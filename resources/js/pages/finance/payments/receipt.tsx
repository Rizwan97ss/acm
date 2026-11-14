import { Head, usePage } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/hooks/use-format';
import { useMoney } from '@/hooks/use-money';

type Props = {
    payment: {
        receipt_no: string;
        amount: number;
        method: string;
        reference: string | null;
        paid_on: string;
        received_by: string | null;
        reversed: boolean;
        invoice: {
            number: string;
            title: string;
            total: number;
            balance: number;
        };
        student: { name: string; admission_no: string; section: string | null };
    };
};

export default function Receipt({ payment }: Props) {
    const { tenant, branding } = usePage().props;
    const money = useMoney();
    const format = useFormat();

    return (
        <div className="flex flex-1 flex-col items-center gap-4 p-4 md:p-6 print:p-0">
            <Head title={`Receipt ${payment.receipt_no}`} />
            <div className="flex w-full max-w-xl justify-end print:hidden">
                <Button
                    onClick={() => window.print()}
                    className="bg-gradient-to-r from-emerald-600 to-teal-600 text-white"
                >
                    <Printer aria-hidden /> Print
                </Button>
            </div>
            <article className="w-full max-w-xl overflow-hidden rounded-3xl border bg-card shadow-xl print:rounded-none print:border-0 print:shadow-none">
                <header className="flex items-center gap-3 bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-5 text-white print:bg-none print:text-black">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/30">
                        <AppLogoIcon className="size-6 fill-current" />
                    </span>
                    <div>
                        <p className="font-bold">
                            {tenant?.current?.name ?? branding?.name}
                        </p>
                        <p className="text-sm text-white/85 print:text-black">
                            Payment receipt
                        </p>
                    </div>
                </header>
                <div className="space-y-5 p-6 text-sm">
                    {payment.reversed && (
                        <p className="rounded-lg bg-rose-100 px-3 py-2 font-semibold text-rose-800">
                            This payment has been reversed.
                        </p>
                    )}
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">
                            Receipt no.
                        </span>
                        <span className="font-mono font-semibold">
                            {payment.receipt_no}
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Date</span>
                        <span>
                            {format.date(`${payment.paid_on}T12:00:00Z`)}
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">
                            Received from
                        </span>
                        <span className="text-right">
                            {payment.student.name}
                            <span className="block text-xs text-muted-foreground">
                                {payment.student.admission_no}
                                {payment.student.section
                                    ? ` · ${payment.student.section}`
                                    : ''}
                            </span>
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">For</span>
                        <span className="text-right">
                            {payment.invoice.title}
                            <span className="block font-mono text-xs text-muted-foreground">
                                {payment.invoice.number}
                            </span>
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Method</span>
                        <span>
                            {payment.method}
                            {payment.reference ? ` · ${payment.reference}` : ''}
                        </span>
                    </div>
                    <div className="rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 p-5 text-center text-white print:bg-none print:text-black print:ring-1 print:ring-black/20">
                        <p className="text-xs text-white/85 uppercase print:text-black">
                            Amount received
                        </p>
                        <p className="text-3xl font-bold tabular-nums">
                            {money.format(payment.amount)}
                        </p>
                    </div>
                    <div className="flex justify-between border-t pt-4">
                        <span className="text-muted-foreground">
                            Balance remaining on invoice
                        </span>
                        <span className="font-semibold tabular-nums">
                            {money.format(payment.invoice.balance)}
                        </span>
                    </div>
                    {payment.received_by && (
                        <p className="text-xs text-muted-foreground">
                            Received by {payment.received_by}
                        </p>
                    )}
                </div>
            </article>
        </div>
    );
}
