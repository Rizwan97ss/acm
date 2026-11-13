<?php

namespace App\Services\Dashboard;

use App\Models\User;

/**
 * A dashboard widget. Modules contribute widgets by implementing this contract and
 * registering the class in DashboardService::WIDGETS; each widget decides who may see it.
 */
interface DashboardWidget
{
    /**
     * The prop name the frontend renders this widget from.
     */
    public function key(): string;

    public function isVisibleTo(User $user): bool;

    /**
     * Expensive widgets are deferred so the page renders immediately.
     */
    public function isDeferred(): bool;

    public function data(User $user): mixed;
}
