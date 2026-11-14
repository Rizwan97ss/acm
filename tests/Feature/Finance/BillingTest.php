<?php

use App\Enums\InvoiceStatus;
use App\Models\Enrollment;
use App\Models\FeeConcession;
use App\Models\FeeItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\LedgerService;
use App\Services\Rbac\PermissionRegistry;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

function accountant(array $setup, array $extra = []): User
{
    return userWithPermissions([...app(PermissionRegistry::class)->expand(['fees.*', 'invoices.*', 'payments.view', 'payments.create', 'accounting.view']), ...$extra], $setup['school']);
}

/**
 * Two fees (one for every class, one for the student's class) and a 25% concession for the student.
 *
 * @return array<string, mixed>
 */
function billedSchool(): array
{
    $setup = teachingSchool();
    $all = FeeItem::query()->create(['school_id' => $setup['school']->id, 'academic_year_id' => $setup['year']->id, 'name' => 'Tuition', 'amount' => 20000, 'frequency' => 'monthly']);
    $class = FeeItem::query()->create(['school_id' => $setup['school']->id, 'academic_year_id' => $setup['year']->id, 'school_class_id' => $setup['section']->school_class_id, 'name' => 'Lab fee', 'amount' => 4000, 'frequency' => 'monthly']);
    FeeConcession::query()->create(['school_id' => $setup['school']->id, 'student_id' => $setup['student']->id, 'percent' => 25, 'reason' => 'Sibling']);

    return [...$setup, 'fees' => [$all, $class]];
}

function generate(array $setup, User $actor): TestResponse
{
    return test()->actingAs($actor)->post(route('finance.invoices.store'), [
        'title' => 'September fees',
        'issued_on' => now()->toDateString(),
        'due_on' => now()->addDays(15)->toDateString(),
        'fee_item_ids' => collect($setup['fees'])->pluck('id')->all(),
        'class_ids' => [$setup['section']->school_class_id],
    ]);
}

function ledgerTotals(int $schoolId): array
{
    $balances = collect(app(LedgerService::class)->balances($schoolId));

    return ['debit' => $balances->sum('debit'), 'credit' => $balances->sum('credit'), 'by' => $balances->keyBy('code')];
}

test('generating invoices bills enrolled students with concessions and posts to the ledger once', function () {
    $setup = billedSchool();
    $sibling = Student::factory()->create(['school_id' => $setup['school']->id]);
    Enrollment::factory()->create(['student_id' => $sibling->id, 'section_id' => $setup['section']->id, 'academic_year_id' => $setup['year']->id]);
    $actor = accountant($setup);

    generate($setup, $actor)->assertSessionHasNoErrors()->assertRedirect(route('finance.invoices.index'));
    generate($setup, $actor)->assertSessionHasNoErrors();

    $invoice = Invoice::query()->withoutGlobalScopes()->where('student_id', $setup['student']->id)->sole();
    expect($invoice)->subtotal->toBe(24000)->discount->toBe(6000)->total->toBe(18000)->status->toBe(InvoiceStatus::Unpaid)
        ->and($invoice->number)->toStartWith('INV-')
        ->and(Invoice::query()->withoutGlobalScopes()->count())->toBe(2);

    $ledger = ledgerTotals($setup['school']->id);
    expect($ledger['debit'])->toBe($ledger['credit'])
        ->and($ledger['by'][LedgerService::FEES_RECEIVABLE]['balance'])->toBe(18000 + 24000)
        ->and($ledger['by'][LedgerService::FEE_INCOME]['balance'])->toBe(42000);
});

