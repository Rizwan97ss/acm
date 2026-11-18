<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Enums\LiveClassStatus;
use App\Enums\MeetingProvider;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An online lesson on Zoom, Meet, Teams or similar. The meeting link is encrypted and only
 * handed out through the join route, to eligible students, close to the start time.
 *
 * @property int $id
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $school_class_id
 * @property int|null $section_id
 * @property int $subject_id
 * @property int|null $course_id
 * @property string $title
 * @property string|null $description
 * @property MeetingProvider $provider
 * @property string $meeting_url
 * @property CarbonImmutable $starts_at
 * @property int $duration_minutes
 * @property LiveClassStatus $status
 * @property CarbonImmutable|null $reminded_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'academic_year_id', 'school_class_id', 'section_id', 'subject_id', 'course_id', 'title', 'description', 'provider', 'meeting_url', 'starts_at', 'duration_minutes', 'status', 'reminded_at', 'created_by'])]
#[Hidden(['meeting_url'])]
class LiveClass extends Model
{
    use Auditable, BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => MeetingProvider::class,
            'status' => LiveClassStatus::class,
            'meeting_url' => 'encrypted',
            'starts_at' => 'datetime',
            'reminded_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function endsAt(): CarbonImmutable
    {
        return $this->starts_at->addMinutes($this->duration_minutes);
    }
}
