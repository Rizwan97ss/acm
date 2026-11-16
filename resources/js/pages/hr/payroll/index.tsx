import { Link, useForm } from '@inertiajs/react';
import { Calculator, Users } from 'lucide-react';
import type { FormEvent } from 'react';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
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
import { payrollTones } from '@/lib/status-tones';
import { index, salaries, show, store } from '@/routes/hr/payroll';

type Run = {
    id: number;
    period: string;
    status: string;
    status_label: string;
    total_gross: number;
    total_deductions: number;
    total_net: number;
    payslips_count: number;
    paid_on: string | null;
};

export default function PayrollIndex({
    runs,
    can,
}: {
    runs: Run[];
    can: { create: boolean; editSalaries: boolean };
}) {
    const money = useMoney();
    const form = useForm({ period: new Date().toISOString().slice(0, 7) });
    const monthName = (period: string): string =>
        new Date(`${period}-01T12:00:00Z`).toLocaleDateString(undefined, {
            month: 'long',
            year: 'numeric',
        });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.post(store.url());
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Payroll"
                description="Monthly payroll: calculate, approve, then mark as paid."
                actions={
                    can.editSalaries && (
                        <Button variant="outline" asChild>
                            <Link href={salaries()}>
                                <Users aria-hidden /> Salaries
                            </Link>
                        </Button>
                    )
                }
            />
            {can.create && (
                <Card>
                    <CardHeader>
                        <CardTitle>Run payroll</CardTitle>
                        <CardDescription>
                            Uses each employee&apos;s salary profile and deducts
                            approved unpaid leave. Re-running a draft
                            recalculates it.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="flex flex-col gap-2 sm:flex-row sm:items-start"
                        >
                            <div>
                                <Input
                                    type="month"
                                    aria-label="Month"
                                    value={form.data.period}
                                    onChange={(e) =>
                                        form.setData('period', e.target.value)
                                    }
                                    className="sm:w-48"
                                    required
                                />
                                {form.errors.period && (
                                    <p className="mt-1 text-sm text-destructive">
                                        {form.errors.period}
                                    </p>
                                )}
                            </div>
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="bg-gradient-to-r from-royal-600 to-crimson-600 text-white"
                            >
                                <Calculator aria-hidden /> Calculate
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            )}
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {runs.map((run) => (
                    <Link
                        key={run.id}
                        href={show(run.id)}
                        className="group rounded-2xl border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
                    >
                        <div className="flex items-start justify-between gap-2">
                            <p className="font-semibold group-hover:underline">
                                {monthName(run.period)}
                            </p>
                            <TonePill
                                tone={payrollTones[run.status] ?? 'neutral'}
                            >
                                {run.status_label}
                            </TonePill>
                        </div>
                        <p className="mt-3 text-2xl font-bold tabular-nums">
                            {money.format(run.total_net)}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            net pay · {run.payslips_count} staff · gross{' '}
                            {money.format(run.total_gross)}
                        </p>
                    </Link>
                ))}
                {runs.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No payroll runs yet. Set salaries first.
                    </p>
                )}
            </div>
        </div>
    );
}

PayrollIndex.layout = {
    breadcrumbs: [{ title: 'Payroll', href: index() }],
};
