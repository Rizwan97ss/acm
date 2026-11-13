<?php

namespace App\Concerns;

use App\Models\School;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant isolation for school-owned records. When a school is active for the request,
 * queries are constrained to it and new records are stamped with its id. Domain modules
 * (students, fees, attendance...) use this trait so isolation is never forgotten.
 *
 * @mixin Model
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope('school', function (Builder $builder): void {
            $schoolId = app(CurrentSchool::class)->id();

            if ($schoolId !== null) {
                $builder->where($builder->qualifyColumn('school_id'), $schoolId);
            }
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('school_id') === null) {
                $model->setAttribute('school_id', app(CurrentSchool::class)->id());
            }
        });
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
