import { router, useForm } from '@inertiajs/react';
import { Check, Pencil, Plus, X } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import Pagination from '@/components/data/pagination';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
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
import { store as storeCategory } from '@/routes/finance/expense-categories';
import { index, review, store, update } from '@/routes/finance/expenses';
import type { EnumOption, Option, Paginated } from '@/types';

type Expense = {
    id: number;
    number: string;
    payee: string;
    description: string;
    category: string;
    expense_category_id: number;
    amount: number;
    spent_on: string;
    method: string;
    method_label: string;
    reference: string | null;
    status: string;
    status_label: string;
    submitted_by: string | null;
    reviewed_by: string | null;
    review_note: string | null;
    can: { review: boolean; update: boolean };
};

type Props = {
    expenses: Pick<Paginated<Expense>, 'data' | 'meta'>;
    totals: { approved: number; pending: number };
    categories: Option[];
    statuses: EnumOption[];
    methods: EnumOption[];
    filters: { status?: string; category_id?: number; month?: string };
    can: { create: boolean };
};

const statusTone = {
    approved: 'success',
    pending: 'warning',
    rejected: 'danger',
} as const;

function ExpenseDialog({
    expense,
    categories,
    methods,
    onClose,
}: {
    expense: Expense | null;
    categories: Option[];
    methods: EnumOption[];
    onClose: () => void;
}) {
    const money = useMoney();
    const form = useForm({
        expense_category_id:
            expense?.expense_category_id ?? categories[0]?.id ?? '',
        payee: expense?.payee ?? '',
        description: expense?.description ?? '',
        amount: expense ? money.toMajor(expense.amount) : '',
        spent_on: expense?.spent_on ?? new Date().toISOString().slice(0, 10),
        method: expense?.method ?? 'bank_transfer',
        reference: expense?.reference ?? '',
    });
    const [newCategory, setNewCategory] = useState('');

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (expense) {
            form.put(update.url(expense.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogTitle>
                    {expense ? 'Edit expense' : 'Submit expense'}
                </DialogTitle>
                <DialogDescription>
                    Expenses reach the books once someone else approves them.
                </DialogDescription>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="exp-payee"
                        label="Paid to"
                        error={form.errors.payee}
                        required
                    >
                        <Input
                            id="exp-payee"
                            value={form.data.payee}
                            onChange={(e) =>
                                form.setData('payee', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="exp-category"
                        label="Category"
                        error={form.errors.expense_category_id}
                        required
                    >
                        <NativeSelect
                            id="exp-category"
                            value={form.data.expense_category_id}
                            onChange={(e) =>
                                form.setData(
                                    'expense_category_id',
                                    Number(e.target.value),
                                )
                            }
                        >
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField
                        id="exp-description"
                        label="Description"
                        error={form.errors.description}
                        required
                        className="sm:col-span-2"
                    >
                        <Input
                            id="exp-description"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="exp-amount"
                        label={`Amount (${money.currency})`}
                        error={form.errors.amount}
                        required
                    >
                        <Input
                            id="exp-amount"
                            type="number"
                            min={0.01}
                            step="0.01"
                            value={form.data.amount}
                            onChange={(e) =>
                                form.setData('amount', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="exp-date"
                        label="Date"
                        error={form.errors.spent_on}
                        required
                    >
                        <Input
                            id="exp-date"
                            type="date"
                            value={form.data.spent_on}
                            onChange={(e) =>
                                form.setData('spent_on', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="exp-method"
                        label="Paid by"
                        error={form.errors.method}
                        required
                    >
                        <NativeSelect
                            id="exp-method"
                            value={form.data.method}
                            onChange={(e) =>
                                form.setData('method', e.target.value)
                            }
                        >
                            {methods.map((method) => (
                                <option key={method.value} value={method.value}>
                                    {method.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField
                        id="exp-reference"
                        label="Reference"
                        error={form.errors.reference}
                    >
                        <Input
                            id="exp-reference"
                            value={form.data.reference}
                            onChange={(e) =>
                                form.setData('reference', e.target.value)
                            }
                            placeholder="Bill or cheque no."
                        />
                    </FormField>
                    <div className="flex items-end gap-2 sm:col-span-2">
                        <Input
                            aria-label="New category"
                            placeholder="New category…"
                            value={newCategory}
                            onChange={(e) => setNewCategory(e.target.value)}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            disabled={newCategory.trim() === ''}
                            onClick={() =>
                                router.post(
                                    storeCategory.url(),
                                    { name: newCategory },
                                    {
                                        preserveScroll: true,
                                        preserveState: true,
                                        onSuccess: () => setNewCategory(''),
                                    },
                                )
                            }
                        >
                            Add category
                        </Button>
                    </div>
                    <div className="flex justify-end sm:col-span-2">
                        <Button type="submit" disabled={form.processing}>
                            {expense ? 'Save changes' : 'Submit expense'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function ExpensesIndex({
    expenses,
    totals,
    categories,
    statuses,
    methods,
    filters,
    can,
}: Props) {
    const money = useMoney();
    const format = useFormat();
    const [editing, setEditing] = useState<Expense | 'new' | null>(null);
    const visit = (changes: Partial<Props['filters']>): void =>
        router.get(
            index.url(),
            Object.fromEntries(
                Object.entries({ ...filters, ...changes }).filter(
                    ([, value]) => value !== '' && value !== undefined,
                ),
            ),
            { preserveScroll: true, replace: true },
        );

    const decide = (
        expense: Expense,
        decision: 'approved' | 'rejected',
    ): void => {
        const note =
            decision === 'rejected'
                ? window.prompt('Reason for rejecting (optional)')
                : null;
        router.post(
            review.url(expense.id),
            { decision, note },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Expenses"
                description="Spending submitted for approval, and what has been approved."
                actions={
                    can.create && (
                        <Button onClick={() => setEditing('new')}>
                            <Plus aria-hidden /> Submit expense
                        </Button>
                    )
                }
            />
            <section aria-label="Totals" className="grid gap-4 sm:grid-cols-2">
                <StatCard
                    label="Approved"
                    value={money.format(totals.approved)}
                    tone="pink"
                />
                <StatCard
                    label="Awaiting approval"
                    value={money.format(totals.pending)}
                    tone="orange"
                />
            </section>
            <div className="flex flex-col gap-2 sm:flex-row">
                <NativeSelect
                    aria-label="Status"
                    value={filters.status ?? ''}
                    onChange={(e) => visit({ status: e.target.value })}
                    className="sm:w-44"
                >
                    <option value="">Any status</option>
                    {statuses.map((status) => (
                        <option key={status.value} value={status.value}>
                            {status.label}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect
                    aria-label="Category"
                    value={filters.category_id ?? ''}
                    onChange={(e) =>
                        visit({
                            category_id:
                                e.target.value === ''
                                    ? undefined
                                    : Number(e.target.value),
                        })
                    }
                    className="sm:w-52"
                >
                    <option value="">All categories</option>
                    {categories.map((category) => (
                        <option key={category.id} value={category.id}>
                            {category.name}
                        </option>
                    ))}
                </NativeSelect>
                <Input
                    type="month"
                    aria-label="Month"
                    value={filters.month ?? ''}
                    onChange={(e) => visit({ month: e.target.value })}
                    className="sm:w-44"
                />
            </div>
            <ul className="divide-y overflow-hidden rounded-2xl border bg-card shadow-sm">
                {expenses.data.map((expense) => (
                    <li
                        key={expense.id}
                        className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center"
                    >
                        <div className="min-w-0 flex-1">
                            <p className="font-medium">
                                {expense.payee}{' '}
                                <span className="text-sm font-normal text-muted-foreground">
                                    · {expense.category}
                                </span>
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {expense.description}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {expense.number} ·{' '}
                                {format.date(`${expense.spent_on}T12:00:00Z`)} ·{' '}
                                {expense.method_label}
                                {expense.submitted_by
                                    ? ` · by ${expense.submitted_by}`
                                    : ''}
                                {expense.review_note
                                    ? ` · “${expense.review_note}”`
                                    : ''}
                            </p>
                        </div>
                        <p className="text-lg font-bold tabular-nums">
                            {money.format(expense.amount)}
                        </p>
                        <TonePill
                            tone={
                                statusTone[
                                    expense.status as keyof typeof statusTone
                                ] ?? 'neutral'
                            }
                        >
                            {expense.status_label}
                        </TonePill>
                        <div className="flex gap-1">
                            {expense.can.update && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Edit"
                                    onClick={() => setEditing(expense)}
                                >
                                    <Pencil aria-hidden />
                                </Button>
                            )}
                            {expense.can.review && (
                                <>
                                    <Button
                                        size="sm"
                                        className="bg-emerald-600 text-white hover:bg-emerald-700"
                                        onClick={() =>
                                            decide(expense, 'approved')
                                        }
                                    >
                                        <Check aria-hidden /> Approve
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            decide(expense, 'rejected')
                                        }
                                    >
                                        <X aria-hidden /> Reject
                                    </Button>
                                </>
                            )}
                        </div>
                    </li>
                ))}
                {expenses.data.length === 0 && (
                    <li className="p-8 text-center text-sm text-muted-foreground">
                        No expenses found.
                    </li>
                )}
            </ul>
            <Pagination meta={expenses.meta} />
            {editing && (
                <ExpenseDialog
                    key={editing === 'new' ? 'new' : editing.id}
                    expense={editing === 'new' ? null : editing}
                    categories={categories}
                    methods={methods}
                    onClose={() => setEditing(null)}
                />
            )}
        </div>
    );
}

ExpensesIndex.layout = {
    breadcrumbs: [{ title: 'Expenses', href: index() }],
};