test('payments move invoices from unpaid to partly paid to paid and cannot overpay', function () {
    $setup = billedSchool();
    $actor = accountant($setup);
    generate($setup, $actor);
    $invoice = Invoice::query()->withoutGlobalScopes()->sole();
    $route = route('finance.payments.store', $invoice);

    $this->actingAs($actor)->post($route, ['amount' => '100.00', 'method' => 'cash', 'paid_on' => now()->toDateString()])->assertSessionHasNoErrors();
    expect($invoice->fresh())->paid->toBe(10000)->status->toBe(InvoiceStatus::Partial);

    $this->actingAs($actor)->post($route, ['amount' => '90.00', 'method' => 'bank_transfer', 'paid_on' => now()->toDateString()])->assertSessionHasErrors('amount');
    $this->actingAs($actor)->post($route, ['amount' => '80.00', 'method' => 'bank_transfer', 'paid_on' => now()->toDateString()])->assertSessionHasNoErrors();

    expect($invoice->fresh())->paid->toBe(18000)->status->toBe(InvoiceStatus::Paid)
        ->and(Payment::query()->withoutGlobalScopes()->orderBy('id')->pluck('receipt_no')->all())->each->toStartWith('RCP-');
    $ledger = ledgerTotals($setup['school']->id)['by'];
    expect($ledger[LedgerService::CASH]['balance'])->toBe(10000)
        ->and($ledger[LedgerService::BANK]['balance'])->toBe(8000)
        ->and($ledger[LedgerService::FEES_RECEIVABLE]['balance'])->toBe(0);
});

test('reversing a payment restores the balance and the books, and paid invoices cannot be voided until reversed', function () {
    $setup = billedSchool();
    $actor = accountant($setup, ['payments.approve']);
    generate($setup, $actor);
    $invoice = Invoice::query()->withoutGlobalScopes()->sole();
    $this->actingAs($actor)->post(route('finance.payments.store', $invoice), ['amount' => '50', 'method' => 'cash', 'paid_on' => now()->toDateString()]);
    $payment = Payment::query()->withoutGlobalScopes()->sole();

    $this->actingAs($actor)->post(route('finance.invoices.void', $invoice), ['reason' => 'Billed in error'])->assertSessionHasErrors('reason');
    $this->actingAs($actor)->post(route('finance.payments.reverse', $payment), ['reason' => 'Bounced cheque'])->assertSessionHasNoErrors();
    $this->actingAs($actor)->post(route('finance.invoices.void', $invoice), ['reason' => 'Billed in error'])->assertSessionHasNoErrors();

    expect($invoice->fresh())->status->toBe(InvoiceStatus::Void)->paid->toBe(0);
    $ledger = ledgerTotals($setup['school']->id);
    expect($ledger['debit'])->toBe($ledger['credit'])
        ->and($ledger['by'][LedgerService::FEES_RECEIVABLE]['balance'])->toBe(0)
        ->and($ledger['by'][LedgerService::CASH]['balance'])->toBe(0)
        ->and($ledger['by'][LedgerService::FEE_INCOME]['balance'])->toBe(0);
});

test('staff without payments.approve cannot reverse payments', function () {
    $setup = billedSchool();
    $actor = accountant($setup);
    generate($setup, $actor);
    $invoice = Invoice::query()->withoutGlobalScopes()->sole();
    $this->actingAs($actor)->post(route('finance.payments.store', $invoice), ['amount' => '50', 'method' => 'cash', 'paid_on' => now()->toDateString()]);

    $this->actingAs($actor)->post(route('finance.payments.reverse', Payment::query()->withoutGlobalScopes()->sole()), ['reason' => 'x'])->assertForbidden();
});

test('guardians only see their own children\'s invoices and cannot record payments', function () {
    $setup = billedSchool();
    $otherSection = Section::factory()->create(['school_class_id' => $setup['section']->school_class_id, 'name' => 'B']);
    $stranger = Student::factory()->create(['school_id' => $setup['school']->id]);
    Enrollment::factory()->create(['student_id' => $stranger->id, 'section_id' => $otherSection->id, 'academic_year_id' => $setup['year']->id]);
    generate($setup, accountant($setup));
    $parent = userWithPermissions(['invoices.view', 'payments.view', 'payments.create'], $setup['school']);
    $setup['guardian']->update(['user_id' => $parent->id]);
    $own = Invoice::query()->withoutGlobalScopes()->where('student_id', $setup['student']->id)->sole();
    $theirs = Invoice::query()->withoutGlobalScopes()->where('student_id', $stranger->id)->sole();

    $this->actingAs($parent)->get(route('finance.invoices.index'))->assertInertia(fn (Assert $page) => $page->has('invoices.data', 1)->where('invoices.data.0.id', $own->id));
    $this->actingAs($parent)->get(route('finance.invoices.show', $theirs))->assertNotFound();
    $this->actingAs($parent)->post(route('finance.payments.store', $own), ['amount' => '10', 'method' => 'cash', 'paid_on' => now()->toDateString()])->assertForbidden();
});
