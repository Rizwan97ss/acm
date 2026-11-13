<?php

namespace App\Policies;

class SubjectPolicy extends SchoolOwnedPolicy
{
    protected function module(): string
    {
        return 'subjects';
    }
}
