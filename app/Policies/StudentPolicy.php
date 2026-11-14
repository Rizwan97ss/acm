<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy extends SchoolOwnedPolicy
{
    protected function module(): string
    {
        return 'students';
    }

    /**
     * Inviting a student to the portal creates a user account.
     */
    public function invite(User $user, Student $student): bool
    {
        return $this->update($user, $student) && $user->hasPermission('users.create');
    }
}
