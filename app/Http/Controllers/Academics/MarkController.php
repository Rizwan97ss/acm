<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\MarksRequest;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamPaper;
use App\Models\Mark;
use App\Models\Section;
use App\Services\Academics\AttendanceService;
use App\Services\Academics\ExamService;
use App\Services\Academics\TeachingScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mark entry for one paper and one section.
 */
class MarkController extends Controller
{
    public function __construct(
        private ExamService $exams,
        private TeachingScope $scope,
    ) {}

    public function edit(Request $request, Exam $exam, ExamPaper $paper, Section $section, AttendanceService $attendance): Response
    {
        $this->authorizeEntry($request, $exam, $paper, $section, write: false);

        $marks = Mark::query()->where('exam_paper_id', $paper->id)->get()->keyBy('student_id');

        return Inertia::render('academics/exams/marks', [
            'exam' => ['id' => $exam->id, 'name' => $exam->name, 'published' => $exam->isPublished()],
            'paper' => [
                'id' => $paper->id,
                'subject' => $paper->subject->name,
                'class' => $paper->schoolClass->name,
                'max_marks' => (float) $paper->max_marks,
                'pass_marks' => (float) $paper->pass_marks,
            ],
            'section' => ['id' => $section->id, 'name' => $section->fullName()],
            'rows' => $attendance->roster($section, $exam->academicYear)->map(fn (Enrollment $enrollment): array => [
                'student_id' => $enrollment->student_id,
                'name' => $enrollment->student->full_name,
                'admission_no' => $enrollment->student->admission_no,
                'roll_number' => $enrollment->roll_number,
                'marks_obtained' => $marks->get($enrollment->student_id)?->marks_obtained !== null ? (float) $marks->get($enrollment->student_id)->marks_obtained : null,
                'is_absent' => (bool) $marks->get($enrollment->student_id)?->is_absent,
                'remarks' => $marks->get($enrollment->student_id)?->remarks,
            ])->values(),
            'canEdit' => ! $exam->isPublished() && ($request->user()->can('grades.create') || $request->user()->can('grades.edit')),
        ]);
    }

    public function update(MarksRequest $request, Exam $exam, ExamPaper $paper, Section $section): RedirectResponse
    {
        $this->authorizeEntry($request, $exam, $paper, $section, write: true);

        $this->exams->recordMarks($request->user(), $exam, $paper, $section, $request->rows());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marks saved.')]);

        return to_route('academics.exams.marks.edit', [$exam, $paper, $section]);
    }

    private function authorizeEntry(Request $request, Exam $exam, ExamPaper $paper, Section $section, bool $write): void
    {
        abort_unless($paper->exam_id === $exam->id && $paper->school_class_id === $section->school_class_id, 404);
        abort_unless($request->user()->can('grades.view'), 403);
        abort_unless($this->scope->canTeachSubject($request->user(), $section, $paper->subject_id, $exam->academicYear, 'grades'), 403);

        if ($write) {
            abort_unless($request->user()->can('grades.create') || $request->user()->can('grades.edit'), 403);
        }
    }
}
