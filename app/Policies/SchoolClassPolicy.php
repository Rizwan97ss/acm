<?php

namespace App\Policies;

class SchoolClassPolicy extends SchoolOwnedPolicy
{
    protected function module(): string
    {
        return 'classes';
    }
}
