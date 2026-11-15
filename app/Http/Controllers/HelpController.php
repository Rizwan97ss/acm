<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\FeeItem;
use App\Models\GradeScale;
use App\Models\Invoice;
use App\Models\Period;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The in-app user guide. Content lives in the page and is filtered by the viewer's
 * permissions; administrators also get a live checklist of the school's setup.
 */
class HelpController extends Controller
{
    public function __invoke(Request $request, CurrentSchool $currentSchool): Response
    {
        $showChecklist = ! $currentSchool->isGlobal() && $request->user()->can('settings.edit');

        return Inertia::render('help', [
            'setup' => $showChecklist ? $this->setupProgress() : null,
        ]);
    }

    /**
     * Whether each setup step has data yet, in the order the guide recommends.
     *
     * @return array<string, bool>
     */
    private function setupProgress(): array
    {
        $year = AcademicYear::query()->current()->first();

        return [
            'academic_year' => $year !== null,
            'classes' => SchoolClass::query()->whereHas('sections')->exists(),
            'subjects' => Subject::query()->exists(),
            'periods' => Period::query()->exists(),
            'grade_scales' => GradeScale::query()->exists(),
            'staff' => Employee::query()->exists(),
            'timetable' => $year !== null && TimetableEntry::query()->where('academic_year_id', $year->id)->exists(),
            'students' => Student::query()->exists(),
            'fees' => $year !== null && FeeItem::query()->where('academic_year_id', $year->id)->exists(),
            'invoices' => Invoice::query()->exists(),
        ];
    }
}
