<?php

namespace App\Http\Controllers\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Concerns\ValidatesSchoolRecords;
use App\Models\AcademicYear;
use App\Models\FeeItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Academics\AcademicOptions;
use App\Services\Finance\BillingService;
use App\Services\People\PortalScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    use ValidatesSchoolRecords;

    public function __construct(
        private BillingService $billing,
        private PortalScope $portalScope,
    ) {}

    public function index(Request $request, AcademicOptions $options): Response
    {
        Gate::authorize('invoices.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
            'section_id' => ['nullable', 'integer'],
            'overdue' => ['nullable', 'boolean'],
        ]);

        $portalStudentIds = $this->portalStudentIds($request);

        $query = Invoice::query()
            ->with('student:id,first_name,last_name,admission_no')
            ->when($portalStudentIds !== null, fn (Builder $query) => $query->whereIn('student_id', $portalStudentIds))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['overdue'] ?? false, fn (Builder $query) => $query->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])->whereDate('due_on', '<', now()->toDateString()))
            ->when($filters['section_id'] ?? null, fn (Builder $query, int $sectionId) => $query->whereHas('student.enrollments', fn (Builder $enrollments) => $enrollments->where('section_id', $sectionId)))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $escaped = addcslashes($search, '%_\\');
                $query->where(fn (Builder $query) => $query
                    ->where('number', 'like', "%{$escaped}%")
                    ->orWhere('title', 'like', "%{$escaped}%")
                    ->orWhereHas('student', fn (Builder $students) => $students->where(fn (Builder $students) => $students
                        ->where('first_name', 'like', "%{$escaped}%")
                        ->orWhere('last_name', 'like', "%{$escaped}%")
                        ->orWhere('admission_no', 'like', "%{$escaped}%"))));
            });

        $summary = (clone $query)->toBase()->selectRaw('sum(total) as billed, sum(paid) as collected')->where('status', '!=', InvoiceStatus::Void->value)->first();

        $invoices = $query->orderByDesc('issued_on')->orderByDesc('id')->paginate(20)->withQueryString();

        return Inertia::render('finance/invoices/index', [
            'invoices' => [
                'data' => $invoices->getCollection()->map(fn (Invoice $invoice): array => $this->present($invoice))->values(),
                'meta' => $this->meta($invoices),
            ],
            'summary' => [
                'billed' => (int) ($summary->billed ?? 0),
                'collected' => (int) ($summary->collected ?? 0),
            ],
            'filters' => $filters,
            'statuses' => InvoiceStatus::options(),
            'sections' => $portalStudentIds === null ? $options->sections() : [],
            'can' => ['create' => $request->user()->can('invoices.create') && $portalStudentIds === null],
        ]);
    }

    public function create(Request $request, AcademicOptions $options): Response
    {
        Gate::authorize('invoices.create');

        $year = AcademicYear::query()->current()->first();

        return Inertia::render('finance/invoices/generate', [
            'year' => $year?->only(['id', 'name']),
            'classes' => $options->classes(),
            'feeItems' => $year === null ? [] : FeeItem::query()->with('schoolClass:id,name')->where('academic_year_id', $year->id)->where('is_active', true)->orderBy('name')->get()
                ->map(fn (FeeItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'amount' => $item->amount,
                    'frequency_label' => $item->frequency->label(),
                    'school_class' => $item->schoolClass?->name,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('invoices.create');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'issued_on' => ['required', 'date'],
            'due_on' => ['required', 'date', 'after_or_equal:issued_on'],
            'fee_item_ids' => ['required', 'array', 'min:1'],
            'fee_item_ids.*' => ['integer', $this->existsInSchool('fee_items')],
            'class_ids' => ['required', 'array', 'min:1'],
            'class_ids.*' => ['integer', $this->existsInSchool('school_classes')],
        ]);

        $result = $this->billing->generateBatch(
            $request->user(),
            AcademicYear::query()->current()->firstOrFail(),
            $validated['title'],
            CarbonImmutable::parse($validated['issued_on']),
            CarbonImmutable::parse($validated['due_on']),
            array_values(array_map(intval(...), $validated['fee_item_ids'])),
            array_values(array_map(intval(...), $validated['class_ids'])),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{0} No new invoices — everyone in those classes is already billed.|{1} 1 invoice issued.|[2,*] :count invoices issued.', $result['created'], ['count' => $result['created']])]);

        return to_route('finance.invoices.index');
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        $this->authorizeView($request, $invoice);

        $invoice->load(['student:id,first_name,last_name,admission_no', 'lines', 'payments.receiver:id,name']);
        $isPortalUser = $this->portalStudentIds($request) !== null;

        return Inertia::render('finance/invoices/show', [
            'invoice' => [
                ...$this->present($invoice),
                'subtotal' => $invoice->subtotal,
                'discount' => $invoice->discount,
                'void_reason' => $invoice->void_reason,
                'lines' => $invoice->lines->map(fn ($line): array => $line->only(['id', 'description', 'amount', 'discount']))->values(),
                'payments' => $invoice->payments->sortByDesc('paid_on')->map(fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'receipt_no' => $payment->receipt_no,
                    'amount' => $payment->amount,
                    'method' => $payment->method->label(),
                    'reference' => $payment->reference,
                    'paid_on' => $payment->paid_on->toDateString(),
                    'received_by' => $payment->receiver?->name,
                    'reversed' => $payment->reversed_at !== null,
                    'reversal_reason' => $payment->reversal_reason,
                ])->values(),
            ],
            'methods' => PaymentMethod::options(),
            'can' => [
                'pay' => ! $isPortalUser && $request->user()->can('payments.create') && $invoice->status !== InvoiceStatus::Void && $invoice->balance() > 0,
                'reverse' => ! $isPortalUser && $request->user()->can('payments.approve'),
                'void' => ! $isPortalUser && $request->user()->can('invoices.delete') && $invoice->status !== InvoiceStatus::Void,
            ],
        ]);
    }

    public function void(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('invoices.delete');

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->billing->voidInvoice($request->user(), $invoice, $validated['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice voided.')]);

        return to_route('finance.invoices.show', $invoice);
    }

    private function authorizeView(Request $request, Invoice $invoice): void
    {
        abort_unless($request->user()->can('invoices.view'), 403);

        $portalStudentIds = $this->portalStudentIds($request);
        abort_unless($portalStudentIds === null || in_array($invoice->student_id, $portalStudentIds, true), 404);
    }

    /**
     * @return list<int>|null null for staff (no restriction)
     */
    private function portalStudentIds(Request $request): ?array
    {
        return $this->portalScope->isPortalUser($request->user()) ? $this->portalScope->studentIds($request->user()) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'title' => $invoice->title,
            'student' => [
                'id' => $invoice->student->id,
                'name' => $invoice->student->full_name,
                'admission_no' => $invoice->student->admission_no,
            ],
            'issued_on' => $invoice->issued_on->toDateString(),
            'due_on' => $invoice->due_on->toDateString(),
            'total' => $invoice->total,
            'paid' => $invoice->paid,
            'balance' => $invoice->balance(),
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'overdue' => $invoice->isOverdue(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Invoice>  $paginator
     * @return array<string, mixed>
     */
    private function meta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
            'links' => $paginator->linkCollection()->all(),
        ];
    }
}
