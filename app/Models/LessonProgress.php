<?php

namespace App\Models;

use App\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How far a student has got with a lesson.
 *
 * @property int $id
 * @property int $school_id
 * @property int $lesson_id
 * @property int $student_id
 * @property CarbonImmutable $first_viewed_at
 * @property CarbonImmutable $last_viewed_at
 * @property CarbonImmutable|null $completed_at
 * @property int $views
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'lesson_id', 'student_id', 'first_viewed_at', 'last_viewed_at', 'completed_at', 'views'])]
class LessonProgress extends Model
{
    use BelongsToSchool;

    protected $table = 'lesson_progress';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_viewed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'completed_at' => 'datetime',
            'views' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
