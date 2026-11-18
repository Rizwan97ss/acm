<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSchool;
use App\Enums\AssignmentType;
use Carbon\CarbonImmutable;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $section_id
 * @property int $subject_id
 * @property int|null $created_by
 * @property AssignmentType $type
 * @property string $title
 * @property string $description
 * @property CarbonImmutable $due_on
 * @property string|null $max_marks
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_id', 'academic_year_id', 'section_id', 'subject_id', 'created_by', 'type', 'title', 'description', 'due_on', 'max_marks'])]
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use Auditable, BelongsToSchool, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AssignmentType::class,
            'due_on' => 'date',
            'max_marks' => 'decimal:2',
        ];
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
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AssignmentSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    /**
     * Files the teacher attached (not those handed in with submissions).
     *
     * @return HasMany<AssignmentFile, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(AssignmentFile::class)->whereNull('assignment_submission_id');
    }

    /**
     * When any are chosen, only these students of the section receive the work.
     *
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'assignment_student');
    }
}
