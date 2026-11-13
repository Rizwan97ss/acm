<?php

namespace App\Services\Dashboard\Widgets;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Dashboard\DashboardWidget;
use Carbon\CarbonPeriod;

/**
 * Daily sign-ins and failed attempts for the last 14 days.
 */
class LoginActivityWidget implements DashboardWidget
{
    private const int DAYS = 14;

    public function key(): string
    {
        return 'loginActivity';
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
     * @return list<array{date: string, logins: int, failed: int}>
     */
    public function data(User $user): array
    {
        $start = now()->subDays(self::DAYS - 1)->startOfDay();

        $counts = AuditLog::query()
            ->whereIn('event', ['auth.login', 'auth.failed'])
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, event, COUNT(*) as total')
            ->groupBy('day', 'event')
            ->get()
            ->groupBy('day');

        $series = [];

        foreach (CarbonPeriod::create($start, '1 day', now()->startOfDay()) as $day) {
            $rows = $counts->get($day->toDateString(), collect());

            $series[] = [
                'date' => $day->toDateString(),
                'logins' => (int) $rows->firstWhere('event', 'auth.login')?->getAttribute('total'),
                'failed' => (int) $rows->firstWhere('event', 'auth.failed')?->getAttribute('total'),
            ];
        }

        return $series;
    }
}
