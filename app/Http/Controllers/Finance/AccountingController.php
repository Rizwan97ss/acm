<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Services\Finance\LedgerService;
use App\Services\Finance\Money;
use App\Services\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Financial reports from the general ledger, plus manual journal entries.
 */
class AccountingController extends Controller
{
    public function __construct(
        private LedgerService $ledger,
        private CurrentSchool $currentSchool,
    ) {}

    public function overview(Request $request): Response
    {
        Gate::authorize('accounting.view');

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $schoolId = (int) $this->currentSchool->id();
        $from = CarbonImmutable::parse($filters['from'] ?? now()->startOfYear()->toDateString());
        $to = CarbonImmutable::parse($filters['to'] ?? now()->toDateString());

        $period = collect($this->ledger->balances($schoolId, $from, $to));
        $income = $period->where('type', 'income')->values();
        $expenses = $period->where('type', 'expense')->values();

        return Inertia::render('finance/accounting/overview', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'trialBalance' => $this->ledger->balances($schoolId, null, $to),
            'income' => $income,
            'expenses' => $expenses,
            'totals' => [
                'income' => (int) $income->sum('balance'),
                'expenses' => (int) $expenses->sum('balance'),
                'net' => (int) $income->sum('balance') - (int) $expenses->sum('balance'),
                'receivable' => (int) Invoice::query()->whereIn('status', ['unpaid', 'partial'])->toBase()->selectRaw('sum(total - paid) as due')->value('due'),
            ],
            'monthly' => $this->monthly($schoolId),
        ]);
    }

    public function journal(Request $request): Response
    {
        Gate::authorize('accounting.view');

        $entries = JournalEntry::query()
            ->with(['lines.account:id,code,name'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(25);

        return Inertia::render('finance/accounting/journal', [
            'entries' => [
                'data' => $entries->getCollection()->map(fn (JournalEntry $entry): array => [
                    'id' => $entry->id,
                    'date' => $entry->entry_date->toDateString(),
                    'memo' => $entry->memo,
                    'source' => $entry->source_type ? class_basename($entry->source_type) : 'Manual',
                    'is_reversal' => $entry->reverses_id !== null,
                    'lines' => $entry->lines->map(fn ($line): array => [
                        'account' => "{$line->account->code} {$line->account->name}",
                        'debit' => $line->debit,
                        'credit' => $line->credit,
                    ])->values(),
                ])->values(),
                'meta' => [
                    'current_page' => $entries->currentPage(),
                    'from' => $entries->firstItem(),
                    'last_page' => $entries->lastPage(),
                    'per_page' => $entries->perPage(),
                    'to' => $entries->lastItem(),
                    'total' => $entries->total(),
                    'links' => $entries->linkCollection()->all(),
                ],
            ],
            'accounts' => Account::query()->orderBy('code')->get(['id', 'code', 'name']),
            'can' => ['create' => $request->user()->can('accounting.create')],
        ]);
    }

    /**
     * A manual, balanced journal entry (e.g. opening balances or a donation received).
     */
    public function storeJournal(Request $request): RedirectResponse
    {
        Gate::authorize('accounting.create');

        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'memo' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2', 'max:20'],
            'lines.*.account_id' => ['required', 'integer'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $accounts = Account::query()->pluck('code', 'id');
        $lines = [];

        foreach ($validated['lines'] as $index => $line) {
            $code = $accounts[(int) $line['account_id']] ?? throw ValidationException::withMessages(["lines.{$index}.account_id" => __('Unknown account.')]);
            $debit = Money::toCents($line['debit'] ?? 0);
            $credit = Money::toCents($line['credit'] ?? 0);

            if (($debit > 0) === ($credit > 0)) {
                throw ValidationException::withMessages(["lines.{$index}.debit" => __('Each line needs either a debit or a credit.')]);
            }

            $lines[] = [$code, $debit, $credit];
        }

        if (array_sum(array_column($lines, 1)) !== array_sum(array_column($lines, 2))) {
            throw ValidationException::withMessages(['lines' => __('Debits and credits must be equal.')]);
        }

        $this->ledger->post((int) $this->currentSchool->id(), CarbonImmutable::parse($validated['entry_date']), $validated['memo'], null, $lines, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Journal entry posted.')]);

        return to_route('finance.accounting.journal');
    }

    /**
     * Income vs expenses for the last six months.
     *
     * @return list<array{month: string, income: int, expenses: int}>
     */
    private function monthly(int $schoolId): array
    {
        $months = [];

        for ($i = 5; $i >= 0; $i--) {
            $start = CarbonImmutable::now()->startOfMonth()->subMonths($i);
            $rows = DB::table('journal_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
                ->where('journal_entries.school_id', $schoolId)
                ->whereDate('journal_entries.entry_date', '>=', $start->toDateString())
                ->whereDate('journal_entries.entry_date', '<=', $start->endOfMonth()->toDateString())
                ->whereIn('accounts.type', ['income', 'expense'])
                ->groupBy('accounts.type')
                ->selectRaw('accounts.type, sum(journal_lines.credit) as credit, sum(journal_lines.debit) as debit')
                ->get()
                ->keyBy('type');

            $months[] = [
                'month' => $start->format('Y-m'),
                'income' => (int) (($rows['income']->credit ?? 0) - ($rows['income']->debit ?? 0)),
                'expenses' => (int) (($rows['expense']->debit ?? 0) - ($rows['expense']->credit ?? 0)),
            ];
        }

        return $months;
    }
}
