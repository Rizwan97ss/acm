<?php

namespace App\Services\Tenancy;

use App\Models\School;
use Illuminate\Support\Str;

/**
 * Knows whether the app runs for one institute or many, and resolves the
 * institute in single mode.
 */
class Tenancy
{
    private ?School $institute = null;

    public function isSingle(): bool
    {
        return config('tenancy.mode') !== 'multi';
    }

    public function isMulti(): bool
    {
        return ! $this->isSingle();
    }

    /**
     * The one institute in single mode, created on first use.
     */
    public function institute(): School
    {
        return $this->institute ??= School::query()->orderBy('id')->first()
            ?? School::query()->create([
                'name' => (string) config('tenancy.default_institute.name'),
                'code' => Str::upper((string) config('tenancy.default_institute.code')),
                'is_active' => true,
            ]);
    }
}
