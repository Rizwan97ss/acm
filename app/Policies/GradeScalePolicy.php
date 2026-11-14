<?php

namespace App\Policies;

use App\Models\GradeScale;
use App\Models\User;

/**
 * Grading scales affect every result, so changing them needs grades.approve.
 */
class GradeScalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('grades.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('grades.approve');
    }

    public function update(User $user, GradeScale $scale): bool
    {
        return $user->hasPermission('grades.approve') && ($user->isPlatformUser() || $user->school_id === $scale->school_id);
    }

    public function delete(User $user, GradeScale $scale): bool
    {
        return $this->update($user, $scale);
    }
}
