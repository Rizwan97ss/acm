import { Link, router } from '@inertiajs/react';
import { BookText } from 'lucide-react';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useMoney } from '@/hooks/use-money';
import { cn } from '@/lib/utils';
import { journal, overview } from '@/routes/finance/accounting';

type Balance = {
    code: string;
    name: string;
    type: string;
    debit: number;
    credit: number;
    balance: number;
};

type Props = {
    filters: { from: string; to: string };
    trialBalance: Balance[];
    income: Balance[];
    expenses: Balance[];
    totals: {
        income: number;
        expenses: number;
        net: number;
        receivable: number;
    };
    monthly: { month: string; income: number; expenses: number }[];
};

/**
 * Two series compared side by side per month: income (emerald) and expenses (rose),
 * each labelled in the legend and the accessible table.
 */
function MonthlyChart({ monthly }: { monthly: Props['monthly'] }) {
    const money = useMoney();
    const max = Math.max(
        1,
        ...monthly.flatMap((month) => [month.income, month.expenses]),
    );
    const label = (month: string): string =>
        new Date(`${month}-01T12:00:00Z`).toLocaleDateString(undefined, {
            month: 'short',
        });

    return (
        <figure>
            <figcaption className="mb-3 flex gap-4 text-xs text-muted-foreground">
                <span className="flex items-center gap-1.5">
                    <span
                        className="size-2.5 rounded-sm bg-emerald-500"
                        aria-hidden
                    />{' '}
                    Income
                </span>
                <span className="flex items-center gap-1.5">
                    <span
                        className="size-2.5 rounded-sm bg-rose-500"
                        aria-hidden
                    />{' '}
                    Expenses
                </span>
            </figcaption>
            <div className="flex h-48 items-end gap-3 border-b" aria-hidden>
                {monthly.map((month) => (
                    <div
                        key={month.month}
                        className="group relative flex h-full flex-1 items-end justify-center gap-0.5"
                    >
                        <div
                            className="w-1/3 rounded-t-[4px] bg-gradient-to-t from-emerald-600 to-emerald-400"
                            style={{
                                height: `${(Math.max(0, month.income) / max) * 100}%`,
                            }}
                        />
                        <div
                            className="w-1/3 rounded-t-[4px] bg-gradient-to-t from-rose-600 to-rose-400"
                            style={{
                                height: `${(Math.max(0, month.expenses) / max) * 100}%`,
                            }}
                        />
                        <div className="pointer-events-none absolute -top-2 left-1/2 z-10 hidden -translate-x-1/2 -translate-y-full rounded-md border bg-popover px-2 py-1 text-xs whitespace-nowrap shadow-md group-hover:block">
                            <p className="font-medium">{label(month.month)}</p>
                            <p>Income {money.format(month.income)}</p>
                            <p>Expenses {money.format(month.expenses)}</p>
                        </div>
                    </div>
                ))}
            </div>
            <div
                className="mt-1.5 flex gap-3 text-center text-xs text-muted-foreground"
                aria-hidden
            >
                {monthly.map((month) => (
                    <span key={month.month} className="flex-1">
                        {label(month.month)}
                    </span>
                ))}
            </div>
            <table className="sr-only">
                <caption>Income and expenses by month</caption>
                <tbody>
                    {monthly.map((month) => (
                        <tr key={month.month}>
                            <th scope="row">{month.month}</th>
                            <td>Income {money.format(month.income)}</td>
                            <td>Expenses {money.format(month.expenses)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </figure>
    );
}

export default function AccountingOverview({
    filters,
    trialBalance,
    income,
    expenses,
    totals,
    monthly,
}: Props) {
    const money = useMoney();
    const visit = (changes: Partial<Props['filters']>): void =>
        router.get(
            overview.url(),
            { ...filters, ...changes },
            { preserveScroll: true, replace: true },
        );
    const debitTotal = trialBalance.reduce((sum, row) => sum + row.debit, 0);
    const creditTotal = trialBalance.reduce((sum, row) => sum + row.credit, 0);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Accounting"
                description="Figures come from the double-entry ledger posted by invoices, payments, expenses and payroll."
                actions={
                    <Button variant="outline" asChild>
                        <Link href={journal()}>
                            <BookText aria-hidden /> Journal
                        </Link>
                    </Button>
                }
            />
            <div className="flex flex-col gap-2 sm:flex-row">
                <Input
                    type="date"
                    aria-label="From"
                    value={filters.from}
                    onChange={(e) =>
                        e.target.value && visit({ from: e.target.value })
                    }
                    className="sm:w-44"
                />
                <Input
                    type="date"
                    aria-label="To"
                    value={filters.to}
                    onChange={(e) =>
                        e.target.value && visit({ to: e.target.value })
                    }
                    className="sm:w-44"
                />
            </div>
            <section
                aria-label="Summary"
                className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            >
                <StatCard
                    label="Income"
                    value={money.format(totals.income)}
                    tone="emerald"
                />
                <StatCard
                    label="Expenses"
                    value={money.format(totals.expenses)}
                    tone="pink"
                />
                <StatCard
                    label={totals.net >= 0 ? 'Surplus' : 'Deficit'}
                    value={money.format(Math.abs(totals.net))}
                    tone={totals.net >= 0 ? 'sky' : 'orange'}
                />
                <StatCard
                    label="Fees outstanding"
                    value={money.format(totals.receivable)}
                    tone="violet"
                />
            </section>
            <div className="grid gap-4 lg:grid-cols-5">
                <Card className="lg:col-span-3">
                    <CardHeader>
                        <CardTitle>Last six months</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <MonthlyChart monthly={monthly} />
                    </CardContent>
                </Card>
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Income statement</CardTitle>
                        <CardDescription>
                            For the selected period
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-1 text-sm">
                        {[...income, ...expenses].map((row) => (
                            <div
                                key={row.code}
                                className="flex justify-between py-1"
                            >
                                <span>{row.name}</span>
                                <span
                                    className={cn(
                                        'tabular-nums',
                                        row.type === 'expense' &&
                                            'text-rose-700 dark:text-rose-400',
                                    )}
                                >
                                    {row.type === 'expense' ? '−' : ''}
                                    {money.format(row.balance)}
                                </span>
                            </div>
                        ))}
                        <div className="flex justify-between border-t pt-2 font-semibold">
                            <span>Net</span>
                            <span className="tabular-nums">
                                {money.format(totals.net)}
                            </span>
                        </div>
                    </CardContent>
                </Card>
            </div>
            <Card>
                <CardHeader>
                    <CardTitle>Trial balance</CardTitle>
                    <CardDescription>
                        All postings up to {filters.to}. Debits and credits
                        always match.
                    </CardDescription>
                </CardHeader>
                <CardContent className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <caption className="sr-only">Trial balance</caption>
                        <thead className="text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th scope="col" className="py-2 font-medium">
                                    Account
                                </th>
                                <th
                                    scope="col"
                                    className="py-2 text-right font-medium"
                                >
                                    Debit
                                </th>
                                <th
                                    scope="col"
                                    className="py-2 text-right font-medium"
                                >
                                    Credit
                                </th>
                                <th
                                    scope="col"
                                    className="py-2 text-right font-medium"
                                >
                                    Balance
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {trialBalance.map((row) => (
                                <tr key={row.code}>
                                    <td className="py-2">
                                        <span className="font-mono text-xs text-muted-foreground">
                                            {row.code}
                                        </span>{' '}
                                        {row.name}
                                    </td>
                                    <td className="py-2 text-right tabular-nums">
                                        {money.format(row.debit)}
                                    </td>
                                    <td className="py-2 text-right tabular-nums">
                                        {money.format(row.credit)}
                                    </td>
                                    <td className="py-2 text-right font-medium tabular-nums">
                                        {money.format(row.balance)}
                                    </td>
                                </tr>
                            ))}
                            <tr className="font-semibold">
                                <td className="py-2">Total</td>
                                <td className="py-2 text-right tabular-nums">
                                    {money.format(debitTotal)}
                                </td>
                                <td className="py-2 text-right tabular-nums">
                                    {money.format(creditTotal)}
                                </td>
                                <td />
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>
    );
}

AccountingOverview.layout = {
    breadcrumbs: [{ title: 'Accounting', href: overview() }],
};
