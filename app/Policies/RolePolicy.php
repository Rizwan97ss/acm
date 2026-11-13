<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('roles.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('roles.create');
    }

    /**
     * Platform-wide roles are shared by every school, so only super admins may change them.
     */
    public function update(User $actor, Role $role): bool
    {
        return $actor->hasPermission('roles.edit') && $this->owns($actor, $role);
    }

    public function delete(User $actor, Role $role): bool
    {
        return $actor->hasPermission('roles.delete') && ! $role->is_system && $this->owns($actor, $role);
    }

    private function owns(User $actor, Role $role): bool
    {
        if ($role->isPlatformRole()) {
            return $actor->isSuperAdmin();
        }

        return $actor->isPlatformUser() || $actor->school_id === $role->school_id;
    }
}
