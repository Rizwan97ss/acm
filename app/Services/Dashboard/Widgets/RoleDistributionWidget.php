<?php

namespace App\Services\Dashboard\Widgets;

use App\Models\Role;
use App\Models\User;
use App\Services\Dashboard\DashboardWidget;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;

class RoleDistributionWidget implements DashboardWidget
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function key(): string
    {
        return 'roleDistribution';
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->hasPermission('roles.view') && $user->hasPermission('users.view');
    }

    public function isDeferred(): bool
    {
        return true;
    }

    /**
     * @return list<array{name: string, count: int}>
     */
    public function data(User $user): array
    {
        $schoolId = $this->currentSchool->id();

        $roles = Role::query()
            ->availableTo($schoolId)
            ->withCount(['users' => fn (Builder $query) => $query->when($schoolId, fn (Builder $query, int $id) => $query->where('users.school_id', $id))])
            ->orderByDesc('users_count')
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->filter(fn (Role $role): bool => $role->users_count > 0)
            ->map(fn (Role $role): array => ['name' => $role->name, 'count' => (int) $role->users_count])
            ->all();

        return array_values($roles);
    }
}
