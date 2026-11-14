<?php

namespace App\Http\Controllers\People;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\People\StudentRequest;
use App\Http\Resources\GuardianResource;
use App\Http\Resources\StudentResource;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\Academics\AcademicOptions;
use App\Services\People\StudentService;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function __construct(
        private StudentService $students,
        private AcademicOptions $options,
        private CurrentSchool $currentSchool,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Student::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'section_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:'.implode(',', array_column(StudentStatus::cases(), 'value'))],
        ]);

        $students = Student::query()
            ->with('currentEnrollment.section.schoolClass:id,name')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $this->applySearch($query, $search))
            ->when($filters['section_id'] ?? null, fn (Builder $query, int $sectionId) => $query->whereHas(
                'currentEnrollment',
                fn (Builder $enrollment) => $enrollment->where('section_id', $sectionId),
            ))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('people/students/index', [
            'students' => StudentResource::collection($students),
            'filters' => $filters,
            'sections' => $this->options->sections(),
            'statuses' => StudentStatus::options(),
            'can' => ['create' => $request->user()->can('create', Student::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Student::class);

        return Inertia::render('people/students/create', $this->formOptions());
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        $student = $this->students->create(
            (int) $this->currentSchool->id(),
            $request->attributesPayload(),
            $request->enrollmentPayload(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Student :no created.', ['no' => $student->admission_no])]);

        return to_route('people.students.show', $student);
    }

    public function show(Request $request, Student $student): Response
    {
        Gate::authorize('view', $student);

        $student->load(['currentEnrollment.section.schoolClass', 'guardians']);

        return Inertia::render('people/students/show', [
            'student' => (new StudentResource($student))->resolve($request),
            'guardians' => $request->user()->can('guardians.view')
                ? GuardianResource::collection($student->guardians)->resolve($request)
                : [],
            'enrollments' => $student->enrollments()
                ->with(['academicYear:id,name,starts_on', 'section.schoolClass:id,name'])
                ->get()
                ->sortByDesc(fn (Enrollment $enrollment) => $enrollment->academicYear->starts_on)
                ->values()
                ->map(fn (Enrollment $enrollment): array => [
                    'id' => $enrollment->id,
                    'academic_year' => $enrollment->academicYear->name,
                    'academic_year_id' => $enrollment->academic_year_id,
                    'section' => $enrollment->section->fullName(),
                    'section_id' => $enrollment->section_id,
                    'roll_number' => $enrollment->roll_number,
                    'status' => $enrollment->status->label(),
                ]),
            'years' => $this->options->years(),
            'sections' => $this->options->sections(),
            'relationships' => GuardianRelationship::options(),
            'can' => [
                'linkGuardians' => $request->user()->can('update', $student) && $request->user()->can('guardians.view'),
                'createGuardians' => $request->user()->can('guardians.create'),
            ],
        ]);
    }

    public function edit(Request $request, Student $student): Response
    {
        Gate::authorize('update', $student);

        return Inertia::render('people/students/edit', [
            ...$this->formOptions(),
            'student' => (new StudentResource($student))->resolve($request),
        ]);
    }

    public function update(StudentRequest $request, Student $student): RedirectResponse
    {
        $student->update($request->attributesPayload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Student updated.')]);

        return to_route('people.students.show', $student);
    }

    public function destroy(Student $student): RedirectResponse
    {
        Gate::authorize('delete', $student);

        $this->students->delete($student);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Student archived.')]);

        return to_route('people.students.index');
    }

    /**
     * Partial match on name or admission number; exact match on national ID via its blind index.
     *
     * @param  Builder<Student>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $escaped = addcslashes($search, '%_\\');
        $nationalIdIndex = (new Student)->blindIndexFor('national_id', $search);

        $query->where(function (Builder $query) use ($escaped, $nationalIdIndex): void {
            $query->where('first_name', 'like', "%{$escaped}%")
                ->orWhere('last_name', 'like', "%{$escaped}%")
                ->orWhere('admission_no', 'like', "%{$escaped}%");

            if ($nationalIdIndex !== null) {
                $query->orWhere('national_id_index', $nationalIdIndex);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'years' => $this->options->years(),
            'sections' => $this->options->sections(),
            'statuses' => StudentStatus::options(),
            'genders' => Gender::options(),
            'bloodGroups' => StudentRequest::BLOOD_GROUPS,
        ];
    }
}
