<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\AcademicYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property bool $is_current
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'name', 'starts_on', 'ends_on', 'is_current'])]
class AcademicYear extends Model
{
    /** @use HasFactory<AcademicYearFactory> */
    use Auditable, BelongsToSchool, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Term, $this>
     */
    public function terms(): HasMany
    {
        return $this->hasMany(Term::class)->orderBy('starts_on');
    }

    /**
     * @return HasMany<TimetableEntry, $this>
     */
    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    /**
     * @param  Builder<AcademicYear>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('is_current', true);
    }
}
