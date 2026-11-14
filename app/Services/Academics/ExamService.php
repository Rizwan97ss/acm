<?php

namespace App\Services\Academics;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamPaper;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Exam setup, mark entry and publishing. Published exams are read-only until unpublished.
 */
class ExamService
{
    public function __construct(
        private SettingsService $settings,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{academic_year_id: int, term_id: int|null, grade_scale_id: int, name: string, starts_on: string, ends_on: string}  $data
     */
    public function save(Exam $exam, array $data): Exam
    {
        $this->guardEditable($exam);

        $exam->fill($data)->save();

        return $exam;
    }

    /**
     * Create a paper for every subject of every class that does not have one yet.
     */
    public function generatePapers(Exam $exam): int
    {
        $this->guardEditable($exam);

        $passPercentage = (int) $this->settings->current('academic.pass_percentage');
        $created = 0;

        DB::transaction(function () use ($exam, $passPercentage, &$created): void {
            foreach (SchoolClass::query()->with('subjects:id')->get() as $class) {
                foreach ($class->subjects as $subject) {
                    $paper = ExamPaper::query()->firstOrCreate(
                        ['exam_id' => $exam->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id],
                        ['school_id' => $exam->school_id, 'max_marks' => 100, 'pass_marks' => $passPercentage],
                    );

                    $created += $paper->wasRecentlyCreated ? 1 : 0;
                }
            }
        });

        return $created;
    }

    /**
     * @param  array{school_class_id: int, subject_id: int, held_on: string|null, max_marks: float, pass_marks: float}  $data
     */
    public function savePaper(Exam $exam, ExamPaper $paper, array $data): ExamPaper
    {
        $this->guardEditable($exam);

        $highestMark = $paper->exists ? (float) $paper->marks()->max('marks_obtained') : 0.0;

        if ($highestMark > $data['max_marks']) {
            throw ValidationException::withMessages(['max_marks' => __('Marks above :max have already been entered.', ['max' => $data['max_marks']])]);
        }

        $paper->fill([...$data, 'exam_id' => $exam->id, 'school_id' => $exam->school_id])->save();

        return $paper;
    }

    public function deletePaper(Exam $exam, ExamPaper $paper): void
    {
        $this->guardEditable($exam);

        if ($paper->marks()->exists()) {
            throw ValidationException::withMessages(['paper' => __('Marks have been entered for this paper.')]);
        }

        $paper->delete();
    }

    /**
     * Record marks for the students of one section on one paper.
     *
     * @param  array<int, array{marks_obtained: float|null, is_absent: bool, remarks: string|null}>  $rows  keyed by student id
     */
    public function recordMarks(User $actor, Exam $exam, ExamPaper $paper, Section $section, array $rows): void
    {
        $this->guardEditable($exam);

        if ($section->school_class_id !== $paper->school_class_id) {
            throw ValidationException::withMessages(['section_id' => __('This section does not sit this paper.')]);
        }

        $enrolled = Enrollment::query()
            ->where('section_id', $section->id)
            ->where('academic_year_id', $exam->academic_year_id)
            ->where('status', EnrollmentStatus::Active)
            ->pluck('student_id')
            ->all();

        if (array_diff(array_keys($rows), $enrolled) !== []) {
            throw ValidationException::withMessages(['marks' => __('Some students are not enrolled in this section.')]);
        }

        foreach ($rows as $studentId => $row) {
            if (! $row['is_absent'] && $row['marks_obtained'] !== null && $row['marks_obtained'] > (float) $paper->max_marks) {
                throw ValidationException::withMessages(["marks.{$studentId}.marks_obtained" => __('Marks cannot exceed :max.', ['max' => $paper->max_marks])]);
            }
        }

        DB::transaction(function () use ($actor, $paper, $rows): void {
            foreach ($rows as $studentId => $row) {
                Mark::query()->updateOrCreate(
                    ['exam_paper_id' => $paper->id, 'student_id' => $studentId],
                    [
                        'school_id' => $paper->school_id,
                        'marks_obtained' => $row['is_absent'] ? null : $row['marks_obtained'],
                        'is_absent' => $row['is_absent'],
                        'remarks' => $row['remarks'],
                        'entered_by' => $actor->id,
                    ],
                );
            }
        });
    }

    public function publish(Exam $exam, User $actor): void
    {
        $exam->forceFill(['published_at' => now(), 'published_by' => $actor->id])->save();
        $this->audit->log('exam.published', $exam, actor: $actor);
    }

    public function unpublish(Exam $exam, User $actor): void
    {
        $exam->forceFill(['published_at' => null, 'published_by' => null])->save();
        $this->audit->log('exam.unpublished', $exam, actor: $actor);
    }

    public function delete(Exam $exam): void
    {
        $this->guardEditable($exam);

        if (Mark::query()->whereIn('exam_paper_id', $exam->papers()->select('id'))->exists()) {
            throw ValidationException::withMessages(['exam' => __('Marks have been entered for this exam, so it cannot be deleted.')]);
        }

        $exam->delete();
    }

    private function guardEditable(Exam $exam): void
    {
        if ($exam->exists && $exam->isPublished()) {
            throw ValidationException::withMessages(['exam' => __('Results are published. Unpublish the exam to make changes.')]);
        }
    }
}
