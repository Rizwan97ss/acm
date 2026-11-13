<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Enums\SubjectType;
use Carbon\CarbonImmutable;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property string $code
 * @property SubjectType $type
 * @property string|null $color
 * @property string|null $description
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'name', 'code', 'type', 'color', 'description', 'is_active'])]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use Auditable, BelongsToSchool, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'core',
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SubjectType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<SchoolClass, $this>
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_subject')->withPivot('periods_per_week');
    }

    /**
     * @return HasMany<TimetableEntry, $this>
     */
    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }
}
