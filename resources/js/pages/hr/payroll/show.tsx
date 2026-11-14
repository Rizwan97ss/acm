import { Link, router, useForm } from '@inertiajs/react';
import { BadgeCheck, Banknote, FileText, RefreshCw } from 'lucide-react';
import type { FormEvent } from 'react';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useMoney } from '@/hooks/use-money';
import { payrollTones } from '@/lib/status-tones';
import { approve, index, pay, payslip, store } from '@/routes/hr/payroll';

type Props = {
    run: {
        id: number;
        period: string;
        status: string;
        status_label: string;
        total_gross: number;
        total_deductions: number;
        total_net: number;
        paid_on: string | null;
    };
    payslips: {
        id: number;
        employee: string;
        employee_no: string;
        designation: string;
        gross: number;
        deductions: number;
        net: number;
        unpaid_leave_days: number;
    }[];
    can: { recalculate: boolean; approve: boolean; pay: boolean };
};

export default function PayrollRun({ run, payslips, can }: Props) {
    const money = useMoney();
    const payForm = useForm({ paid_on: new Date().toISOString().slice(0, 10) });
    const month = new Date(`${run.period}-01T12:00:00Z`).toLocaleDateString(
        undefined,
        { month: 'long', year: 'numeric' },
    );

    const markPaid = (event: FormEvent): void => {
        event.preventDefault();
        payForm.post(pay.url(run.id), { preserveScroll: true });
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={`Payroll · ${month}`}
                description={
                    run.paid_on
                        ? `Paid on ${run.paid_on}`
                        : 'Payslips become visible to staff once approved.'
                }
                actions={
                    <>
                        <TonePill tone={payrollTones[run.status] ?? 'neutral'}>
                            {run.status_label}
                        </TonePill>
                        {can.recalculate && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(store.url(), {
                                        period: run.period,
                                    })
                                }
                            >
                                <RefreshCw aria-hidden /> Recalculate
                            </Button>
                        )}
                        {can.approve && (
                            <Button
                                onClick={() =>
                                    router.post(
                                        approve.url(run.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                                className="bg-gradient-to-r from-sky-600 to-indigo-600 text-white"
                            >
                                <BadgeCheck aria-hidden /> Approve
                            </Button>
                        )}
                    </>
                }
            />
            <section aria-label="Totals" className="grid gap-4 sm:grid-cols-3">
                <StatCard
                    label="Gross pay"
                    value={money.format(run.total_gross)}
                    tone="violet"
                />
                <StatCard
                    label="Deductions"
                    value={money.format(run.total_deductions)}
                    tone="orange"
                />
                <StatCard
                    label="Net pay"
                    value={money.format(run.total_net)}
                    hint={`${payslips.length} staff`}
                    tone="emerald"
                />
            </section>
            {can.pay && (
                <form
                    onSubmit={markPaid}
                    className="flex flex-col gap-2 rounded-2xl border bg-card p-4 shadow-sm sm:flex-row sm:items-center"
                >
                    <span className="text-sm font-medium">
                        Salaries transferred on
                    </span>
                    <Input
                        type="date"
                        aria-label="Paid on"
                        value={payForm.data.paid_on}
                        onChange={(e) =>
                            payForm.setData('paid_on', e.target.value)
                        }
                        className="sm:w-44"
                        required
                    />
                    <Button
                        type="submit"
                        disabled={payForm.processing}
                        className="bg-gradient-to-r from-emerald-600 to-teal-600 text-white"
                    >
                        <Banknote aria-hidden /> Mark as paid
                    </Button>
                </form>
            )}
            <div className="overflow-x-auto rounded-2xl border bg-card shadow-sm">
                <table className="w-full min-w-[640px] text-sm">
                    <caption className="sr-only">Payslips</caption>
                    <thead className="bg-gradient-to-r from-indigo-50 to-fuchsia-50 text-left text-xs text-indigo-950/70 uppercase dark:from-indigo-500/10 dark:to-fuchsia-500/10 dark:text-indigo-100/80">
                        <tr>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Employee
                            </th>
                            <th
                                scope="col"
                                className="px-4 py-3 text-right font-medium"
                            >
                                Gross
                            </th>
                            <th
                                scope="col"
                                className="px-4 py-3 text-right font-medium"
                            >
                                Deductions
                            </th>
                            <th
                                scope="col"
                                className="px-4 py-3 text-right font-medium"
                            >
                                Net
                            </th>
                            <th scope="col" className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {payslips.map((slip) => (
                            <tr key={slip.id}>
                                <td className="px-4 py-2.5">
                                    <p className="font-medium">
                                        {slip.employee}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {slip.employee_no} · {slip.designation}
                                        {slip.unpaid_leave_days > 0 &&
                                            ` · ${slip.unpaid_leave_days} unpaid leave days`}
                                    </p>
                                </td>
                                <td className="px-4 py-2.5 text-right tabular-nums">
                                    {money.format(slip.gross)}
                                </td>
                                <td className="px-4 py-2.5 text-right text-rose-700 tabular-nums dark:text-rose-400">
                                    {money.format(slip.deductions)}
                                </td>
                                <td className="px-4 py-2.5 text-right font-semibold tabular-nums">
                                    {money.format(slip.net)}
                                </td>
                                <td className="px-4 py-2.5 text-right">
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link
                                            href={payslip(slip.id)}
                                            aria-label={`Payslip for ${slip.employee}`}
                                        >
                                            <FileText aria-hidden />
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                        ))}
                        {payslips.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="px-4 py-8 text-center text-muted-foreground"
                                >
                                    No active staff have a salary profile.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

PayrollRun.layout = {
    breadcrumbs: [{ title: 'Payroll', href: index() }],
};
