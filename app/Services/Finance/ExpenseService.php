<?php

namespace App\Services\Finance;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\People\ReferenceNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Expenses are submitted as pending and only reach the ledger once approved
 * (Dr Operating expenses / Cr Cash or Bank). Nobody approves their own expense.
 */
class ExpenseService
{
    private const array DEFAULT_CATEGORIES = ['Utilities', 'Supplies & stationery', 'Maintenance', 'Transport', 'Events', 'Other'];

    public function __construct(
        private LedgerService $ledger,
        private ReferenceNumberGenerator $numbers,
    ) {}

    public function ensureCategories(int $schoolId): void
    {
        if (ExpenseCategory::query()->exists()) {
            return;
        }

        foreach (self::DEFAULT_CATEGORIES as $name) {
            ExpenseCategory::query()->create(['school_id' => $schoolId, 'name' => $name]);
        }
    }

    /**
     * @param  array<string, mixed>  $data  validated attributes with `amount` already in cents
     */
    public function submit(User $actor, int $schoolId, array $data): Expense
    {
        return $this->numbers->withNext('expense', $schoolId, function (string $number) use ($actor, $schoolId, $data): Expense {
            $expense = new Expense([...$data, 'school_id' => $schoolId, 'number' => $number, 'submitted_by' => $actor->id]);
            $expense->forceFill(['status' => ExpenseStatus::Pending])->save();

            return $expense;
        });
    }

    public function review(User $reviewer, Expense $expense, ExpenseStatus $decision, ?string $note): void
    {
        if ($expense->status !== ExpenseStatus::Pending) {
            throw ValidationException::withMessages(['decision' => __('Only pending expenses can be reviewed.')]);
        }

        if ($expense->submitted_by === $reviewer->id && ! $reviewer->isSuperAdmin()) {
            throw ValidationException::withMessages(['decision' => __('You cannot approve an expense you submitted.')]);
        }

        DB::transaction(function () use ($reviewer, $expense, $decision, $note): void {
            $expense->forceFill([
                'status' => $decision,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            if ($decision === ExpenseStatus::Approved) {
                $this->ledger->post($expense->school_id, $expense->spent_on, "Expense {$expense->number}: {$expense->payee}", $expense, [
                    [LedgerService::OPERATING_EXPENSES, $expense->amount, 0],
                    [$expense->method->accountCode(), 0, $expense->amount],
                ], $reviewer);
            }
        });
    }
}
