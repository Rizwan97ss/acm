<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Concerns\TargetsAudience;
use App\Enums\Audience;
use Carbon\CarbonImmutable;
use Database\Factories\SchoolEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A calendar entry (holiday, meeting, sports day, exam week...).
 *
 * @property int $id
 * @property int $school_id
 * @property string $title
 * @property string|null $description
 * @property string|null $location
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property bool $all_day
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'title', 'description', 'location', 'starts_at', 'ends_at', 'all_day', 'audience', 'school_class_id', 'created_by'])]
class SchoolEvent extends Model
{
    /** @use HasFactory<SchoolEventFactory> */
    use Auditable, BelongsToSchool, HasFactory, TargetsAudience;

    protected $table = 'events';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => Audience::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }
}
