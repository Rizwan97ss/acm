<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Enums\PublishStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A topic within a chapter: text plus materials. Visible to students once published and
 * its publish time has passed.
 *
 * @property int $id
 * @property int $school_id
 * @property int $course_id
 * @property int $course_chapter_id
 * @property string $title
 * @property string|null $summary
 * @property string|null $body
 * @property int|null $estimated_minutes
 * @property PublishStatus $status
 * @property CarbonImmutable|null $publish_at
 * @property CarbonImmutable|null $notified_at
 * @property int $position
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'course_id', 'course_chapter_id', 'title', 'summary', 'body', 'estimated_minutes', 'status', 'publish_at', 'notified_at', 'position', 'created_by'])]
class Lesson extends Model
{
    use Auditable, BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'publish_at' => 'datetime',
            'notified_at' => 'datetime',
            'position' => 'integer',
            'estimated_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<CourseChapter, $this>
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(CourseChapter::class, 'course_chapter_id');
    }

    /**
     * @return HasMany<LessonMaterial, $this>
     */
    public function materials(): HasMany
    {
        return $this->hasMany(LessonMaterial::class)->orderBy('position');
    }

    /**
     * @return HasMany<LessonProgress, $this>
     */
    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }
}
