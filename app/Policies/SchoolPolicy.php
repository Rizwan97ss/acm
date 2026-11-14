<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;
use App\Services\Tenancy\Tenancy;

class SchoolPolicy
{
    public function __construct(private Tenancy $tenancy) {}

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('schools.view');
    }

    /**
     * Only platform users can open new schools or branches, and only in multi-institute mode.
     */
    public function create(User $actor): bool
    {
        return $this->tenancy->isMulti() && $actor->isPlatformUser() && $actor->hasPermission('schools.create');
    }

    public function update(User $actor, School $school): bool
    {
        return $actor->hasPermission('schools.edit')
            && ($actor->isPlatformUser() || $actor->school_id === $school->id);
    }

    public function delete(User $actor, School $school): bool
    {
        return $this->tenancy->isMulti() && $actor->isPlatformUser() && $actor->hasPermission('schools.delete');
    }

    /**
     * Only platform users can switch the active school context, and only in multi-institute mode.
     */
    public function switch(User $actor, School $school): bool
    {
        return $this->tenancy->isMulti() && $actor->isPlatformUser() && $actor->hasPermission('schools.view');
    }
}
