<?php

namespace App\Services\Finance;

use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\WebhookEvent;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeConcession;
use App\Models\FeeItem;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\Payment;
use App\Models\User;
use App\Services\People\ReferenceNumberGenerator;
use App\Services\Webhooks\WebhookDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Invoices and payments, with every money movement posted to the ledger:
 *
 *   invoice issued   Dr Fees receivable  / Cr Fee income
 *   payment          Dr Cash or Bank     / Cr Fees receivable
 *   void / reversal  the original entry reversed
 */
class BillingService
{
    public function __construct(
        private LedgerService $ledger,
        private ReferenceNumberGenerator $numbers,
        private WebhookDispatcher $webhooks,
    ) {}

    /**
     * Invoice every actively enrolled student in the chosen classes for the chosen fee items.
     *
     * @param  list<int>  $feeItemIds
     * @param  list<int>  $classIds
     * @return array{batch: InvoiceBatch, created: int}
     */
    public function generateBatch(User $actor, AcademicYear $year, string $title, CarbonImmutable $issuedOn, CarbonImmutable $dueOn, array $feeItemIds, array $classIds): array
    {
        $items = FeeItem::query()->whereIn('id', $feeItemIds)->where('academic_year_id', $year->id)->where('is_active', true)->get();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['fee_item_ids' => __('Choose at least one active fee for this year.')]);
        }

        $enrollments = Enrollment::query()
            ->with(['section:id,school_class_id'])
            ->where('academic_year_id', $year->id)
            ->where('status', EnrollmentStatus::Active)
            ->whereHas('section', fn ($query) => $query->whereIn('school_class_id', $classIds))
            ->get();

        $concessions = FeeConcession::query()->whereIn('student_id', $enrollments->pluck('student_id'))->pluck('percent', 'student_id');

        // Re-running a batch with the same title must not bill anyone twice.
        $alreadyBilled = Invoice::query()
            ->where('academic_year_id', $year->id)
            ->where('title', $title)
            ->where('status', '!=', InvoiceStatus::Void)
            ->pluck('student_id')
            ->flip();
        $enrollments = $enrollments->reject(fn (Enrollment $enrollment): bool => $alreadyBilled->has($enrollment->student_id));

        $batch = InvoiceBatch::query()->create([
            'school_id' => $year->school_id,
            'academic_year_id' => $year->id,
            'title' => $title,
            'issued_on' => $issuedOn->toDateString(),
            'due_on' => $dueOn->toDateString(),
            'created_by' => $actor->id,
        ]);

        $created = 0;

        foreach ($enrollments as $enrollment) {
            $applicable = $items->filter(fn (FeeItem $item): bool => $item->school_class_id === null || $item->school_class_id === $enrollment->section->school_class_id);

            if ($applicable->isEmpty()) {
                continue;
            }

            $percent = (float) ($concessions[$enrollment->student_id] ?? 0);
            $lines = array_values($applicable->map(fn (FeeItem $item): array => [
                'fee_item_id' => $item->id,
                'description' => $item->name,
                'amount' => $item->amount,
                'discount' => Money::percentOf($item->amount, $percent),
            ])->all());

            $this->createInvoice($actor, $year, $enrollment->student_id, $title, $issuedOn, $dueOn, $lines, $batch->id);
            $created++;
        }

        return ['batch' => $batch, 'created' => $created];
    }

    /**
     * @param  list<array{fee_item_id: int|null, description: string, amount: int, discount: int}>  $lines
     * @param  string  $incomeAccount  Ledger account credited with the invoice total (fees by default).
     */
    public function createInvoice(User $actor, AcademicYear $year, int $studentId, string $title, CarbonImmutable $issuedOn, CarbonImmutable $dueOn, array $lines, ?int $batchId = null, string $incomeAccount = LedgerService::FEE_INCOME): Invoice
    {
        $subtotal = array_sum(array_column($lines, 'amount'));
        $discount = array_sum(array_column($lines, 'discount'));

        return $this->numbers->withNext('invoice', $year->school_id, fn (string $number): Invoice => DB::transaction(function () use ($actor, $year, $studentId, $title, $issuedOn, $dueOn, $lines, $batchId, $subtotal, $discount, $number, $incomeAccount): Invoice {
            $invoice = new Invoice([
                'school_id' => $year->school_id,
                'student_id' => $studentId,
                'academic_year_id' => $year->id,
                'invoice_batch_id' => $batchId,
                'number' => $number,
                'title' => $title,
                'issued_on' => $issuedOn->toDateString(),
                'due_on' => $dueOn->toDateString(),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $subtotal - $discount,
                'created_by' => $actor->id,
            ]);
            $invoice->forceFill(['paid' => 0, 'status' => $subtotal - $discount === 0 ? InvoiceStatus::Paid : InvoiceStatus::Unpaid])->save();
            $invoice->lines()->createMany($lines);

            if ($invoice->total > 0) {
                $this->ledger->post($invoice->school_id, $issuedOn, "Invoice {$invoice->number}", $invoice, [
                    [LedgerService::FEES_RECEIVABLE, $invoice->total, 0],
                    [$incomeAccount, 0, $invoice->total],
                ], $actor);
            }

            return $invoice;
        }));
    }

    public function recordPayment(User $actor, Invoice $invoice, int $amount, PaymentMethod $method, CarbonImmutable $paidOn, ?string $reference, ?string $notes): Payment
    {
        if ($invoice->status === InvoiceStatus::Void) {
            throw ValidationException::withMessages(['amount' => __('This invoice is void.')]);
        }

        if ($amount <= 0 || $amount > $invoice->balance()) {
            throw ValidationException::withMessages(['amount' => __('The amount must be more than zero and no more than the balance due.')]);
        }

        return $this->numbers->withNext('receipt', $invoice->school_id, fn (string $receiptNo): Payment => DB::transaction(function () use ($actor, $invoice, $amount, $method, $paidOn, $reference, $notes, $receiptNo): Payment {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($amount > $locked->balance()) {
                throw ValidationException::withMessages(['amount' => __('The amount must be more than zero and no more than the balance due.')]);
            }

            $payment = Payment::query()->create([
                'school_id' => $locked->school_id,
                'invoice_id' => $locked->id,
                'student_id' => $locked->student_id,
                'receipt_no' => $receiptNo,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'paid_on' => $paidOn->toDateString(),
                'notes' => $notes,
                'received_by' => $actor->id,
            ]);

            $this->applyPaid($locked, $locked->paid + $amount);

            $this->webhooks->dispatch($locked->school_id, WebhookEvent::PaymentRecorded, [
                'payment_id' => $payment->id,
                'receipt_no' => $receiptNo,
                'invoice_number' => $locked->number,
                'student_id' => $locked->student_id,
                'amount' => $amount,
                'method' => $method->value,
                'paid_on' => $paidOn->toDateString(),
                'invoice_balance' => $locked->balance(),
            ]);

            $this->ledger->post($locked->school_id, $paidOn, "Receipt {$receiptNo} for {$locked->number}", $payment, [
                [$method->accountCode(), $amount, 0],
                [LedgerService::FEES_RECEIVABLE, 0, $amount],
            ], $actor);

            return $payment;
        }));
    }

    public function reversePayment(User $actor, Payment $payment, string $reason): void
    {
        if ($payment->reversed_at !== null) {
            throw ValidationException::withMessages(['reason' => __('This payment has already been reversed.')]);
        }

        DB::transaction(function () use ($actor, $payment, $reason): void {
            $payment->forceFill(['reversed_at' => now(), 'reversal_reason' => $reason, 'reversed_by' => $actor->id])->save();

            $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);
            $this->applyPaid($invoice, $invoice->paid - $payment->amount);

            $this->ledger->reverseFor($payment, CarbonImmutable::now(), "Reversal of receipt {$payment->receipt_no}", $actor);
        });
    }

    public function voidInvoice(User $actor, Invoice $invoice, string $reason): void
    {
        if ($invoice->status === InvoiceStatus::Void) {
            throw ValidationException::withMessages(['reason' => __('This invoice is already void.')]);
        }

        if ($invoice->payments()->whereNull('reversed_at')->exists()) {
            throw ValidationException::withMessages(['reason' => __('Reverse the payments on this invoice before voiding it.')]);
        }

        DB::transaction(function () use ($actor, $invoice, $reason): void {
            $invoice->forceFill(['status' => InvoiceStatus::Void, 'voided_at' => now(), 'void_reason' => $reason])->save();
            $this->ledger->reverseFor($invoice, CarbonImmutable::now(), "Void of invoice {$invoice->number}", $actor);
        });
    }

    private function applyPaid(Invoice $invoice, int $paid): void
    {
        $status = match (true) {
            $paid >= $invoice->total => InvoiceStatus::Paid,
            $paid > 0 => InvoiceStatus::Partial,
            default => InvoiceStatus::Unpaid,
        };

        $invoice->forceFill(['paid' => $paid, 'status' => $status])->save();
    }
}
