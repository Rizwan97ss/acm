<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Dashboard\DashboardWidget;
use App\Services\People\PortalScope;
use App\Services\Tenancy\CurrentSchool;

/**
 * Fee collection at a glance for finance staff. Amounts are in cents.
 */
class FinanceSnapshotWidget implements DashboardWidget
{
    public function __construct(
        private CurrentSchool $currentSchool,
        private PortalScope $portalScope,
    ) {}

    public function key(): string
    {
        return 'finance';
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->currentSchool->id() !== null
            && $user->hasPermission('invoices.view')
            && $user->hasPermission('payments.view')
            && ! $this->portalScope->isPortalUser($user);
    }

    public function isDeferred(): bool
    {
        return true;
    }

    /**
     * @return array{collected_this_month: int, outstanding: int, overdue_count: int, pending_expenses: int}
     */
    public function data(User $user): array
    {
        $open = Invoice::query()->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial]);

        return [
            'collected_this_month' => (int) Payment::query()->whereNull('reversed_at')->whereDate('paid_on', '>=', now()->startOfMonth()->toDateString())->sum('amount'),
            'outstanding' => (int) (clone $open)->toBase()->selectRaw('sum(total - paid) as due')->value('due'),
            'overdue_count' => (clone $open)->whereDate('due_on', '<', now()->toDateString())->count(),
            'pending_expenses' => $user->hasPermission('expenses.view') ? (int) Expense::query()->where('status', ExpenseStatus::Pending)->sum('amount') : 0,
        ];
    }
}
