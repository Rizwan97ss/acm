<?php

namespace App\Http\Controllers\Academics;

use App\Enums\AssignmentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\AssignmentRequest;
use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Academics\AcademicOptions;
use App\Services\Academics\AssignmentService;
use App\Services\Academics\AttendanceService;
use App\Services\Academics\TeachingScope;
use App\Services\People\PortalScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Homework and assignments. Teachers work with the sections they teach; students
 * submit answers; guardians follow their children's work.
 */
class AssignmentController extends Controller
{
    public function __construct(
        private TeachingScope $scope,
        private PortalScope $portalScope,
        private AcademicOptions $options,
        private AssignmentService $assignments,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Assignment::class);

        $filters = $request->validate([
            'section_id' => ['nullable', 'integer'],
            'when' => ['nullable', 'in:upcoming,past'],
        ]);

        $user = $request->user();
        $year = AcademicYear::query()->current()->first();
        $sectionIds = $this->visibleSectionIds($user, $year);
        $studentIds = $this->portalScope->isPortalUser($user) ? $this->portalScope->studentIds($user) : [];

        $assignments = Assignment::query()
            ->with(['section.schoolClass:id,name', 'subject:id,name,color', 'author:id,name'])
            ->withCount('submissions')
            ->when($year, fn (Builder $query, AcademicYear $year) => $query->where('academic_year_id', $year->id))
            ->when($sectionIds !== null, fn (Builder $query) => $query->whereIn('section_id', $sectionIds))
            ->when($filters['section_id'] ?? null, fn (Builder $query, int $sectionId) => $query->where('section_id', $sectionId))
            ->when(($filters['when'] ?? 'upcoming') === 'upcoming', fn (Builder $query) => $query->whereDate('due_on', '>=', now()->toDateString()), fn (Builder $query) => $query->whereDate('due_on', '<', now()->toDateString()))
            ->orderBy('due_on', ($filters['when'] ?? 'upcoming') === 'upcoming' ? 'asc' : 'desc')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        $submitted = AssignmentSubmission::query()
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->whereIn('student_id', $studentIds)
            ->get(['assignment_id', 'student_id', 'graded_at'])
            ->groupBy('assignment_id');

