<?php

namespace App\Policies;

class TimetableEntryPolicy extends SchoolOwnedPolicy
{
    protected function module(): string
    {
        return 'timetables';
    }
}
