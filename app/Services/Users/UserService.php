<?php

namespace App\Services\Users;

use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Rbac\PermissionResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private PermissionResolver $resolver,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, email: string, phone: string|null, password: string|null, school_id: int|null, is_active: bool, roles: list<int>}  $data
     */
    public function create(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data): User {
            $user = new User(Arr::only($data, ['name', 'email', 'phone', 'password', 'school_id', 'is_active']));
            $user->email_verified_at = now();
            $user->save();

            $this->syncRoles($actor, $user, $data['roles']);

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, phone: string|null, password: string|null, school_id: int|null, is_active: bool, roles: list<int>}  $data
     */
    public function update(User $actor, User $user, array $data): User
    {
        return DB::transaction(function () use ($actor, $user, $data): User {
            $attributes = Arr::only($data, ['name', 'email', 'phone', 'school_id', 'is_active']);

            if (! empty($data['password'])) {
                $attributes['password'] = $data['password'];
            }

            if ($actor->is($user)) {
                unset($attributes['is_active'], $attributes['school_id']);
            }

            $user->fill($attributes);

            if ($user->isDirty('email')) {
                $user->email_verified_at = now();
            }

            $user->save();

            $this->syncRoles($actor, $user, $data['roles']);

            return $user;
        });
    }

    public function delete(User $user): void
    {
        DB::transaction(fn () => $user->delete());

        $this->resolver->flush();
    }

    /**
     * Assign roles after checking each is available to the user's school and grants
     * nothing the actor does not hold themselves.
     *
     * @param  list<int>  $roleIds
     */
    private function syncRoles(User $actor, User $user, array $roleIds): void
    {
        $roles = Role::query()->availableTo($user->school_id)->whereIn('id', $roleIds)->get();

        if ($roles->count() !== count(array_unique($roleIds))) {
            throw ValidationException::withMessages(['roles' => __('One or more selected roles are not available for this school.')]);
        }

        foreach ($roles as $role) {
            if ($this->resolver->missing($actor, $this->resolver->permissionsForRole($role)) !== []) {
                throw ValidationException::withMessages(['roles' => __('You cannot assign the :role role because it grants permissions you do not hold.', ['role' => $role->name])]);
            }
        }

        $before = $user->roles()->pluck('slug')->sort()->values()->all();
        $user->roles()->syncWithPivotValues($roles->pluck('id'), ['created_at' => now()]);
        $after = $roles->pluck('slug')->sort()->values()->all();

        if ($before !== $after) {
            $this->audit->log('user.roles_synced', $user, ['roles' => $before], ['roles' => $after], $actor);
        }

        $this->resolver->flush();
    }
}
