import { Head, usePage } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { useMoney } from '@/hooks/use-money';
import type { MoneyLine } from '@/types';

type Props = {
    payslip: {
        period: string;
        status_label: string;
        employee: {
            name: string;
            employee_no: string;
            designation: string;
            department: string | null;
        };
        breakdown: {
            basic: number;
            allowances: MoneyLine[];
            deductions: MoneyLine[];
        };
        gross: number;
        deductions: number;
        net: number;
        unpaid_leave_days: number;
    };
};

export default function Payslip({ payslip }: Props) {
    const { tenant, branding } = usePage().props;
    const money = useMoney();
    const month = new Date(`${payslip.period}-01T12:00:00Z`).toLocaleDateString(
        undefined,
        { month: 'long', year: 'numeric' },
    );

    return (
        <div className="flex flex-1 flex-col items-center gap-4 p-4 md:p-6 print:p-0">
            <Head title={`Payslip ${payslip.period}`} />
            <div className="flex w-full max-w-2xl justify-end print:hidden">
                <Button
                    onClick={() => window.print()}
                    className="bg-gradient-to-r from-royal-600 to-crimson-600 text-white"
                >
                    <Printer aria-hidden /> Print
                </Button>
            </div>
            <article className="w-full max-w-2xl overflow-hidden rounded-3xl border bg-card shadow-xl print:rounded-none print:border-0 print:shadow-none">
                <header className="flex items-center gap-3 bg-gradient-to-r from-royal-700 to-crimson-600 px-6 py-5 text-white print:bg-none print:text-black">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/30">
                        <AppLogoIcon className="size-6 fill-current" />
                    </span>
                    <div>
                        <p className="font-bold">
                            {tenant?.current?.name ?? branding?.name}
                        </p>
                        <p className="text-sm text-white/85 print:text-black">
                            Payslip · {month}
                        </p>
                    </div>
                </header>
                <div className="space-y-6 p-6 text-sm">
                    <dl className="grid gap-3 sm:grid-cols-3">
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Employee
                            </dt>
                            <dd className="font-semibold">
                                {payslip.employee.name}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Employee no.
                            </dt>
                            <dd className="font-mono">
                                {payslip.employee.employee_no}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Designation
                            </dt>
                            <dd>
                                {payslip.employee.designation}
                                {payslip.employee.department
                                    ? ` · ${payslip.employee.department}`
                                    : ''}
                            </dd>
                        </div>
                    </dl>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <section className="rounded-2xl bg-emerald-50 p-4 dark:bg-emerald-500/10">
                            <h2 className="mb-2 font-semibold text-emerald-900 dark:text-emerald-200">
                                Earnings
                            </h2>
                            <div className="flex justify-between py-0.5">
                                <span>Basic salary</span>
                                <span className="tabular-nums">
                                    {money.format(payslip.breakdown.basic)}
                                </span>
                            </div>
                            {payslip.breakdown.allowances.map((line) => (
                                <div
                                    key={line.name}
                                    className="flex justify-between py-0.5"
                                >
                                    <span>{line.name}</span>
                                    <span className="tabular-nums">
                                        {money.format(line.amount)}
                                    </span>
                                </div>
                            ))}
                            <div className="mt-2 flex justify-between border-t border-emerald-200 pt-2 font-semibold dark:border-emerald-500/30">
                                <span>Gross</span>
                                <span className="tabular-nums">
                                    {money.format(payslip.gross)}
                                </span>
                            </div>
                        </section>
                        <section className="rounded-2xl bg-rose-50 p-4 dark:bg-rose-500/10">
                            <h2 className="mb-2 font-semibold text-rose-900 dark:text-rose-200">
                                Deductions
                            </h2>
                            {payslip.breakdown.deductions.map((line) => (
                                <div
                                    key={line.name}
                                    className="flex justify-between py-0.5"
                                >
                                    <span>{line.name}</span>
                                    <span className="tabular-nums">
                                        {money.format(line.amount)}
                                    </span>
                                </div>
                            ))}
                            {payslip.breakdown.deductions.length === 0 && (
                                <p className="text-muted-foreground">None</p>
                            )}
                            <div className="mt-2 flex justify-between border-t border-rose-200 pt-2 font-semibold dark:border-rose-500/30">
                                <span>Total</span>
                                <span className="tabular-nums">
                                    {money.format(payslip.deductions)}
                                </span>
                            </div>
                        </section>
                    </div>
                    <div className="rounded-2xl bg-gradient-to-br from-royal-600 to-crimson-600 p-5 text-center text-white print:bg-none print:text-black print:ring-1 print:ring-black/20">
                        <p className="text-xs text-white/85 uppercase print:text-black">
                            Net pay
                        </p>
                        <p className="text-3xl font-bold tabular-nums">
                            {money.format(payslip.net)}
                        </p>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Status: {payslip.status_label}. This payslip is
                        confidential.
                    </p>
                </div>
            </article>
        </div>
    );
}
