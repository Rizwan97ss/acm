import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import Pagination from '@/components/data/pagination';
import FormField from '@/components/form-field';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useFormat } from '@/hooks/use-format';
import { useMoney } from '@/hooks/use-money';
import { journal, overview } from '@/routes/finance/accounting';
import { store } from '@/routes/finance/accounting/journal';
import type { Paginated } from '@/types';

type Entry = {
    id: number;
    date: string;
    memo: string;
    source: string;
    is_reversal: boolean;
    lines: { account: string; debit: number; credit: number }[];
};

type Props = {
    entries: Pick<Paginated<Entry>, 'data' | 'meta'>;
    accounts: { id: number; code: string; name: string }[];
    can: { create: boolean };
};

function JournalDialog({
    accounts,
    onClose,
}: {
    accounts: Props['accounts'];
    onClose: () => void;
}) {
    const form = useForm({
        entry_date: new Date().toISOString().slice(0, 10),
        memo: '',
        lines: [
            { account_id: accounts[0]?.id ?? 0, debit: '', credit: '' },
            { account_id: accounts[1]?.id ?? 0, debit: '', credit: '' },
        ],
    });
    const errors = form.errors as Record<string, string | undefined>;
    const debits = form.data.lines.reduce(
        (sum, line) => sum + Number(line.debit || 0),
        0,
    );
    const credits = form.data.lines.reduce(
        (sum, line) => sum + Number(line.credit || 0),
        0,
    );
    const balanced = debits > 0 && Math.abs(debits - credits) < 0.005;

    const setLine = (
        i: number,
        changes: Partial<(typeof form.data.lines)[number]>,
    ): void =>
        form.setData(
            'lines',
            form.data.lines.map((line, index) =>
                index === i ? { ...line, ...changes } : line,
            ),
        );

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.post(store.url(), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-2xl">
                <DialogTitle>Manual journal entry</DialogTitle>
                <DialogDescription>
                    For opening balances, donations and corrections. Debits must
                    equal credits.
                </DialogDescription>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-[10rem_1fr]">
                        <FormField
                            id="je-date"
                            label="Date"
                            error={form.errors.entry_date}
                            required
                        >
                            <Input
                                id="je-date"
                                type="date"
                                value={form.data.entry_date}
                                onChange={(e) =>
                                    form.setData('entry_date', e.target.value)
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            id="je-memo"
                            label="Memo"
                            error={form.errors.memo}
                            required
                        >
                            <Input
                                id="je-memo"
                                value={form.data.memo}
                                onChange={(e) =>
                                    form.setData('memo', e.target.value)
                                }
                                required
                            />
                        </FormField>
                    </div>
                    {form.data.lines.map((line, i) => (
                        <div
                            key={i}
                            className="grid items-start gap-2 sm:grid-cols-[1fr_8rem_8rem_auto]"
                        >
                            <NativeSelect
                                aria-label="Account"
                                value={line.account_id}
                                onChange={(e) =>
                                    setLine(i, {
                                        account_id: Number(e.target.value),
                                    })
                                }
                            >
                                {accounts.map((account) => (
                                    <option key={account.id} value={account.id}>
                                        {account.code} {account.name}
                                    </option>
                                ))}
                            </NativeSelect>
                            <div>
                                <Input
                                    aria-label="Debit"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    placeholder="Debit"
                                    value={line.debit}
                                    onChange={(e) =>
                                        setLine(i, {
                                            debit: e.target.value,
                                            credit: e.target.value
                                                ? ''
                                                : line.credit,
                                        })
                                    }
                                />
                                <InputError
                                    message={errors[`lines.${i}.debit`]}
                                />
                            </div>
                            <Input
                                aria-label="Credit"
                                type="number"
                                min={0}
                                step="0.01"
                                placeholder="Credit"
                                value={line.credit}
                                onChange={(e) =>
                                    setLine(i, {
                                        credit: e.target.value,
                                        debit: e.target.value ? '' : line.debit,
                                    })
                                }
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Remove line"
                                disabled={form.data.lines.length <= 2}
                                onClick={() =>
                                    form.setData(
                                        'lines',
                                        form.data.lines.filter(
                                            (_, index) => index !== i,
                                        ),
                                    )
                                }
                            >
                                <Trash2 aria-hidden />
                            </Button>
                        </div>
                    ))}
                    <InputError message={errors.lines} />
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                form.setData('lines', [
                                    ...form.data.lines,
                                    {
                                        account_id: accounts[0]?.id ?? 0,
                                        debit: '',
                                        credit: '',
                                    },
                                ])
                            }
                        >
                            <Plus aria-hidden /> Add line
                        </Button>
                        <span
                            className={`text-sm tabular-nums ${balanced ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600'}`}
                        >
                            Debits {debits.toFixed(2)} · Credits{' '}
                            {credits.toFixed(2)}
                        </span>
                    </div>
                    <div className="flex justify-end">
                        <Button
                            type="submit"
                            disabled={form.processing || !balanced}
                        >
                            Post entry
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Journal({ entries, accounts, can }: Props) {
    const money = useMoney();
    const format = useFormat();
    const [open, setOpen] = useState(false);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Journal"
                description="Every posting in the ledger, newest first. Entries are never edited — corrections are reversals."
                actions={
                    can.create && (
                        <Button onClick={() => setOpen(true)}>
                            <Plus aria-hidden /> Manual entry
                        </Button>
                    )
                }
            />
            <ul className="space-y-3">
                {entries.data.map((entry) => (
                    <li
                        key={entry.id}
                        className="rounded-2xl border bg-card p-4 shadow-sm"
                    >
                        <div className="mb-2 flex flex-wrap items-center gap-2">
                            <span className="text-sm text-muted-foreground">
                                {format.date(`${entry.date}T12:00:00Z`)}
                            </span>
                            <span className="font-medium">{entry.memo}</span>
                            <TonePill
                                tone={entry.is_reversal ? 'warning' : 'info'}
                            >
                                {entry.is_reversal ? 'Reversal' : entry.source}
                            </TonePill>
                        </div>
                        <table className="w-full text-sm">
                            <caption className="sr-only">
                                Lines of {entry.memo}
                            </caption>
                            <tbody>
                                {entry.lines.map((line, i) => (
                                    <tr key={i}>
                                        <td
                                            className={`py-0.5 ${line.credit > 0 ? 'pl-6' : ''}`}
                                        >
                                            {line.account}
                                        </td>
                                        <td className="w-32 py-0.5 text-right tabular-nums">
                                            {line.debit > 0
                                                ? money.format(line.debit)
                                                : ''}
                                        </td>
                                        <td className="w-32 py-0.5 text-right tabular-nums">
                                            {line.credit > 0
                                                ? money.format(line.credit)
                                                : ''}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </li>
                ))}
                {entries.data.length === 0 && (
                    <li className="text-sm text-muted-foreground">
                        No postings yet.
                    </li>
                )}
            </ul>
            <Pagination meta={entries.meta} />
            {open && (
                <JournalDialog
                    accounts={accounts}
                    onClose={() => setOpen(false)}
                />
            )}
        </div>
    );
}

Journal.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: overview() },
        { title: 'Journal', href: journal() },
    ],
};
