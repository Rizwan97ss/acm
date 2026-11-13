<?php

namespace App\Services\Dashboard\Widgets;

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\Dashboard\DashboardWidget;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;

class OverviewStatsWidget implements DashboardWidget
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function key(): string
    {
        return 'stats';
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->hasAnyPermission(['users.view', 'roles.view', 'schools.view']);
    }

    public function isDeferred(): bool
    {
        return false;
    }

    /**
     * @return list<array{key: string, label: string, value: int, hint: string|null}>
     */
    public function data(User $user): array
    {
        $schoolId = $this->currentSchool->id();
        $stats = [];

        if ($user->hasPermission('users.view')) {
            $users = User::query()->when($schoolId, fn (Builder $query, int $id) => $query->where('school_id', $id));
            $total = (clone $users)->count();
            $active = (clone $users)->where('is_active', true)->count();
            $recent = (clone $users)->where('last_login_at', '>=', now()->subDays(7))->count();

            $stats[] = ['key' => 'users', 'label' => 'Users', 'value' => $total, 'hint' => "{$active} active"];
            $stats[] = ['key' => 'active_week', 'label' => 'Signed in this week', 'value' => $recent, 'hint' => $total > 0 ? round($recent / $total * 100).'% of users' : null];
        }

        if ($user->hasPermission('roles.view')) {
            $stats[] = ['key' => 'roles', 'label' => 'Roles', 'value' => Role::query()->availableTo($schoolId)->count(), 'hint' => null];
        }

        if ($user->hasPermission('schools.view') && $user->isPlatformUser() && $schoolId === null) {
            $schools = School::query()->count();
            $active = School::query()->active()->count();

            $stats[] = ['key' => 'schools', 'label' => 'Schools & branches', 'value' => $schools, 'hint' => "{$active} active"];
        }

        return $stats;
    }
}
