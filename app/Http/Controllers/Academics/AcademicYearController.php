<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\AcademicYearRequest;
use App\Http\Resources\AcademicYearResource;
use App\Models\AcademicYear;
use App\Services\Academics\AcademicYearService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function __construct(private AcademicYearService $years) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AcademicYear::class);

        $years = AcademicYear::query()
            ->with('terms')
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('academics/academic-years/index', [
            'years' => AcademicYearResource::collection($years)->resolve($request),
            'can' => ['create' => $request->user()->can('create', AcademicYear::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', AcademicYear::class);

        return Inertia::render('academics/academic-years/create');
    }

    public function store(AcademicYearRequest $request): RedirectResponse
    {
        $this->years->save(new AcademicYear, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Academic year created.')]);

        return to_route('academics.academic-years.index');
    }

    public function edit(Request $request, AcademicYear $academicYear): Response
    {
        Gate::authorize('update', $academicYear);

        return Inertia::render('academics/academic-years/edit', [
            'year' => (new AcademicYearResource($academicYear->load('terms')))->resolve($request),
        ]);
    }

    public function update(AcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $this->years->save($academicYear, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Academic year updated.')]);

        return to_route('academics.academic-years.index');
    }

    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        Gate::authorize('delete', $academicYear);

        $this->years->delete($academicYear);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Academic year deleted.')]);

        return to_route('academics.academic-years.index');
    }

    public function makeCurrent(AcademicYear $academicYear): RedirectResponse
    {
        Gate::authorize('update', $academicYear);

        $this->years->makeCurrent($academicYear);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':year is now the current academic year.', ['year' => $academicYear->name])]);

        return to_route('academics.academic-years.index');
    }
}
