<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;

class ExamPolicy extends SchoolOwnedPolicy
{
    protected function module(): string
    {
        return 'exams';
    }

    /**
     * Publishing makes results visible to students and parents.
     */
    public function publish(User $user, Exam $exam): bool
    {
        return $user->hasPermission('exams.approve') && $this->sameSchool($user, $exam);
    }
}
