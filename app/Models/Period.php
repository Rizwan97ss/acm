<?php

namespace App\Models;

use App\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\PeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One slot of the school's daily bell schedule.
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property string $starts_at
 * @property string $ends_at
 * @property bool $is_break
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'name', 'starts_at', 'ends_at', 'is_break', 'position'])]
class Period extends Model
{
    /** @use HasFactory<PeriodFactory> */
    use BelongsToSchool, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_break' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return HasMany<TimetableEntry, $this>
     */
    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }
}
