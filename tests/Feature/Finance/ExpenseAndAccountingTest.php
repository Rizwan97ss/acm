<?php

use App\Enums\ExpenseStatus;
use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\School;
use App\Services\Finance\LedgerService;

test('expenses reach the ledger only when approved by someone other than the submitter', function () {
    $school = School::factory()->create();
    $clerk = userWithPermissions(['expenses.view', 'expenses.create', 'expenses.approve'], $school);
    $principal = userWithPermissions(['expenses.view', 'expenses.approve'], $school);
    $category = ExpenseCategory::query()->create(['school_id' => $school->id, 'name' => 'Utilities']);

    $this->actingAs($clerk)->post(route('finance.expenses.store'), [
        'expense_category_id' => $category->id, 'payee' => 'Power Co', 'description' => 'Electricity',
        'amount' => '250.75', 'spent_on' => now()->toDateString(), 'method' => 'bank_transfer',
    ])->assertSessionHasNoErrors();

    $expense = Expense::query()->withoutGlobalScopes()->sole();
    expect($expense)->amount->toBe(25075)->status->toBe(ExpenseStatus::Pending)
        ->and(JournalEntry::query()->withoutGlobalScopes()->count())->toBe(0);

    $this->actingAs($clerk)->post(route('finance.expenses.review', $expense), ['decision' => 'approved'])->assertSessionHasErrors('decision');
    $this->actingAs($principal)->post(route('finance.expenses.review', $expense), ['decision' => 'approved'])->assertSessionHasNoErrors();

    $balances = collect(app(LedgerService::class)->balances($school->id))->keyBy('code');
    expect($expense->fresh()->status)->toBe(ExpenseStatus::Approved)
        ->and($balances[LedgerService::OPERATING_EXPENSES]['balance'])->toBe(25075)
        ->and($balances[LedgerService::BANK]['balance'])->toBe(-25075);
});

test('rejected expenses are never posted', function () {
    $school = School::factory()->create();
    $expense = Expense::factory()->create(['school_id' => $school->id, 'status' => 'pending']);
    $expense->forceFill(['status' => ExpenseStatus::Pending])->save();

    $this->actingAs(userWithPermissions(['expenses.view', 'expenses.approve'], $school))
        ->post(route('finance.expenses.review', $expense), ['decision' => 'rejected', 'note' => 'No receipt'])
        ->assertSessionHasNoErrors();

    expect($expense->fresh()->status)->toBe(ExpenseStatus::Rejected)
        ->and(JournalEntry::query()->withoutGlobalScopes()->count())->toBe(0);
});

test('manual journal entries must balance', function () {
    $school = School::factory()->create();
    app(LedgerService::class)->ensureAccounts($school->id);
    $accountant = userWithPermissions(['accounting.view', 'accounting.create'], $school);
    $bank = Account::query()->withoutGlobalScopes()->where('code', LedgerService::BANK)->sole();
    $income = Account::query()->withoutGlobalScopes()->where('code', LedgerService::OTHER_INCOME)->sole();
    $line = fn (Account $account, string $debit, string $credit) => ['account_id' => $account->id, 'debit' => $debit, 'credit' => $credit];

    $this->actingAs($accountant)->post(route('finance.accounting.journal.store'), [
        'entry_date' => now()->toDateString(), 'memo' => 'Donation',
        'lines' => [$line($bank, '500', ''), $line($income, '', '400')],
    ])->assertSessionHasErrors('lines');

    $this->actingAs($accountant)->post(route('finance.accounting.journal.store'), [
        'entry_date' => now()->toDateString(), 'memo' => 'Donation',
        'lines' => [$line($bank, '500', ''), $line($income, '', '500')],
    ])->assertSessionHasNoErrors();

    $balances = collect(app(LedgerService::class)->balances($school->id))->keyBy('code');
    expect($balances[LedgerService::OTHER_INCOME]['balance'])->toBe(50000);
});

test('the accounting overview requires accounting.view', function () {
    $school = School::factory()->create();

    $this->actingAs(userWithPermissions(['invoices.view'], $school))->get(route('finance.accounting.overview'))->assertForbidden();
    $this->actingAs(userWithPermissions(['accounting.view'], $school))->get(route('finance.accounting.overview'))->assertOk();
});
