<?php

namespace App\Services\Rbac;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the effective permissions of users and roles, including permissions
 * inherited from parent roles. Results are cached per user and invalidated
 * globally by bumping a version number whenever roles or assignments change.
 */
class PermissionResolver
{
    private const int MAX_INHERITANCE_DEPTH = 10;

    /**
     * Per-request memo of resolved user permissions.
     *
     * @var array<int, array{super: bool, permissions: array<string, true>}>
     */
    private array $resolved = [];

    public function __construct(
        private Cache $cache,
        private PermissionRegistry $registry,
    ) {}

    /**
     * Determine whether the user holds the permission. Super admins hold every permission.
     */
    public function has(User $user, string $permission): bool
    {
        $resolved = $this->resolve($user);

        return $resolved['super'] || isset($resolved['permissions'][$permission]);
    }

    /**
     * Determine whether the user holds the super admin role.
     */
    public function isSuperAdmin(User $user): bool
    {
        return $this->resolve($user)['super'];
    }

    /**
     * Get the user's effective permission names. Super admins receive every declared permission.
     *
     * @return list<string>
     */
    public function permissionsFor(User $user): array
    {
        $resolved = $this->resolve($user);

        return $resolved['super'] ? $this->registry->all() : array_keys($resolved['permissions']);
    }

    /**
     * Get the effective permission names of a role, including inherited ones.
     *
     * @return list<string>
     */
    public function permissionsForRole(Role $role): array
    {
        if ($this->isSuperAdminRole($role)) {
            return $this->registry->all();
        }

        return $this->permissionNamesForRoleIds($this->withAncestors([$role->id]));
    }

    /**
     * Determine whether every permission of the target user is also held by the actor.
     */
    public function covers(User $actor, User $target): bool
    {
        if ($this->isSuperAdmin($actor)) {
            return true;
        }

        return array_diff($this->permissionsFor($target), $this->permissionsFor($actor)) === [];
    }

    /**
     * Get the permissions in the list that the actor does not hold.
     *
     * @param  list<string>  $permissions
     * @return list<string>
     */
    public function missing(User $actor, array $permissions): array
    {
        if ($this->isSuperAdmin($actor)) {
            return [];
        }

        return array_values(array_diff($permissions, $this->permissionsFor($actor)));
    }

    /**
     * Invalidate every cached permission set.
     */
    public function flush(): void
    {
        $this->cache->forever('rbac:version', $this->version() + 1);
        $this->resolved = [];
    }

    /**
     * Determine whether the role is the platform super admin role.
     */
    public function isSuperAdminRole(Role $role): bool
    {
        return $role->school_id === null && $role->slug === config('rbac.super_admin_role');
    }

    /**
     * @return array{super: bool, permissions: array<string, true>}
     */
    private function resolve(User $user): array
    {
        return $this->resolved[$user->id] ??= $this->cache->remember(
            "rbac:user:{$user->id}:v{$this->version()}",
            (int) config('rbac.cache_ttl', 3600),
            fn (): array => $this->load($user),
        );
    }

    /**
     * @return array{super: bool, permissions: array<string, true>}
     */
    private function load(User $user): array
    {
        $roles = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->get(['roles.id', 'roles.slug', 'roles.school_id']);

        $isSuperAdmin = $roles->contains(
            fn (object $role): bool => $role->school_id === null && $role->slug === config('rbac.super_admin_role'),
        );

        if ($isSuperAdmin) {
            return ['super' => true, 'permissions' => []];
        }

        $roleIds = array_values(array_map(intval(...), $roles->pluck('id')->all()));
        $names = $this->permissionNamesForRoleIds($this->withAncestors($roleIds));

        return ['super' => false, 'permissions' => array_fill_keys($names, true)];
    }

    /**
     * Walk parent links upward, guarding against cycles and runaway depth.
     *
     * @param  list<int>  $roleIds
     * @return list<int>
     */
    private function withAncestors(array $roleIds): array
    {
        $all = $roleIds;
        $frontier = $roleIds;

        for ($depth = 0; $frontier !== [] && $depth < self::MAX_INHERITANCE_DEPTH; $depth++) {
            $parents = DB::table('roles')
                ->whereIn('id', $frontier)
                ->whereNotNull('parent_id')
                ->pluck('parent_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            $frontier = array_values(array_diff($parents, $all));
            $all = [...$all, ...$frontier];
        }

        return $all;
    }

    /**
     * @param  list<int>  $roleIds
     * @return list<string>
     */
    private function permissionNamesForRoleIds(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $names = DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->whereIn('permission_role.role_id', $roleIds)
            ->distinct()
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->all();

        return array_values(array_map(strval(...), $names));
    }

    private function version(): int
    {
        return (int) $this->cache->get('rbac:version', 1);
    }
}
