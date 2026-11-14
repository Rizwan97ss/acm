<?php

namespace App\Policies;

use App\Enums\AdmissionStatus;
use App\Models\AdmissionApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AdmissionApplicationPolicy extends SchoolOwnedPolicy
{
    protected function module(): string
    {
        return 'admissions';
    }

    /**
     * Applications can only be edited while they await a decision.
     */
    public function update(User $user, Model $record): bool
    {
        return parent::update($user, $record)
            && $record instanceof AdmissionApplication
            && $record->status === AdmissionStatus::Pending;
    }

    public function decide(User $user, AdmissionApplication $application): bool
    {
        return $user->hasPermission('admissions.approve')
            && $this->sameSchool($user, $application)
            && $application->status === AdmissionStatus::Pending;
    }

    public function enroll(User $user, AdmissionApplication $application): bool
    {
        return $user->hasPermission('admissions.approve')
            && $user->hasPermission('students.create')
            && $this->sameSchool($user, $application)
            && $application->status === AdmissionStatus::Approved;
    }
}
