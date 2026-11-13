<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('schools.view');
    }

    /**
     * Only platform users can open new schools or branches.
     */
    public function create(User $actor): bool
    {
        return $actor->isPlatformUser() && $actor->hasPermission('schools.create');
    }

    public function update(User $actor, School $school): bool
    {
        return $actor->hasPermission('schools.edit')
            && ($actor->isPlatformUser() || $actor->school_id === $school->id);
    }

    public function delete(User $actor, School $school): bool
    {
        return $actor->isPlatformUser() && $actor->hasPermission('schools.delete');
    }

    /**
     * Only platform users can switch the active school context.
     */
    public function switch(User $actor, School $school): bool
    {
        return $actor->isPlatformUser() && $actor->hasPermission('schools.view');
    }
}
