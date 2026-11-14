<?php

namespace App\Http\Controllers\Finance;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Finance\BillingService;
use App\Services\Finance\Money;
use App\Services\People\PortalScope;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(private BillingService $billing) {}

    /**
     * The receipts register with collection totals per method.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('payments.view');
        abort_if(app(PortalScope::class)->isPortalUser($request->user()), 403);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $from = CarbonImmutable::parse($filters['from'] ?? now()->startOfMonth()->toDateString());
        $to = CarbonImmutable::parse($filters['to'] ?? now()->toDateString());

        $query = Payment::query()
            ->whereNull('reversed_at')
            ->whereDate('paid_on', '>=', $from->toDateString())
            ->whereDate('paid_on', '<=', $to->toDateString());

        $byMethod = (clone $query)->toBase()->selectRaw('method, sum(amount) as total, count(*) as count')->groupBy('method')->get()
            ->map(fn (object $row): array => [
                'method' => PaymentMethod::from($row->method)->label(),
                'total' => (int) $row->total,
                'count' => (int) $row->count,
            ])->values();

        $payments = (clone $query)
            ->with(['student:id,first_name,last_name,admission_no', 'invoice:id,number', 'receiver:id,name'])
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('finance/payments/index', [
            'payments' => [
                'data' => $payments->getCollection()->map(fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'receipt_no' => $payment->receipt_no,
                    'invoice_id' => $payment->invoice_id,
                    'invoice_number' => $payment->invoice->number,
                    'student' => $payment->student->full_name,
                    'amount' => $payment->amount,
                    'method' => $payment->method->label(),
                    'paid_on' => $payment->paid_on->toDateString(),
                    'received_by' => $payment->receiver?->name,
                ])->values(),
                'meta' => [
                    'current_page' => $payments->currentPage(),
                    'from' => $payments->firstItem(),
                    'last_page' => $payments->lastPage(),
                    'per_page' => $payments->perPage(),
                    'to' => $payments->lastItem(),
                    'total' => $payments->total(),
                    'links' => $payments->linkCollection()->all(),
                ],
            ],
            'byMethod' => $byMethod,
            'total' => (int) $byMethod->sum('total'),
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('payments.create');
        abort_if(app(PortalScope::class)->isPortalUser($request->user()), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = $this->billing->recordPayment(
            $request->user(),
            $invoice,
            Money::toCents($validated['amount']),
            PaymentMethod::from($validated['method']),
            CarbonImmutable::parse($validated['paid_on']),
            $validated['reference'] ?? null,
            $validated['notes'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment recorded — receipt :no.', ['no' => $payment->receipt_no])]);

        return to_route('finance.invoices.show', $invoice);
    }

    public function reverse(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('payments.approve');

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->billing->reversePayment($request->user(), $payment, $validated['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment reversed.')]);

        return to_route('finance.invoices.show', $payment->invoice_id);
    }

    /**
     * Printable receipt. Guardians and students may print receipts for their own invoices.
     */
    public function receipt(Request $request, Payment $payment, PortalScope $portalScope): Response
    {
        abort_unless($request->user()->can('payments.view'), 403);

        if ($portalScope->isPortalUser($request->user())) {
            abort_unless(in_array($payment->student_id, $portalScope->studentIds($request->user()), true), 404);
        }

        $payment->load(['invoice', 'student.currentEnrollment.section.schoolClass', 'receiver:id,name']);

        return Inertia::render('finance/payments/receipt', [
            'payment' => [
                'receipt_no' => $payment->receipt_no,
                'amount' => $payment->amount,
                'method' => $payment->method->label(),
                'reference' => $payment->reference,
                'paid_on' => $payment->paid_on->toDateString(),
                'received_by' => $payment->receiver?->name,
                'reversed' => $payment->reversed_at !== null,
                'invoice' => [
                    'number' => $payment->invoice->number,
                    'title' => $payment->invoice->title,
                    'total' => $payment->invoice->total,
                    'balance' => $payment->invoice->balance(),
                ],
                'student' => [
                    'name' => $payment->student->full_name,
                    'admission_no' => $payment->student->admission_no,
                    'section' => $payment->student->currentEnrollment?->section->fullName(),
                ],
            ],
        ]);
    }
}
