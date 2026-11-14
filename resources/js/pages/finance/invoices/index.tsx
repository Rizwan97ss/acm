import { Link } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import type { Column } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import Pagination from '@/components/data/pagination';
import SearchInput from '@/components/data/search-input';
import InvoiceStatus from '@/components/finance/invoice-status';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { NativeSelect } from '@/components/ui/native-select';
import { useFormat } from '@/hooks/use-format';
import { useMoney } from '@/hooks/use-money';
import { useTableFilters } from '@/hooks/use-table-filters';
import { create, index, show } from '@/routes/finance/invoices';
import type {
    EnumOption,
    InvoiceSummary,
    Paginated,
    SectionOption,
} from '@/types';

type Props = {
    invoices: Pick<Paginated<InvoiceSummary>, 'data' | 'meta'>;
    summary: { billed: number; collected: number };
    filters: {
        search?: string;
        status?: string;
        section_id?: number;
        overdue?: boolean;
    };
    statuses: EnumOption[];
    sections: SectionOption[];
    can: { create: boolean };
};

export default function InvoicesIndex({
    invoices,
    summary,
    filters,
    statuses,
    sections,
    can,
}: Props) {
    const money = useMoney();
    const format = useFormat();
    const { values, update } = useTableFilters(index.url(), {
        search: filters.search ?? '',
        status: filters.status ?? '',
        section_id: filters.section_id ?? '',
        overdue: filters.overdue ? 1 : '',
    });
    const collectionRate =
        summary.billed > 0
            ? Math.round((summary.collected / summary.billed) * 100)
            : null;

    const columns: Column<InvoiceSummary>[] = [
        {
            key: 'number',
            header: 'Invoice',
            cell: (invoice) => (
                <Link
                    href={show(invoice.id)}
                    className="block min-w-44 hover:underline"
                >
                    <span className="block font-mono text-xs text-muted-foreground">
                        {invoice.number}
                    </span>
                    <span className="block font-medium">{invoice.title}</span>
                </Link>
            ),
        },
        {
            key: 'student',
            header: 'Student',
            cell: (invoice) => (
                <div>
                    <p>{invoice.student.name}</p>
                    <p className="font-mono text-xs text-muted-foreground">
                        {invoice.student.admission_no}
                    </p>
                </div>
            ),
        },
        {
            key: 'due',
            header: 'Due',
            className: 'whitespace-nowrap',
            cell: (invoice) => format.date(`${invoice.due_on}T12:00:00Z`),
        },
        {
            key: 'total',
            header: 'Total',
            className: 'text-right tabular-nums',
            headerClassName: 'text-right',
            cell: (invoice) => money.format(invoice.total),
        },
        {
            key: 'balance',
            header: 'Balance',
            className: 'text-right font-semibold tabular-nums',
            headerClassName: 'text-right',
            cell: (invoice) => money.format(invoice.balance),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (invoice) => (
                <InvoiceStatus
                    status={invoice.status}
                    label={invoice.status_label}
                    overdue={invoice.overdue}
                />
            ),
        },
    ];

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Invoices"
                description="Student fee invoices and what is still owed."
                actions={
                    can.create && (
                        <Button
                            asChild
                            className="bg-gradient-to-r from-emerald-600 to-teal-600 text-white"
                        >
                            <Link href={create()}>
                                <Sparkles aria-hidden /> Generate invoices
                            </Link>
                        </Button>
                    )
                }
            />
            <section aria-label="Totals" className="grid gap-4 sm:grid-cols-3">
                <StatCard
                    label="Billed"
                    value={money.format(summary.billed)}
                    tone="violet"
                />
                <StatCard
                    label="Collected"
                    value={money.format(summary.collected)}
                    hint={
                        collectionRate !== null
                            ? `${collectionRate}% collected`
                            : null
                    }
                    tone="emerald"
                />
                <StatCard
                    label="Outstanding"
                    value={money.format(summary.billed - summary.collected)}
                    tone="orange"
                />
            </section>
            <div className="flex flex-col gap-2 md:flex-row md:items-center">
                <SearchInput
                    value={values.search}
                    onChange={(search) =>
                        update({ search }, { debounce: true })
                    }
                    placeholder="Invoice no., title or student"
                />
                <NativeSelect
                    aria-label="Status"
                    value={values.status}
                    onChange={(e) => update({ status: e.target.value })}
                    className="md:w-40"
                >
                    <option value="">Any status</option>
                    {statuses.map((status) => (
                        <option key={status.value} value={status.value}>
                            {status.label}
                        </option>
                    ))}
                </NativeSelect>
                {sections.length > 0 && (
                    <NativeSelect
                        aria-label="Section"
                        value={values.section_id}
                        onChange={(e) => update({ section_id: e.target.value })}
                        className="md:w-52"
                    >
                        <option value="">All sections</option>
                        {sections.map((section) => (
                            <option key={section.id} value={section.id}>
                                {section.name}
                            </option>
                        ))}
                    </NativeSelect>
                )}
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        checked={values.overdue === 1}
                        onCheckedChange={(checked) =>
                            update({ overdue: checked === true ? 1 : '' })
                        }
                    />
                    Overdue only
                </label>
            </div>
            <DataTable
                caption="Invoices"
                rows={invoices.data}
                columns={columns}
                rowKey={(invoice) => invoice.id}
                empty="No invoices found."
            />
            <Pagination meta={invoices.meta} />
        </div>
    );
}

InvoicesIndex.layout = {
    breadcrumbs: [{ title: 'Invoices', href: index() }],
};
