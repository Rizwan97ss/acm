<?php

namespace App\Http\Controllers\Communication;

use App\Enums\Audience;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\SchoolEventRequest;
use App\Models\SchoolClass;
use App\Models\SchoolEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * School calendar: a month grid plus what is coming up next.
 */
class EventController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('events.view');

        $user = $request->user();
        $manages = $user->can('events.create');
        $validated = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = CarbonImmutable::createFromFormat('Y-m-d', ($validated['month'] ?? now()->format('Y-m')).'-01')->startOfDay();

        $base = fn (): Builder => SchoolEvent::query()
            ->with('schoolClass:id,name')
            ->when(! $manages, fn (Builder $query) => $query->visibleTo($user));

        $present = fn (SchoolEvent $event): array => [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'location' => $event->location,
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at->toIso8601String(),
            'all_day' => $event->all_day,
            'audience' => $event->audience->value,
            'audience_label' => $event->audience->label(),
            'school_class_id' => $event->school_class_id,
            'school_class' => $event->schoolClass?->name,
        ];

        return Inertia::render('communication/events/index', [
            'month' => $month->format('Y-m'),
            'events' => $base()
                ->where('starts_at', '<=', $month->endOfMonth())
                ->where('ends_at', '>=', $month)
                ->orderBy('starts_at')
                ->get()
                ->map($present)
                ->values(),
            'upcoming' => $base()
                ->where('ends_at', '>=', now())
                ->orderBy('starts_at')
                ->limit(6)
                ->get()
                ->map($present)
                ->values(),
            'audiences' => Audience::options(),
            'classes' => $manages ? SchoolClass::query()->orderBy('name')->get(['id', 'name']) : [],
            'can' => [
                'create' => $manages,
                'edit' => $user->can('events.edit'),
                'delete' => $user->can('events.delete'),
            ],
        ]);
    }

    public function store(SchoolEventRequest $request): RedirectResponse
    {
        $event = SchoolEvent::query()->create([...$request->payload(), 'created_by' => $request->user()->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event added.')]);

        return to_route('communication.events.index', ['month' => $event->starts_at->format('Y-m')]);
    }

    public function update(SchoolEventRequest $request, SchoolEvent $event): RedirectResponse
    {
        $event->update($request->payload());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event updated.')]);

        return to_route('communication.events.index', ['month' => $event->starts_at->format('Y-m')]);
    }

    public function destroy(SchoolEvent $event): RedirectResponse
    {
        Gate::authorize('events.delete');

        $month = $event->starts_at->format('Y-m');
        $event->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event deleted.')]);

        return to_route('communication.events.index', ['month' => $month]);
    }
}
