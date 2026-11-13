<?php

namespace App\Services\Dashboard;

use App\Models\User;
use App\Services\Dashboard\Widgets\LoginActivityWidget;
use App\Services\Dashboard\Widgets\OverviewStatsWidget;
use App\Services\Dashboard\Widgets\RecentActivityWidget;
use App\Services\Dashboard\Widgets\RoleDistributionWidget;
use App\Services\Dashboard\Widgets\TodaysLessonsWidget;
use Illuminate\Contracts\Container\Container;
use Inertia\Inertia;

class DashboardService
{
    /**
     * @var list<class-string<DashboardWidget>>
     */
    private const array WIDGETS = [
        OverviewStatsWidget::class,
        TodaysLessonsWidget::class,
        LoginActivityWidget::class,
        RoleDistributionWidget::class,
        RecentActivityWidget::class,
    ];

    public function __construct(private Container $container) {}

    /**
     * Build Inertia props for every widget the user may see.
     *
     * @return array<string, mixed>
     */
    public function propsFor(User $user): array
    {
        $props = [];

        foreach (self::WIDGETS as $class) {
            $widget = $this->container->make($class);

            if (! $widget->isVisibleTo($user)) {
                continue;
            }

            $props[$widget->key()] = $widget->isDeferred()
                ? Inertia::defer(fn () => $widget->data($user), 'widgets')
                : $widget->data($user);
        }

        return $props;
    }
}
