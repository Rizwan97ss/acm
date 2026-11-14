<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Http\Requests\People\EnrollStudentRequest;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Student;
use App\Services\People\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class StudentEnrollmentController extends Controller
{
    public function store(EnrollStudentRequest $request, Student $student, EnrollmentService $enrollments): RedirectResponse
    {
        $enrollments->enroll(
            $student,
            AcademicYear::query()->findOrFail($request->integer('academic_year_id')),
            Section::query()->findOrFail($request->integer('section_id')),
            $request->filled('roll_number') ? $request->integer('roll_number') : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Enrollment saved.')]);

        return to_route('people.students.show', $student);
    }
}
