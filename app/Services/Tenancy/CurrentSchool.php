<?php

namespace App\Services\Tenancy;

use App\Models\School;

/**
 * Holds the school the current request operates on. School-bound users are
 * always locked to their own school; platform users (no school) may switch
 * between schools or work in the global context (null).
 */
class CurrentSchool
{
    private ?School $school = null;

    public function set(?School $school): void
    {
        $this->school = $school;
    }

    public function get(): ?School
    {
        return $this->school;
    }

    public function id(): ?int
    {
        return $this->school?->id;
    }

    public function isGlobal(): bool
    {
        return $this->school === null;
    }
}
