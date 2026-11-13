<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\SchoolClassRequest;
use App\Http\Resources\SchoolClassResource;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\Academics\SchoolClassService;
use App\Services\Academics\TeacherDirectory;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SchoolClassController extends Controller
{
    public function __construct(
        private SchoolClassService $classes,
        private TeacherDirectory $teachers,
        private CurrentSchool $currentSchool,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SchoolClass::class);

        $classes = SchoolClass::query()
            ->with(['sections.classTeacher:id,name', 'subjects:id,name,code,color'])
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        return Inertia::render('academics/classes/index', [
            'classes' => SchoolClassResource::collection($classes)->resolve($request),
            'can' => ['create' => $request->user()->can('create', SchoolClass::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', SchoolClass::class);

        return Inertia::render('academics/classes/create', $this->formOptions());
    }

    public function store(SchoolClassRequest $request): RedirectResponse
    {
        $this->classes->save(new SchoolClass, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Class created.')]);

        return to_route('academics.classes.index');
    }

    public function edit(Request $request, SchoolClass $class): Response
    {
        Gate::authorize('update', $class);

        return Inertia::render('academics/classes/edit', [
            ...$this->formOptions(),
            'schoolClass' => (new SchoolClassResource($class->load(['sections', 'subjects'])))->resolve($request),
        ]);
    }

    public function update(SchoolClassRequest $request, SchoolClass $class): RedirectResponse
    {
        $this->classes->save($class, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Class updated.')]);

        return to_route('academics.classes.index');
    }

    public function destroy(SchoolClass $class): RedirectResponse
    {
        Gate::authorize('delete', $class);

        $this->classes->delete($class);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Class deleted.')]);

        return to_route('academics.classes.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'color']),
            'teachers' => $this->teachers->forSchool((int) $this->currentSchool->id()),
        ];
    }
}
