<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Section;
use App\Models\Student;
use App\Services\Academics\AttendanceService;
use App\Services\Academics\ResultService;
use App\Services\Academics\TeachingScope;
use App\Services\People\PortalScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ResultController extends Controller
{
    public function __construct(
        private ResultService $results,
        private TeachingScope $scope,
        private PortalScope $portalScope,
    ) {}

    /**
     * Results of one section: every student's marks, total, grade and rank.
     */
    public function section(Request $request, Exam $exam, Section $section): Response
    {
        Gate::authorize('grades.view');
        abort_unless($this->scope->canAccessSection($request->user(), $section, $exam->academicYear, 'grades'), 403);

        return Inertia::render('academics/exams/results', [
            'exam' => ['id' => $exam->id, 'name' => $exam->name, 'published' => $exam->isPublished()],
            'section' => ['id' => $section->id, 'name' => $section->fullName()],
            ...$this->results->forSection($exam, $section),
        ]);
    }

    /**
     * A printable report card. Staff see it any time; students and guardians once published.
     */
    public function reportCard(Request $request, Exam $exam, Student $student, AttendanceService $attendance): Response
    {
        $user = $request->user();
        $isPortalUser = $this->portalScope->isPortalUser($user);

        if ($isPortalUser) {
            abort_unless($exam->isPublished() && in_array($student->id, $this->portalScope->studentIds($user), true), 404);
        } else {
            Gate::authorize('grades.view');
            $enrollment = $student->enrollments()->where('academic_year_id', $exam->academic_year_id)->with('section')->first();
            abort_unless($enrollment !== null && $this->scope->canAccessSection($user, $enrollment->section, $exam->academicYear, 'grades'), 403);
        }

        $exam->load(['academicYear:id,name', 'term:id,name', 'gradeScale.bands']);

        return Inertia::render('academics/exams/report-card', [
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'academic_year' => $exam->academicYear->name,
                'term' => $exam->term?->name,
                'published' => $exam->isPublished(),
            ],
            'student' => [
                'id' => $student->id,
                'name' => $student->full_name,
                'admission_no' => $student->admission_no,
                'date_of_birth' => $student->date_of_birth->toDateString(),
            ],
            'result' => $this->results->forStudent($exam, $student),
            'attendance' => $attendance->summary($student, $exam->academicYear),
            'bands' => $exam->gradeScale->bands->map(fn ($band): array => [
                'grade' => $band->grade,
                'min_percentage' => (float) $band->min_percentage,
                'remark' => $band->remark,
            ])->values(),
        ]);
    }
}
