<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Student;
use App\Services\Academics\AttendanceService;
use App\Services\Academics\ResultService;
use App\Services\People\PortalScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My progress" for students and guardians: attendance, published results and
 * open homework for each child, on one page.
 */
class ProgressController extends Controller
{
    public function __invoke(
        Request $request,
        PortalScope $portalScope,
        AttendanceService $attendance,
        ResultService $results,
    ): Response|RedirectResponse {
        $user = $request->user();

        if (! $portalScope->isPortalUser($user)) {
            return to_route('dashboard');
        }

        $year = AcademicYear::query()->current()->first();
        $students = Student::query()->whereIn('id', $portalScope->studentIds($user))->orderBy('first_name')->get();

        $exams = $year === null ? collect() : Exam::query()
            ->where('academic_year_id', $year->id)
            ->whereNotNull('published_at')
            ->orderByDesc('starts_on')
            ->get();

        return Inertia::render('academics/progress', [
            'year' => $year?->only(['id', 'name']),
            'children' => $students->map(function (Student $student) use ($year, $exams, $attendance, $results): array {
                $enrollment = $year === null ? null : Enrollment::query()
                    ->with('section.schoolClass')
                    ->where('student_id', $student->id)
                    ->where('academic_year_id', $year->id)
                    ->first();

                $openAssignments = $enrollment === null ? collect() : Assignment::query()
                    ->with('subject:id,name,color')
                    ->where('section_id', $enrollment->section_id)
                    ->whereDate('due_on', '>=', now()->toDateString())
                    ->orderBy('due_on')
                    ->limit(10)
                    ->get();

                $submittedIds = AssignmentSubmission::query()
                    ->where('student_id', $student->id)
                    ->whereIn('assignment_id', $openAssignments->pluck('id'))
                    ->pluck('assignment_id')
                    ->all();

                return [
                    'id' => $student->id,
                    'name' => $student->full_name,
                    'admission_no' => $student->admission_no,
                    'section' => $enrollment?->section->fullName(),
                    'attendance' => $year === null ? null : $attendance->summary($student, $year),
                    'results' => $exams->map(function (Exam $exam) use ($student, $results): ?array {
                        $result = $results->forStudent($exam, $student);

                        return $result === null ? null : [
                            'exam_id' => $exam->id,
                            'exam' => $exam->name,
                            'percentage' => $result['percentage'],
                            'grade' => $result['grade'],
                            'rank' => $result['rank'],
                            'class_size' => $result['class_size'],
                            'passed' => $result['passed'],
                        ];
                    })->filter()->values(),
                    'assignments' => $openAssignments->map(fn (Assignment $assignment): array => [
                        'id' => $assignment->id,
                        'title' => $assignment->title,
                        'subject' => $assignment->subject->name,
                        'color' => $assignment->subject->color,
                        'due_on' => $assignment->due_on->toDateString(),
                        'submitted' => in_array($assignment->id, $submittedIds, true),
                    ])->values(),
                ];
            })->values(),
        ]);
    }
}
