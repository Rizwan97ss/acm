<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Rbac\PermissionResolver;

class UserPolicy
{
    public function __construct(private PermissionResolver $resolver) {}

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('users.create');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.edit') && $this->canManage($actor, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.delete')
            && ! $actor->is($target)
            && $this->canManage($actor, $target);
    }

    /**
     * Actors manage users of their own school (platform users manage everyone) and never
     * users holding permissions beyond their own.
     */
    private function canManage(User $actor, User $target): bool
    {
        $sameTenant = $actor->isPlatformUser() || $actor->school_id === $target->school_id;

        return $sameTenant && $this->resolver->covers($actor, $target);
    }
}
