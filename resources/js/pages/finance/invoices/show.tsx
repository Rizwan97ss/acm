import { Link, useForm } from '@inertiajs/react';
import { Ban, Printer, Undo2, Wallet } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InvoiceStatus from '@/components/finance/invoice-status';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { index, voidMethod as voidInvoice } from '@/routes/finance/invoices';
import {
    receipt,
    reverse,
    store as storePayment,
} from '@/routes/finance/payments';
import type { EnumOption, InvoiceSummary } from '@/types';

type InvoiceDetail = InvoiceSummary & {
    subtotal: number;
    discount: number;
    void_reason: string | null;
    lines: {
        id: number;
        description: string;
        amount: number;
        discount: number;
    }[];
    payments: {
        id: number;
        receipt_no: string;
        amount: number;
        method: string;
        reference: string | null;
        paid_on: string;
        received_by: string | null;
        reversed: boolean;
        reversal_reason: string | null;
    }[];
};

type Props = {
    invoice: InvoiceDetail;
    methods: EnumOption[];
    can: { pay: boolean; reverse: boolean; void: boolean };
};

function PaymentDialog({
    invoice,
    methods,
    onClose,
}: {
    invoice: InvoiceDetail;
    methods: EnumOption[];
    onClose: () => void;
}) {
    const money = useMoney();
    const form = useForm({
        amount: money.toMajor(invoice.balance),
        method: 'cash',
        paid_on: new Date().toISOString().slice(0, 10),
        reference: '',
        notes: '',
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.post(storePayment.url(invoice.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle>Record payment</DialogTitle>
                <DialogDescription>
                    Balance due {money.format(invoice.balance)}
                </DialogDescription>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="pay-amount"
                        label={`Amount (${money.currency})`}
                        error={form.errors.amount}
                        required
                    >
                        <Input
                            id="pay-amount"
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
                        id="pay-method"
                        label="Method"
                        error={form.errors.method}
                        required
                    >
                        <NativeSelect
                            id="pay-method"
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
                        id="pay-date"
                        label="Date"
                        error={form.errors.paid_on}
                        required
                    >
                        <Input
                            id="pay-date"
                            type="date"
                            value={form.data.paid_on}
                            onChange={(e) =>
                                form.setData('paid_on', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="pay-reference"
                        label="Reference"
                        error={form.errors.reference}
                    >
                        <Input
                            id="pay-reference"
                            value={form.data.reference}
                            onChange={(e) =>
                                form.setData('reference', e.target.value)
                            }
                            placeholder="Cheque or transfer no."
                        />
                    </FormField>
                    <FormField
                        id="pay-notes"
                        label="Notes"
                        error={form.errors.notes}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="pay-notes"
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                    </FormField>
                    <div className="flex justify-end sm:col-span-2">
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="bg-gradient-to-r from-emerald-600 to-teal-600 text-white"
                        >
                            Record payment
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ReasonDialog({
    title,
    description,
    action,
    onClose,
}: {
    title: string;
    description: string;
    action: { url: string };
    onClose: () => void;
}) {
    const form = useForm({ reason: '' });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.post(action.url, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>
                <form onSubmit={submit} className="space-y-4">
                    <FormField
                        id="reason"
                        label="Reason"
                        error={form.errors.reason}
                        required
                    >
                        <Input
                            id="reason"
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <div className="flex justify-end">
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing}
                        >
                            Confirm
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function ShowInvoice({ invoice, methods, can }: Props) {
    const money = useMoney();
    const format = useFormat();
    const day = (value: string): string => format.date(`${value}T12:00:00Z`);
    const [dialog, setDialog] = useState<
        | { kind: 'pay' }
        | { kind: 'void' }
        | { kind: 'reverse'; paymentId: number }
        | null
    >(null);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={invoice.title}
                description={`${invoice.number} · ${invoice.student.name} (${invoice.student.admission_no}) · due ${day(invoice.due_on)}`}
                actions={
                    <>
                        <InvoiceStatus
                            status={invoice.status}
                            label={invoice.status_label}
                            overdue={invoice.overdue}
                        />
                        {can.pay && (
                            <Button
                                onClick={() => setDialog({ kind: 'pay' })}
                                className="bg-gradient-to-r from-emerald-600 to-teal-600 text-white"
                            >
                                <Wallet aria-hidden /> Record payment
                            </Button>
                        )}
                        {can.void && invoice.paid === 0 && (
                            <Button
                                variant="outline"
                                onClick={() => setDialog({ kind: 'void' })}
                            >
                                <Ban aria-hidden /> Void
                            </Button>
                        )}
                    </>
                }
            />
            {invoice.status === 'void' && invoice.void_reason && (
                <p className="rounded-xl bg-muted px-4 py-3 text-sm">
                    Voided: {invoice.void_reason}
                </p>
            )}
            <section aria-label="Amounts" className="grid gap-4 sm:grid-cols-3">
                <StatCard
                    label="Total"
                    value={money.format(invoice.total)}
                    hint={
                        invoice.discount > 0
                            ? `after ${money.format(invoice.discount)} concession`
                            : null
                    }
                    tone="violet"
                />
                <StatCard
                    label="Paid"
                    value={money.format(invoice.paid)}
                    tone="emerald"
                />
                <StatCard
                    label="Balance"
                    value={money.format(invoice.balance)}
                    tone={invoice.balance > 0 ? 'orange' : 'sky'}
                />
            </section>
            <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Charges</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <table className="w-full text-sm">
                            <caption className="sr-only">Invoice lines</caption>
                            <tbody className="divide-y">
                                {invoice.lines.map((line) => (
                                    <tr key={line.id}>
                                        <td className="py-2">
                                            {line.description}
                                            {line.discount > 0 && (
                                                <span className="ml-2 text-xs text-emerald-700 dark:text-emerald-400">
                                                    −
                                                    {money.format(
                                                        line.discount,
                                                    )}{' '}
                                                    concession
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-2 text-right tabular-nums">
                                            {money.format(
                                                line.amount - line.discount,
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                <tr className="font-semibold">
                                    <td className="py-2">Total</td>
                                    <td className="py-2 text-right tabular-nums">
                                        {money.format(invoice.total)}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Payments</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="divide-y">
                            {invoice.payments.map((payment) => (
                                <li
                                    key={payment.id}
                                    className="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm"
                                >
                                    <div>
                                        <p className="font-medium tabular-nums">
                                            {money.format(payment.amount)}{' '}
                                            <span className="font-normal text-muted-foreground">
                                                · {payment.method}
                                            </span>
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {payment.receipt_no} ·{' '}
                                            {day(payment.paid_on)}
                                            {payment.received_by
                                                ? ` · ${payment.received_by}`
                                                : ''}
                                        </p>
                                        {payment.reversed && (
                                            <p className="text-xs text-rose-600">
                                                Reversed:{' '}
                                                {payment.reversal_reason}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-1">
                                        {payment.reversed ? (
                                            <TonePill tone="neutral">
                                                Reversed
                                            </TonePill>
                                        ) : (
                                            <>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={receipt(
                                                            payment.id,
                                                        )}
                                                        aria-label={`Receipt ${payment.receipt_no}`}
                                                    >
                                                        <Printer aria-hidden />
                                                    </Link>
                                                </Button>
                                                {can.reverse && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        aria-label={`Reverse ${payment.receipt_no}`}
                                                        onClick={() =>
                                                            setDialog({
                                                                kind: 'reverse',
                                                                paymentId:
                                                                    payment.id,
                                                            })
                                                        }
                                                    >
                                                        <Undo2 aria-hidden />
                                                    </Button>
                                                )}
                                            </>
                                        )}
                                    </div>
                                </li>
                            ))}
                            {invoice.payments.length === 0 && (
                                <li className="py-4 text-sm text-muted-foreground">
                                    No payments yet.
                                </li>
                            )}
                        </ul>
                    </CardContent>
                </Card>
            </div>
            {dialog?.kind === 'pay' && (
                <PaymentDialog
                    invoice={invoice}
                    methods={methods}
                    onClose={() => setDialog(null)}
                />
            )}
            {dialog?.kind === 'void' && (
                <ReasonDialog
                    title="Void invoice"
                    description="The charge is removed and reversed in the ledger. This cannot be undone."
                    action={{ url: voidInvoice.url(invoice.id) }}
                    onClose={() => setDialog(null)}
                />
            )}
            {dialog?.kind === 'reverse' && (
                <ReasonDialog
                    title="Reverse payment"
                    description="The amount is added back to the balance and reversed in the ledger."
                    action={{ url: reverse.url(dialog.paymentId) }}
                    onClose={() => setDialog(null)}
                />
            )}
        </div>
    );
}

ShowInvoice.layout = {
    breadcrumbs: [{ title: 'Invoices', href: index() }],
};
