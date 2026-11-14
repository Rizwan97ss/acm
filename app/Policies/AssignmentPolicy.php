<?php

namespace App\Policies;

class AssignmentPolicy extends SchoolOwnedPolicy
{
    protected function module(): string
    {
        return 'assignments';
    }
}
