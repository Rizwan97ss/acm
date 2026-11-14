<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\ActivityLogReader;
use App\Services\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Request-level activity (every controller action) from the activity log files.
 * School-bound viewers only see entries recorded in their own school.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request, ActivityLogReader $reader, CurrentSchool $currentSchool): Response
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'user_id' => ['nullable', 'integer'],
            'level' => ['nullable', 'in:info,warning,error'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $dates = $reader->dates();
        $date = $filters['date'] ?? $dates[0] ?? now()->format('Y-m-d');

        $entries = $reader->entries(CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay(), [
            'school_id' => $request->user()->school_id !== null ? $currentSchool->id() : null,
            'user_id' => isset($filters['user_id']) ? (int) $filters['user_id'] : null,
            'level' => $filters['level'] ?? null,
            'search' => $filters['search'] ?? null,
        ]);

        $userIds = array_values(array_unique(array_filter(array_column($entries, 'user_id'))));
        $users = User::query()->whereIn('id', $userIds)->get(['id', 'name', 'email'])->keyBy('id');

        return Inertia::render('admin/activity-logs', [
            'entries' => array_map(fn (array $entry): array => [
                ...$entry,
                'user' => $entry['user_id'] !== null ? $users->get($entry['user_id'])?->only(['id', 'name', 'email']) : null,
            ], $entries),
            'dates' => $dates,
            'filters' => [...$filters, 'date' => $date],
        ]);
    }
}