        return Inertia::render('academics/assignments/index', [
            'assignments' => [
                'data' => $assignments->getCollection()->map(fn (Assignment $assignment): array => [
                    ...$this->present($assignment),
                    'submissions_count' => (int) $assignment->getAttribute('submissions_count'),
                    'my_status' => $studentIds === [] ? null : ($submitted->has($assignment->id)
                        ? ($submitted[$assignment->id]->every(fn ($submission) => $submission->graded_at !== null) ? 'graded' : 'submitted')
                        : 'pending'),
                ])->values(),
                'meta' => [
                    'current_page' => $assignments->currentPage(),
                    'from' => $assignments->firstItem(),
                    'last_page' => $assignments->lastPage(),
                    'per_page' => $assignments->perPage(),
                    'to' => $assignments->lastItem(),
                    'total' => $assignments->total(),
                    'links' => $assignments->linkCollection()->all(),
                ],
            ],
            'sections' => $this->sectionOptions($sectionIds),
            'filters' => ['section_id' => $filters['section_id'] ?? null, 'when' => $filters['when'] ?? 'upcoming'],
            'can' => ['create' => $user->can('create', Assignment::class) && ! $this->portalScope->isPortalUser($user)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Assignment::class);

        return Inertia::render('academics/assignments/form', [...$this->formOptions($request), 'assignment' => null]);
    }

    public function store(AssignmentRequest $request): RedirectResponse
    {
        $year = AcademicYear::query()->current()->firstOrFail();
        $section = $this->authorizedSection($request, $year);

        $assignment = Assignment::query()->create([
            ...$request->validated(),
            'school_id' => $section->school_id,
            'academic_year_id' => $year->id,
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment set for :section.', ['section' => $section->fullName()])]);

        return to_route('academics.assignments.show', $assignment);
    }

    public function show(Request $request, Assignment $assignment, AttendanceService $attendance): Response
    {
        $user = $request->user();
        $this->authorizeView($user, $assignment);
        $assignment->load(['section.schoolClass', 'subject:id,name,color', 'author:id,name']);

        $isPortalUser = $this->portalScope->isPortalUser($user);
        $ownStudentIds = $isPortalUser ? $this->portalScope->studentIds($user) : [];
        $canGrade = ! $isPortalUser && $user->can('assignments.edit')
            && $this->scope->canAccessSection($user, $assignment->section, $this->year($assignment), 'assignments');

        $submissions = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->when($isPortalUser, fn (Builder $query) => $query->whereIn('student_id', $ownStudentIds))
            ->get()
            ->keyBy('student_id');

        $roster = $isPortalUser
            ? Student::query()->whereIn('id', $ownStudentIds)->get()
                ->filter(fn (Student $student): bool => Enrollment::query()->where('student_id', $student->id)->where('section_id', $assignment->section_id)->exists())
                ->values()
            : $attendance->roster($assignment->section, $this->year($assignment))->map(fn (Enrollment $enrollment) => $enrollment->student);

        return Inertia::render('academics/assignments/show', [
            'assignment' => $this->present($assignment),
            'students' => $roster->map(fn (Student $student): array => [
                'id' => $student->id,
                'name' => $student->full_name,
                'submission' => ($submission = $submissions->get($student->id)) ? [
                    'id' => $submission->id,
                    'content' => $submission->content,
                    'submitted_at' => $submission->submitted_at->toIso8601String(),
                    'late' => $submission->submitted_at->startOfDay()->greaterThan($assignment->due_on),
                    'marks' => $submission->marks !== null ? (float) $submission->marks : null,
                    'feedback' => $submission->feedback,
                    'graded' => $submission->graded_at !== null,
                ] : null,
            ])->values(),
            'can' => [
                'update' => ! $isPortalUser && $this->canManage($user, $assignment),
                'grade' => $canGrade,
                'submit' => $user->student()->exists() && ! $user->employee()->exists(),
            ],
        ]);
    }

    public function edit(Request $request, Assignment $assignment): Response
    {
        abort_unless($this->canManage($request->user(), $assignment), 403);

        return Inertia::render('academics/assignments/form', [
            ...$this->formOptions($request),
            'assignment' => $this->present($assignment->load(['section.schoolClass', 'subject'])),
        ]);
    }

    public function update(AssignmentRequest $request, Assignment $assignment): RedirectResponse
    {
        abort_unless($this->canManage($request->user(), $assignment), 403);
        $this->authorizedSection($request, $this->year($assignment));

        $assignment->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment updated.')]);

        return to_route('academics.assignments.show', $assignment);
    }

    public function destroy(Request $request, Assignment $assignment): RedirectResponse
    {
        abort_unless($request->user()->can('delete', $assignment) && $this->canManage($request->user(), $assignment), 403);

        $assignment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment deleted.')]);

        return to_route('academics.assignments.index');
    }

    /**
     * A student submits their answer.
     */
    public function submit(Request $request, Assignment $assignment): RedirectResponse
    {
        $student = $request->user()->student;
        abort_if($student === null || $request->user()->employee()->exists(), 403);

        $validated = $request->validate(['content' => ['required', 'string', 'max:20000']]);
        $this->assignments->submit($student, $assignment, $validated['content']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your work has been submitted.')]);

        return to_route('academics.assignments.show', $assignment);
    }

    public function grade(Request $request, Assignment $assignment, AssignmentSubmission $submission): RedirectResponse
    {
        abort_unless($submission->assignment_id === $assignment->id, 404);
        abort_unless($request->user()->can('assignments.edit') && $this->scope->canAccessSection($request->user(), $assignment->section, $this->year($assignment), 'assignments'), 403);

        $validated = $request->validate([
            'marks' => ['nullable', 'numeric', 'min:0'],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->assignments->grade($request->user(), $submission, isset($validated['marks']) ? (float) $validated['marks'] : null, $validated['feedback'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Feedback saved.')]);

        return to_route('academics.assignments.show', $assignment);
    }

    /**
     * Teachers set work only for sections they teach, and only for subjects of that class.
     */
    private function authorizedSection(AssignmentRequest $request, AcademicYear $year): Section
    {
        $section = Section::query()->with('schoolClass')->findOrFail($request->integer('section_id'));

        abort_unless($this->scope->canAccessSection($request->user(), $section, $year, 'assignments'), 403);

        if (! $section->schoolClass->subjects()->whereKey($request->integer('subject_id'))->exists()) {
            throw ValidationException::withMessages(['subject_id' => __('This subject is not taught in :class.', ['class' => $section->schoolClass->name])]);
        }

        return $section;
    }

    private function authorizeView(User $user, Assignment $assignment): void
    {
        abort_unless($user->can('view', $assignment), 403);

        $sectionIds = $this->visibleSectionIds($user, $this->year($assignment));
        abort_unless($sectionIds === null || in_array($assignment->section_id, $sectionIds, true), 403);
    }

    private function canManage(User $user, Assignment $assignment): bool
    {
        return $user->can('update', $assignment)
            && ($assignment->created_by === $user->id || $this->scope->supervises($user, 'assignments'));
    }

    /**
     * @return list<int>|null null = all sections
     */
    private function visibleSectionIds(User $user, ?AcademicYear $year): ?array
    {
        if ($this->portalScope->isPortalUser($user)) {
            return $this->portalScope->sectionIds($user, $year) ?? [];
        }

        return $year === null ? [] : $this->scope->sectionIds($user, $year, 'assignments');
    }

    private function year(Assignment $assignment): AcademicYear
    {
        return AcademicYear::query()->findOrFail($assignment->academic_year_id);
    }

    /**
     * @param  list<int>|null  $sectionIds
     * @return Collection<int, array{id: int, name: string, school_class_id: int, room: string|null, capacity: int|null}>
     */
    private function sectionOptions(?array $sectionIds): Collection
    {
        $sections = $this->options->sections();

        return ($sectionIds === null ? $sections : $sections->whereIn('id', $sectionIds))->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        $year = AcademicYear::query()->current()->first();
        $sectionIds = $year === null ? [] : $this->scope->sectionIds($request->user(), $year, 'assignments');

        return [
            'sections' => $this->sectionOptions($sectionIds),
            'subjectsByClass' => Subject::query()
                ->where('is_active', true)
                ->with('classes:id')
                ->orderBy('name')
                ->get()
                ->flatMap(fn (Subject $subject) => $subject->classes->map(fn ($class): array => [
                    'school_class_id' => $class->id,
                    'id' => $subject->id,
                    'name' => $subject->name,
                ]))
                ->groupBy('school_class_id'),
            'types' => AssignmentType::options(),
            'hasCurrentYear' => $year !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Assignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'description' => $assignment->description,
            'type' => $assignment->type->value,
            'type_label' => $assignment->type->label(),
            'due_on' => $assignment->due_on->toDateString(),
            'max_marks' => $assignment->max_marks !== null ? (float) $assignment->max_marks : null,
            'section_id' => $assignment->section_id,
            'section' => $assignment->relationLoaded('section') ? $assignment->section->fullName() : null,
            'subject_id' => $assignment->subject_id,
            'subject' => $assignment->relationLoaded('subject') ? $assignment->subject->only(['id', 'name', 'color']) : null,
            'author' => $assignment->relationLoaded('author') ? $assignment->author?->name : null,
        ];
    }
}
