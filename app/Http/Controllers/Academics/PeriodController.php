<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\PeriodsRequest;
use App\Models\Period;
use App\Services\Academics\PeriodService;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The school's bell schedule, edited as one list.
 */
class PeriodController extends Controller
{
    public function edit(Request $request): Response
    {
        Gate::authorize('timetables.view');

        return Inertia::render('academics/periods/edit', [
            'periods' => Period::query()->orderBy('position')->get()->map(fn (Period $period): array => [
                'id' => $period->id,
                'name' => $period->name,
                'starts_at' => substr($period->starts_at, 0, 5),
                'ends_at' => substr($period->ends_at, 0, 5),
                'is_break' => $period->is_break,
            ]),
            'canEdit' => $request->user()->can('timetables.edit'),
        ]);
    }

    public function update(PeriodsRequest $request, PeriodService $periods, CurrentSchool $currentSchool): RedirectResponse
    {
        $periods->sync((int) $currentSchool->id(), $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bell schedule saved.')]);

        return to_route('academics.periods.edit');
    }
}
