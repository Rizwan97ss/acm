<?php

namespace App\Policies;

class AcademicYearPolicy extends SchoolOwnedPolicy
{
    protected function module(): string
    {
        return 'academic_years';
    }
}
