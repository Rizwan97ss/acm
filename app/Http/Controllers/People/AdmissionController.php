<?php

namespace App\Http\Controllers\People;

use App\Enums\AdmissionStatus;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Http\Controllers\Controller;
use App\Http\Requests\People\AdmissionRequest;
use App\Http\Requests\People\EnrollAdmissionRequest;
use App\Http\Resources\AdmissionApplicationResource;
use App\Models\AdmissionApplication;
use App\Models\Section;
use App\Services\Academics\AcademicOptions;
use App\Services\People\AdmissionService;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdmissionController extends Controller
{
    public function __construct(
        private AdmissionService $admissions,
        private AcademicOptions $options,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AdmissionApplication::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(AdmissionStatus::class)],
        ]);

        $applications = AdmissionApplication::query()
            ->with(['academicYear:id,name', 'schoolClass:id,name'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $escaped = addcslashes($search, '%_\\');
                $query->where(fn (Builder $query) => $query
                    ->where('first_name', 'like', "%{$escaped}%")
                    ->orWhere('last_name', 'like', "%{$escaped}%")
                    ->orWhere('application_no', 'like', "%{$escaped}%")
                    ->orWhere('guardian_name', 'like', "%{$escaped}%"));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('people/admissions/index', [
            'applications' => AdmissionApplicationResource::collection($applications),
            'filters' => $filters,
            'statuses' => AdmissionStatus::options(),
            'counts' => AdmissionApplication::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'can' => ['create' => $request->user()->can('create', AdmissionApplication::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', AdmissionApplication::class);

        return Inertia::render('people/admissions/create', $this->formOptions());
    }

    public function store(AdmissionRequest $request, CurrentSchool $currentSchool): RedirectResponse
    {
        $application = $this->admissions->submit($request->user(), (int) $currentSchool->id(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Application :no recorded.', ['no' => $application->application_no])]);

        return to_route('people.admissions.show', $application);
    }

    public function show(Request $request, AdmissionApplication $admission): Response
    {
        Gate::authorize('view', $admission);

        $admission->load(['academicYear:id,name', 'schoolClass:id,name', 'reviewer:id,name']);

        return Inertia::render('people/admissions/show', [
            'application' => (new AdmissionApplicationResource($admission))->resolve($request),
            'sections' => Section::query()
                ->where('school_class_id', $admission->school_class_id)
                ->withCount(['enrollments' => fn (Builder $query) => $query->where('academic_year_id', $admission->academic_year_id)])
                ->orderBy('name')
                ->get()
                ->map(fn (Section $section): array => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'capacity' => $section->capacity,
                    'enrolled' => (int) $section->getAttribute('enrollments_count'),
                ]),
        ]);
    }

    public function edit(Request $request, AdmissionApplication $admission): Response
    {
        Gate::authorize('update', $admission);

        return Inertia::render('people/admissions/edit', [
            ...$this->formOptions(),
            'application' => (new AdmissionApplicationResource($admission))->resolve($request),
        ]);
    }

    public function update(AdmissionRequest $request, AdmissionApplication $admission): RedirectResponse
    {
        $admission->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Application updated.')]);

        return to_route('people.admissions.show', $admission);
    }

    public function decide(Request $request, AdmissionApplication $admission): RedirectResponse
    {
        Gate::authorize('decide', $admission);

        $validated = $request->validate([
            'decision' => ['required', Rule::in([AdmissionStatus::Approved->value, AdmissionStatus::Rejected->value])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $decision = AdmissionStatus::from($validated['decision']);
        $this->admissions->decide($admission, $request->user(), $decision, $validated['notes'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Application :status.', ['status' => strtolower($decision->label())])]);

        return to_route('people.admissions.show', $admission);
    }

    public function enroll(EnrollAdmissionRequest $request, AdmissionApplication $admission): RedirectResponse
    {
        $student = $this->admissions->enroll(
            $admission->load(['schoolClass', 'academicYear']),
            Section::query()->findOrFail($request->integer('section_id')),
            $request->filled('admission_no') ? $request->string('admission_no')->toString() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name enrolled as :no.', ['name' => $student->full_name, 'no' => $student->admission_no])]);

        return to_route('people.students.show', $student);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'years' => $this->options->years(),
            'classes' => $this->options->classes(),
            'genders' => Gender::options(),
            'relationships' => GuardianRelationship::options(),
        ];
    }
}
