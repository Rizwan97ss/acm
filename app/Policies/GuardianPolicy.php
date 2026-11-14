<?php

namespace App\Policies;

use App\Models\Guardian;
use App\Models\User;

class GuardianPolicy extends SchoolOwnedPolicy
{
    protected function module(): string
    {
        return 'guardians';
    }

    /**
     * Inviting a guardian to the portal creates a user account.
     */
    public function invite(User $user, Guardian $guardian): bool
    {
        return $this->update($user, $guardian) && $user->hasPermission('users.create');
    }
}
