<?php

namespace App\Services\Dashboard\Widgets;

use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Dashboard\DashboardWidget;

class RecentActivityWidget implements DashboardWidget
{
    public function key(): string
    {
        return 'recentActivity';
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->hasPermission('audit_logs.view');
    }

    public function isDeferred(): bool
    {
        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function data(User $user): array
    {
        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return AuditLogResource::collection($logs)->resolve(request());
    }
}
