<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only audit trail. Tenant isolation comes from AuditLog's BelongsToSchool scope.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($filters['event'] ?? null, fn (Builder $query, string $event) => $query->where('event', 'like', addcslashes($event, '%_\\').'%'))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $escaped = addcslashes($search, '%_\\');
                $query->whereHas('user', fn (Builder $users) => $users->where(
                    fn (Builder $users) => $users->where('name', 'like', "%{$escaped}%")->orWhere('email', 'like', "%{$escaped}%"),
                ));
            })
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<', now()->parse($to)->addDay()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/audit-logs/index', [
            'logs' => AuditLogResource::collection($logs),
            'filters' => $filters,
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event'),
        ]);
    }
}
