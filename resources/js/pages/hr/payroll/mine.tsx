import { Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { useMoney } from '@/hooks/use-money';
import { mine, payslip } from '@/routes/hr/payroll';

export default function MyPayslips({
    payslips,
}: {
    payslips: {
        id: number;
        period: string;
        net: number;
        status_label: string;
    }[];
}) {
    const money = useMoney();

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="My payslips"
                description="Approved and paid payslips."
            />
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {payslips.map((slip) => (
                    <Link
                        key={slip.id}
                        href={payslip(slip.id)}
                        className="group flex items-center gap-4 rounded-2xl border bg-card p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <span className="flex size-11 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white">
                            <FileText className="size-5" aria-hidden />
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block font-semibold group-hover:underline">
                                {new Date(
                                    `${slip.period}-01T12:00:00Z`,
                                ).toLocaleDateString(undefined, {
                                    month: 'long',
                                    year: 'numeric',
                                })}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {slip.status_label}
                            </span>
                        </span>
                        <span className="font-bold tabular-nums">
                            {money.format(slip.net)}
                        </span>
                    </Link>
                ))}
                {payslips.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No payslips yet.
                    </p>
                )}
            </div>
        </div>
    );
}

MyPayslips.layout = {
    breadcrumbs: [{ title: 'My payslips', href: mine() }],
};
