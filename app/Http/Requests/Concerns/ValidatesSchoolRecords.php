<?php

namespace App\Http\Requests\Concerns;

use App\Services\Tenancy\CurrentSchool;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation helpers that pin record references to the active school, so an id
 * belonging to another school is rejected as if it did not exist.
 */
trait ValidatesSchoolRecords
{
    protected function schoolId(): int
    {
        return (int) app(CurrentSchool::class)->id();
    }

    protected function existsInSchool(string $table, string $column = 'id'): Exists
    {
        $rule = (new Exists($table, $column))->where('school_id', $this->schoolId());

        return in_array($table, ['students', 'guardians', 'employees'], true)
            ? $rule->whereNull('deleted_at')
            : $rule;
    }

    protected function uniqueInSchool(string $table, string $column, ?int $ignoreId = null): Unique
    {
        return (new Unique($table, $column))->where('school_id', $this->schoolId())->ignore($ignoreId);
    }
}
