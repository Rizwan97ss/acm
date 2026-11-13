<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\TimetableEntryRequest;
use App\Models\TimetableEntry;
use App\Services\Academics\TimetableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TimetableEntryController extends Controller
{
    public function __construct(private TimetableService $timetable) {}

    public function store(TimetableEntryRequest $request): RedirectResponse
    {
        $entry = $this->timetable->place(new TimetableEntry, $request->payload());

        return $this->backToGrid($entry, __('Lesson added.'));
    }

    public function update(TimetableEntryRequest $request, TimetableEntry $entry): RedirectResponse
    {
        $this->timetable->place($entry, $request->payload());

        return $this->backToGrid($entry, __('Lesson updated.'));
    }

    public function destroy(TimetableEntry $entry): RedirectResponse
    {
        Gate::authorize('delete', $entry);

        $entry->delete();

        return $this->backToGrid($entry, __('Lesson removed.'));
    }

    private function backToGrid(TimetableEntry $entry, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('academics.timetable.index', [
            'academic_year_id' => $entry->academic_year_id,
            'section_id' => $entry->section_id,
        ]);
    }
}
