<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\EventResource;
use App\Models\SchoolEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Calendar events addressed to the token owner, overlapping the requested range
 * (default: the next 30 days).
 */
class EventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('events.view');

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from'])->startOfDay() : now()->startOfDay();
        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to'])->endOfDay() : $from->addDays(30)->endOfDay();

        abort_if($from->diffInDays($to) > 366, 422, __('The date range may not exceed one year.'));

        $events = SchoolEvent::query()
            ->with('schoolClass:id,name')
            ->visibleTo($request->user())
            ->where('starts_at', '<=', $to)
            ->where('ends_at', '>=', $from)
            ->orderBy('starts_at')
            ->limit(500)
            ->get();

        return EventResource::collection($events);
    }
}
