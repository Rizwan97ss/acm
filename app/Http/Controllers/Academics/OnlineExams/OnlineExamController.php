<?php

namespace App\Http\Controllers\Academics\OnlineExams;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\OnlineExam;
use App\Services\Academics\Exams\OnlineExamScope;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Helpers shared by the online exam controllers.
 */
abstract class OnlineExamController extends Controller
{
    public function __construct(protected OnlineExamScope $scope) {}

    protected function currentYear(): AcademicYear
    {
        $year = AcademicYear::query()->current()->first();

        abort_if($year === null, 409, __('Set a current academic year first.'));

        return $year;
    }

    protected function authorizeView(OnlineExam $exam): void
    {
        abort_unless($this->scope->canView(request()->user(), $exam), 403);
    }

    protected function authorizeManage(OnlineExam $exam): void
    {
        abort_unless($this->scope->canManage(request()->user(), $exam), 403);
    }

    /**
     * Who the exam is for, e.g. "Class 5 – A", "Class 5 (all sections)" or "Class 5 · 3 students".
     */
    protected function audienceLabel(OnlineExam $exam): string
    {
        $exam->loadMissing(['schoolClass:id,name', 'section:id,name']);
        $chosen = $exam->relationLoaded('students') ? $exam->students->count() : $exam->students()->count();
        $base = $exam->section !== null
            ? "{$exam->schoolClass->name} – {$exam->section->name}"
            : __(':class (all sections)', ['class' => $exam->schoolClass->name]);

        return $chosen > 0 ? trans_choice(':base · :count chosen student|:base · :count chosen students', $chosen, ['base' => $base, 'count' => $chosen]) : $base;
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(OnlineExam $exam): array
    {
        $exam->loadMissing(['subject:id,name,color', 'schoolClass:id,name', 'section:id,name']);
        $state = $exam->state();

        return [
            'id' => $exam->id,
            'title' => $exam->title,
            'instructions' => $exam->instructions,
            'school_class_id' => $exam->school_class_id,
            'section_id' => $exam->section_id,
            'subject_id' => $exam->subject_id,
            'subject' => $exam->subject->name,
            'subject_color' => $exam->subject->color,
            'audience' => $this->audienceLabel($exam),
            'starts_at' => $exam->starts_at->toIso8601String(),
            'ends_at' => $exam->ends_at->toIso8601String(),
            'duration_minutes' => $exam->duration_minutes,
            'total_marks' => (float) $exam->total_marks,
            'passing_marks' => (float) $exam->passing_marks,
            'max_attempts' => $exam->max_attempts,
            'shuffle_questions' => $exam->shuffle_questions,
            'shuffle_options' => $exam->shuffle_options,
            'negative_marking' => $exam->negative_marking,
            'show_rank' => $exam->show_rank,
            'status' => $exam->status->value,
            'state' => $state->value,
            'state_label' => $state->label(),
            'results_status' => $exam->results_status->value,
            'results_status_label' => $exam->results_status->label(),
            'results_published_at' => $exam->results_published_at?->toIso8601String(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array{current_page: int, from: int|null, last_page: int, per_page: int, to: int|null, total: int, links: array<int, mixed>}
     */
    protected function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
            'links' => $paginator->linkCollection()->all(),
        ];
    }
}
