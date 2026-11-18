<?php

namespace App\Services\Academics;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AssignmentService
{
    /**
     * A student submits (or, until graded, replaces) their answer.
     */
    public function submit(Student $student, Assignment $assignment, string $content): AssignmentSubmission
    {
        $enrolled = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('section_id', $assignment->section_id)
            ->where('academic_year_id', $assignment->academic_year_id)
            ->exists();

        $chosen = $assignment->students()->pluck('students.id');

        if (! $enrolled || ($chosen->isNotEmpty() && ! $chosen->contains($student->id))) {
            throw ValidationException::withMessages(['content' => __('This assignment is not set for your class.')]);
        }

        $submission = AssignmentSubmission::query()->firstOrNew([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
        ]);

        if ($submission->graded_at !== null) {
            throw ValidationException::withMessages(['content' => __('Your work has already been graded.')]);
        }

        $submission->fill([
            'school_id' => $assignment->school_id,
            'content' => $content,
            'submitted_at' => now(),
        ])->save();

        return $submission;
    }

    public function grade(User $grader, AssignmentSubmission $submission, ?float $marks, ?string $feedback): void
    {
        $max = $submission->assignment->max_marks;

        if ($marks !== null && ($max === null || $marks > (float) $max)) {
            throw ValidationException::withMessages(['marks' => $max === null
                ? __('This assignment is not marked out of a total.')
                : __('Marks cannot exceed :max.', ['max' => $max])]);
        }

        $submission->forceFill([
            'marks' => $marks,
            'feedback' => $feedback,
            'graded_by' => $grader->id,
            'graded_at' => now(),
        ])->save();
    }
}
