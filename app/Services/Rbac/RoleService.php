<?php

namespace App\Services\Rbac;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RoleService
{
    public function __construct(
        private PermissionResolver $resolver,
        private AuditLogger $audit,
    ) {}

    /**
     * Create a role owned by the given school (null = platform-wide).
     *
     * @param  array{name: string, description: string|null, parent_id: int|null, permissions: list<string>}  $data
     */
    public function create(User $actor, ?int $schoolId, array $data): Role
    {
        return DB::transaction(function () use ($actor, $schoolId, $data): Role {
            $role = new Role(['school_id' => $schoolId]);

            return $this->save($actor, $role, $data);
        });
    }

    /**
     * @param  array{name: string, description: string|null, parent_id: int|null, permissions: list<string>}  $data
     */
    public function update(User $actor, Role $role, array $data): Role
    {
        return DB::transaction(fn (): Role => $this->save($actor, $role, $data));
    }

    public function delete(Role $role): void
    {
        if ($role->is_system) {
            throw ValidationException::withMessages(['role' => __('System roles cannot be deleted.')]);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => __('Remove this role from all users before deleting it.')]);
        }

        DB::transaction(function () use ($role): void {
            $role->children()->update(['parent_id' => null]);
            $role->delete();
        });

        $this->resolver->flush();
    }

    /**
     * @param  array{name: string, description: string|null, parent_id: int|null, permissions: list<string>}  $data
     */
    private function save(User $actor, Role $role, array $data): Role
    {
        $permissions = array_values(array_unique($data['permissions']));
        $parent = $data['parent_id'] !== null ? Role::query()->findOrFail($data['parent_id']) : null;

        $this->guardParent($role, $parent);
        $this->guardGrantable($actor, [
            ...$permissions,
            ...($parent ? $this->resolver->permissionsForRole($parent) : []),
        ]);

        $role->fill([
            'name' => $data['name'],
            'description' => $data['description'],
            'parent_id' => $parent?->id,
        ]);

        if (! $role->exists) {
            $role->slug = $this->uniqueSlug($data['name'], $role->school_id);
        }

        $role->save();

        $permissionIds = Permission::query()->whereIn('name', $permissions)->pluck('id');

        if ($permissionIds->count() !== count($permissions)) {
            throw new RuntimeException('Some declared permissions are missing from the database. Run `php artisan rbac:sync`.');
        }

        $before = $role->permissions()->pluck('name')->sort()->values()->all();
        $role->permissions()->sync($permissionIds);
        $after = $role->permissions()->pluck('name')->sort()->values()->all();

        if ($before !== $after) {
            $this->audit->log('role.permissions_synced', $role,
                ['permissions' => array_values(array_diff($before, $after))],
                ['permissions' => array_values(array_diff($after, $before))],
            );
        }

        $this->resolver->flush();

        return $role;
    }

    /**
     * A parent must be visible to the role's school and must not create an inheritance cycle.
     */
    private function guardParent(Role $role, ?Role $parent): void
    {
        if ($parent === null) {
            return;
        }

        $visible = $parent->school_id === null || $parent->school_id === $role->school_id;

        if (! $visible || $this->resolver->isSuperAdminRole($parent)) {
            throw ValidationException::withMessages(['parent_id' => __('The selected parent role is not available.')]);
        }

        for ($ancestor = $parent; $ancestor !== null; $ancestor = $ancestor->parent) {
            if ($role->exists && $ancestor->id === $role->id) {
                throw ValidationException::withMessages(['parent_id' => __('A role cannot inherit from itself or its descendants.')]);
            }
        }
    }

    /**
     * Prevent privilege escalation: an actor may only grant permissions they hold.
     *
     * @param  list<string>  $permissions
     */
    private function guardGrantable(User $actor, array $permissions): void
    {
        $missing = $this->resolver->missing($actor, $permissions);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'permissions' => __('You cannot grant permissions you do not hold: :list', ['list' => implode(', ', $missing)]),
            ]);
        }
    }

    private function uniqueSlug(string $name, ?int $schoolId): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;

        for ($suffix = 2; Role::query()->where('school_id', $schoolId)->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
