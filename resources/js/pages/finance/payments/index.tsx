import { Link, router } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import type { Column } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import Pagination from '@/components/data/pagination';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import type { StatTone } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/hooks/use-format';
import { useMoney } from '@/hooks/use-money';
import { show as showInvoice } from '@/routes/finance/invoices';
import { index, receipt } from '@/routes/finance/payments';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    receipt_no: string;
    invoice_id: number;
    invoice_number: string;
    student: string;
    amount: number;
    method: string;
    paid_on: string;
    received_by: string | null;
};

type Props = {
    payments: Pick<Paginated<Row>, 'data' | 'meta'>;
    byMethod: { method: string; total: number; count: number }[];
    total: number;
    filters: { from: string; to: string };
};

const tones: StatTone[] = ['emerald', 'sky', 'violet', 'orange', 'pink'];

export default function PaymentsIndex({
    payments,
    byMethod,
    total,
    filters,
}: Props) {
    const money = useMoney();
    const format = useFormat();
    const visit = (changes: Partial<Props['filters']>): void =>
        router.get(
            index.url(),
            { ...filters, ...changes },
            { preserveScroll: true, replace: true },
        );

    const columns: Column<Row>[] = [
        {
            key: 'receipt',
            header: 'Receipt',
            cell: (row) => (
                <span className="font-mono text-xs">{row.receipt_no}</span>
            ),
        },
        {
            key: 'date',
            header: 'Date',
            className: 'whitespace-nowrap',
            cell: (row) => format.date(`${row.paid_on}T12:00:00Z`),
        },
        { key: 'student', header: 'Student', cell: (row) => row.student },
        {
            key: 'invoice',
            header: 'Invoice',
            cell: (row) => (
                <Link
                    href={showInvoice(row.invoice_id)}
                    className="font-mono text-xs hover:underline"
                >
                    {row.invoice_number}
                </Link>
            ),
        },
        { key: 'method', header: 'Method', cell: (row) => row.method },
        {
            key: 'amount',
            header: 'Amount',
            className: 'text-right font-semibold tabular-nums',
            headerClassName: 'text-right',
            cell: (row) => money.format(row.amount),
        },
        {
            key: 'print',
            header: '',
            className: 'text-right',
            cell: (row) => (
                <Button variant="ghost" size="sm" asChild>
                    <Link
                        href={receipt(row.id)}
                        aria-label={`Print receipt ${row.receipt_no}`}
                    >
                        <Printer aria-hidden />
                    </Link>
                </Button>
            ),
        },
    ];

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Payments"
                description="Receipts collected in the period. Reversed payments are excluded."
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
                aria-label="Collections"
                className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            >
                <StatCard
                    label="Collected"
                    value={money.format(total)}
                    hint={`${payments.meta.total} receipts`}
                    tone="emerald"
                />
                {byMethod.map((row, i) => (
                    <StatCard
                        key={row.method}
                        label={row.method}
                        value={money.format(row.total)}
                        hint={`${row.count} receipts`}
                        tone={tones[(i + 1) % tones.length]}
                    />
                ))}
            </section>
            <DataTable
                caption="Payments"
                rows={payments.data}
                columns={columns}
                rowKey={(row) => row.id}
                empty="No payments in this period."
            />
            <Pagination meta={payments.meta} />
        </div>
    );
}

PaymentsIndex.layout = {
    breadcrumbs: [{ title: 'Payments', href: index() }],
};
