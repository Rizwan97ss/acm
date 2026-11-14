<?php

namespace App\Http\Controllers\Finance;

use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Concerns\ValidatesSchoolRecords;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\Finance\ExpenseService;
use App\Services\Finance\Money;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    use ValidatesSchoolRecords;

    public function __construct(
        private ExpenseService $expenses,
        private CurrentSchool $currentSchool,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('expenses.view');

        $this->expenses->ensureCategories((int) $this->currentSchool->id());

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ExpenseStatus::class)],
            'category_id' => ['nullable', 'integer'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $query = Expense::query()
            ->with(['category:id,name', 'submitter:id,name', 'reviewer:id,name'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['category_id'] ?? null, fn (Builder $query, int $id) => $query->where('expense_category_id', $id))
            ->when($filters['month'] ?? null, fn (Builder $query, string $month) => $query
                ->whereDate('spent_on', '>=', "{$month}-01")
                ->whereDate('spent_on', '<=', now()->parse("{$month}-01")->endOfMonth()->toDateString()));

        $totals = (clone $query)->toBase()->selectRaw('status, sum(amount) as total')->groupBy('status')->pluck('total', 'status');
        $expenses = $query->orderByDesc('spent_on')->orderByDesc('id')->paginate(25)->withQueryString();
        $user = $request->user();

        return Inertia::render('finance/expenses/index', [
            'expenses' => [
                'data' => $expenses->getCollection()->map(fn (Expense $expense): array => [
                    'id' => $expense->id,
                    'number' => $expense->number,
                    'payee' => $expense->payee,
                    'description' => $expense->description,
                    'category' => $expense->category->name,
                    'expense_category_id' => $expense->expense_category_id,
                    'amount' => $expense->amount,
                    'spent_on' => $expense->spent_on->toDateString(),
                    'method' => $expense->method->value,
                    'method_label' => $expense->method->label(),
                    'reference' => $expense->reference,
                    'status' => $expense->status->value,
                    'status_label' => $expense->status->label(),
                    'submitted_by' => $expense->submitter?->name,
                    'reviewed_by' => $expense->reviewer?->name,
                    'review_note' => $expense->review_note,
                    'can' => [
                        'review' => $user->can('expenses.approve') && $expense->status === ExpenseStatus::Pending && ($expense->submitted_by !== $user->id || $user->isSuperAdmin()),
                        'update' => $expense->status === ExpenseStatus::Pending && ($user->can('expenses.edit') || $expense->submitted_by === $user->id),
                    ],
                ])->values(),
                'meta' => [
                    'current_page' => $expenses->currentPage(),
                    'from' => $expenses->firstItem(),
                    'last_page' => $expenses->lastPage(),
                    'per_page' => $expenses->perPage(),
                    'to' => $expenses->lastItem(),
                    'total' => $expenses->total(),
                    'links' => $expenses->linkCollection()->all(),
                ],
            ],
            'totals' => [
                'approved' => (int) ($totals[ExpenseStatus::Approved->value] ?? 0),
                'pending' => (int) ($totals[ExpenseStatus::Pending->value] ?? 0),
            ],
            'categories' => ExpenseCategory::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => ExpenseStatus::options(),
            'methods' => PaymentMethod::options(),
            'filters' => $filters,
            'can' => ['create' => $user->can('expenses.create')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('expenses.create');

        $expense = $this->expenses->submit($request->user(), (int) $this->currentSchool->id(), $this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense :no submitted for approval.', ['no' => $expense->number])]);

        return to_route('finance.expenses.index');
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($expense->status === ExpenseStatus::Pending && ($request->user()->can('expenses.edit') || $expense->submitted_by === $request->user()->id), 403);

        $expense->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense updated.')]);

        return to_route('finance.expenses.index');
    }

    public function review(Request $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('expenses.approve');

        $validated = $request->validate([
            'decision' => ['required', Rule::in([ExpenseStatus::Approved->value, ExpenseStatus::Rejected->value])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->expenses->review($request->user(), $expense, ExpenseStatus::from($validated['decision']), $validated['note'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense :status.', ['status' => strtolower(ExpenseStatus::from($validated['decision'])->label())])]);

        return to_route('finance.expenses.index');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        Gate::authorize('expenses.edit');

        $validated = $request->validate(['name' => ['required', 'string', 'max:100', $this->uniqueInSchool('expense_categories', 'name')]]);
        ExpenseCategory::query()->create(['school_id' => $this->currentSchool->id(), 'name' => $validated['name']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category added.')]);

        return to_route('finance.expenses.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'expense_category_id' => ['required', 'integer', $this->existsInSchool('expense_categories')],
            'payee' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        return [...$validated, 'amount' => Money::toCents($validated['amount'])];
    }
}
