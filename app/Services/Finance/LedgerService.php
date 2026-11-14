<?php

namespace App\Services\Finance;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Double-entry general ledger. Every entry balances; entries are never edited or
 * deleted, only reversed.
 */
class LedgerService
{
    public const string CASH = '1000';

    public const string BANK = '1010';

    public const string FEES_RECEIVABLE = '1100';

    public const string PAYROLL_PAYABLE = '2100';

    public const string RETAINED_EARNINGS = '3000';

    public const string FEE_INCOME = '4000';

    public const string OTHER_INCOME = '4900';

    public const string OPERATING_EXPENSES = '5000';

    public const string SALARIES = '5100';

    /**
     * Numeric codes become integer keys in PHP arrays, hence int|string.
     *
     * @var array<int|string, array{0: string, 1: AccountType}>
     */
    private const array SYSTEM_ACCOUNTS = [
        self::CASH => ['Cash', AccountType::Asset],
        self::BANK => ['Bank', AccountType::Asset],
        self::FEES_RECEIVABLE => ['Fees receivable', AccountType::Asset],
        self::PAYROLL_PAYABLE => ['Payroll deductions payable', AccountType::Liability],
        self::RETAINED_EARNINGS => ['Retained earnings', AccountType::Equity],
        self::FEE_INCOME => ['Fee income', AccountType::Income],
        self::OTHER_INCOME => ['Other income', AccountType::Income],
        self::OPERATING_EXPENSES => ['Operating expenses', AccountType::Expense],
        self::SALARIES => ['Salaries', AccountType::Expense],
    ];

    public function ensureAccounts(int $schoolId): void
    {
        foreach (self::SYSTEM_ACCOUNTS as $code => [$name, $type]) {
            Account::query()->withoutGlobalScopes()->firstOrCreate(
                ['school_id' => $schoolId, 'code' => (string) $code],
                ['name' => $name, 'type' => $type, 'is_system' => true],
            );
        }
    }

    /**
     * Post a balanced entry.
     *
     * @param  list<array{0: string, 1: int, 2: int}>  $lines  [account code, debit cents, credit cents]
     */
    public function post(int $schoolId, CarbonImmutable $date, string $memo, ?Model $source, array $lines, ?User $actor = null, ?int $reverses = null): JournalEntry
    {
        $debits = array_sum(array_column($lines, 1));
        $credits = array_sum(array_column($lines, 2));

        if ($debits !== $credits || $debits === 0) {
            throw new InvalidArgumentException("Unbalanced journal entry: debits {$debits}, credits {$credits}.");
        }

        return DB::transaction(function () use ($schoolId, $date, $memo, $source, $lines, $actor, $reverses): JournalEntry {
            $this->ensureAccounts($schoolId);

            $accounts = Account::query()->withoutGlobalScopes()->where('school_id', $schoolId)->pluck('id', 'code');

            $entry = JournalEntry::query()->create([
                'school_id' => $schoolId,
                'entry_date' => $date->toDateString(),
                'memo' => $memo,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'reverses_id' => $reverses,
                'created_by' => $actor?->id,
            ]);

            foreach ($lines as [$code, $debit, $credit]) {
                if ($debit === 0 && $credit === 0) {
                    continue;
                }

                $entry->lines()->create([
                    'account_id' => $accounts[$code] ?? throw new InvalidArgumentException("Unknown account {$code}."),
                    'debit' => $debit,
                    'credit' => $credit,
                ]);
            }

            return $entry;
        });
    }

    /**
     * Reverse every not-yet-reversed entry posted for a source record.
     */
    public function reverseFor(Model $source, CarbonImmutable $date, string $memo, ?User $actor = null): void
    {
        $entries = JournalEntry::query()
            ->with('lines.account:id,code')
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->whereNull('reverses_id')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('journal_entries as reversal')->whereColumn('reversal.reverses_id', 'journal_entries.id'))
            ->get();

        foreach ($entries as $entry) {
            $lines = array_values($entry->lines->map(fn ($line): array => [$line->account->code, $line->credit, $line->debit])->all());
            $this->post($entry->school_id, $date, $memo, $source, $lines, $actor, $entry->id);
        }
    }

    /**
     * Signed balance per account up to a date (debit-normal accounts positive when in debit).
     *
     * @return list<array{code: string, name: string, type: string, debit: int, credit: int, balance: int}>
     */
    public function balances(int $schoolId, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $this->ensureAccounts($schoolId);

        $totals = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.school_id', $schoolId)
            ->when($from, fn ($query) => $query->whereDate('journal_entries.entry_date', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->whereDate('journal_entries.entry_date', '<=', $to->toDateString()))
            ->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id, sum(journal_lines.debit) as debit, sum(journal_lines.credit) as credit')
            ->get()
            ->keyBy('account_id');

        $rows = Account::query()->withoutGlobalScopes()->where('school_id', $schoolId)->orderBy('code')->get()
            ->map(function (Account $account) use ($totals): array {
                $debit = (int) ($totals[$account->id]->debit ?? 0);
                $credit = (int) ($totals[$account->id]->credit ?? 0);

                return [
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type->value,
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => $account->type->isDebitNormal() ? $debit - $credit : $credit - $debit,
                ];
            })
            ->all();

        return array_values($rows);
    }
}
