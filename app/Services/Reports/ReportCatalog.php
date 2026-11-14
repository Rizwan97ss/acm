<?php

namespace App\Services\Reports;

use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\Money;
use Carbon\CarbonImmutable;
use Generator;
use InvalidArgumentException;

/**
 * The CSV exports available on the Reports page. Each one needs `reports.export` plus
 * view access to its module. Encrypted personal fields (national ID, address, medical
 * notes) and salaries are deliberately not exportable.
 */
class ReportCatalog
{
    /**
     * @var array<string, array{label: string, description: string, permission: string, dated: bool}>
     */
    public const array REPORTS = [
        'students' => ['label' => 'Student roll', 'description' => 'Current-year enrolments with class and section.', 'permission' => 'students.view', 'dated' => false],
        'attendance' => ['label' => 'Attendance register', 'description' => 'Every attendance mark in the date range.', 'permission' => 'attendance.view', 'dated' => true],
        'invoices' => ['label' => 'Invoices', 'description' => 'Invoices issued in the date range with balances.', 'permission' => 'invoices.view', 'dated' => true],
        'payments' => ['label' => 'Payments', 'description' => 'Receipts in the date range, including reversals.', 'permission' => 'payments.view', 'dated' => true],
        'expenses' => ['label' => 'Expenses', 'description' => 'Expenses in the date range with approval status.', 'permission' => 'expenses.view', 'dated' => true],
    ];

    /**
     * @return list<array{key: string, label: string, description: string, dated: bool}>
     */
    public function availableTo(User $user): array
    {
        if (! $user->can('reports.export')) {
            return [];
        }

        return array_values(collect(self::REPORTS)
            ->filter(fn (array $report): bool => $user->can($report['permission']))
            ->map(fn (array $report, string $key): array => ['key' => $key, 'label' => $report['label'], 'description' => $report['description'], 'dated' => $report['dated']])
            ->all());
    }

    /**
     * @return array{0: list<string>, 1: Generator<int, list<mixed>>}
     */
    public function build(string $report, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return match ($report) {
            'students' => [['Admission no', 'First name', 'Last name', 'Gender', 'Status', 'Class', 'Section', 'Roll no', 'Admission date'], $this->students()],
            'attendance' => [['Date', 'Admission no', 'Student', 'Class', 'Section', 'Status', 'Remarks'], $this->attendance($from, $to)],
            'invoices' => [['Number', 'Admission no', 'Student', 'Title', 'Issued', 'Due', 'Total', 'Paid', 'Balance', 'Status'], $this->invoices($from, $to)],
            'payments' => [['Receipt', 'Date', 'Admission no', 'Student', 'Invoice', 'Amount', 'Method', 'Reference', 'Reversed'], $this->payments($from, $to)],
            'expenses' => [['Number', 'Date', 'Category', 'Payee', 'Description', 'Amount', 'Method', 'Status'], $this->expenses($from, $to)],
            default => throw new InvalidArgumentException("Unknown report [{$report}]."),
        };
    }

    /**
     * @return Generator<int, list<mixed>>
     */
    private function students(): Generator
    {
        $enrollments = Enrollment::query()
            ->whereHas('academicYear', fn ($query) => $query->where('is_current', true))
            ->with(['student:id,admission_no,first_name,last_name,gender,status,admission_date', 'section:id,name,school_class_id', 'section.schoolClass:id,name'])
            ->orderBy('section_id')
            ->lazyById(500);

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;

            yield [$student?->admission_no, $student?->first_name, $student?->last_name, $student?->gender, $student?->status, $enrollment->section?->schoolClass?->name, $enrollment->section?->name, $enrollment->roll_number, $student?->admission_date];
        }
    }

    /**
     * @return Generator<int, list<mixed>>
     */
    private function attendance(CarbonImmutable $from, CarbonImmutable $to): Generator
    {
        $records = AttendanceRecord::query()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->with(['student:id,admission_no,first_name,last_name', 'section:id,name,school_class_id', 'section.schoolClass:id,name'])
            ->lazyById(1000);

        foreach ($records as $record) {
            yield [$record->date, $record->student?->admission_no, trim("{$record->student?->first_name} {$record->student?->last_name}"), $record->section?->schoolClass?->name, $record->section?->name, $record->status, $record->remarks];
        }
    }

    /**
     * @return Generator<int, list<mixed>>
     */
    private function invoices(CarbonImmutable $from, CarbonImmutable $to): Generator
    {
        $invoices = Invoice::query()
            ->whereDate('issued_on', '>=', $from->toDateString())
            ->whereDate('issued_on', '<=', $to->toDateString())
            ->with('student:id,admission_no,first_name,last_name')
            ->lazyById(500);

        foreach ($invoices as $invoice) {
            yield [$invoice->number, $invoice->student?->admission_no, trim("{$invoice->student?->first_name} {$invoice->student?->last_name}"), $invoice->title, $invoice->issued_on, $invoice->due_on, $this->money($invoice->total), $this->money($invoice->paid), $this->money($invoice->balance()), $invoice->status];
        }
    }

    /**
     * @return Generator<int, list<mixed>>
     */
    private function payments(CarbonImmutable $from, CarbonImmutable $to): Generator
    {
        $payments = Payment::query()
            ->whereDate('paid_on', '>=', $from->toDateString())
            ->whereDate('paid_on', '<=', $to->toDateString())
            ->with(['student:id,admission_no,first_name,last_name', 'invoice:id,number'])
            ->lazyById(500);

        foreach ($payments as $payment) {
            yield [$payment->receipt_no, $payment->paid_on, $payment->student?->admission_no, trim("{$payment->student?->first_name} {$payment->student?->last_name}"), $payment->invoice?->number, $this->money($payment->amount), $payment->method, $payment->reference, $payment->reversed_at !== null];
        }
    }

    /**
     * @return Generator<int, list<mixed>>
     */
    private function expenses(CarbonImmutable $from, CarbonImmutable $to): Generator
    {
        $expenses = Expense::query()
            ->whereDate('spent_on', '>=', $from->toDateString())
            ->whereDate('spent_on', '<=', $to->toDateString())
            ->with('category:id,name')
            ->lazyById(500);

        foreach ($expenses as $expense) {
            yield [$expense->number, $expense->spent_on, $expense->category?->name, $expense->payee, $expense->description, $this->money($expense->amount), $expense->method, $expense->status];
        }
    }

    private function money(int $cents): string
    {
        return number_format(Money::toMajor($cents), 2, '.', '');
    }
}
