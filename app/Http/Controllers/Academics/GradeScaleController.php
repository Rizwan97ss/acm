<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\GradeScaleRequest;
use App\Models\GradeBand;
use App\Models\GradeScale;
use App\Services\Academics\GradeScaleService;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GradeScaleController extends Controller
{
    public function __construct(
        private GradeScaleService $scales,
        private CurrentSchool $currentSchool,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', GradeScale::class);

        $this->scales->defaultFor((int) $this->currentSchool->id());

        return Inertia::render('academics/grade-scales/index', [
            'scales' => GradeScale::query()->with('bands')->orderByDesc('is_default')->orderBy('name')->get()
                ->map(fn (GradeScale $scale): array => [
                    ...$this->present($scale),
                    'can' => [
                        'update' => $request->user()->can('update', $scale),
                        'delete' => $request->user()->can('delete', $scale) && ! $scale->is_default,
                    ],
                ]),
            'can' => ['create' => $request->user()->can('create', GradeScale::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', GradeScale::class);

        return Inertia::render('academics/grade-scales/form', [
            'scale' => null,
            'template' => $this->present($this->scales->defaultFor((int) $this->currentSchool->id())->load('bands')),
        ]);
    }

    public function store(GradeScaleRequest $request): RedirectResponse
    {
        $this->scales->save(new GradeScale(['school_id' => $this->currentSchool->id()]), $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Grading scale created.')]);

        return to_route('academics.grade-scales.index');
    }

    public function edit(GradeScale $gradeScale): Response
    {
        Gate::authorize('update', $gradeScale);

        return Inertia::render('academics/grade-scales/form', [
            'scale' => $this->present($gradeScale->load('bands')),
            'template' => null,
        ]);
    }

    public function update(GradeScaleRequest $request, GradeScale $gradeScale): RedirectResponse
    {
        $this->scales->save($gradeScale, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Grading scale updated.')]);

        return to_route('academics.grade-scales.index');
    }

    public function destroy(GradeScale $gradeScale): RedirectResponse
    {
        Gate::authorize('delete', $gradeScale);

        $this->scales->delete($gradeScale);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Grading scale deleted.')]);

        return to_route('academics.grade-scales.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(GradeScale $scale): array
    {
        return [
            'id' => $scale->id,
            'name' => $scale->name,
            'is_default' => $scale->is_default,
            'bands' => $scale->bands->map(fn (GradeBand $band): array => [
                'grade' => $band->grade,
                'min_percentage' => (float) $band->min_percentage,
                'grade_point' => $band->grade_point !== null ? (float) $band->grade_point : null,
                'remark' => $band->remark,
                'is_passing' => $band->is_passing,
            ])->values(),
        ];
    }
}
