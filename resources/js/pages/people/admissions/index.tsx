import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { Column } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import Pagination from '@/components/data/pagination';
import SearchInput from '@/components/data/search-input';
import PageHeader from '@/components/page-header';
import AdmissionStatusBadge from '@/components/people/admission-status-badge';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/hooks/use-format';
import { useTableFilters } from '@/hooks/use-table-filters';
import { cn } from '@/lib/utils';
import { create, index, show } from '@/routes/people/admissions';
import type { AdmissionApplication, EnumOption, Paginated } from '@/types';

type Props = {
    applications: Paginated<AdmissionApplication>;
    filters: { search?: string; status?: string };
    statuses: EnumOption[];
    counts: Record<string, number>;
    can: { create: boolean };
};

export default function AdmissionsIndex({
    applications,
    filters,
    statuses,
    counts,
    can,
}: Props) {
    const format = useFormat();
    const { values, update } = useTableFilters(index.url(), {
        search: filters.search ?? '',
        status: filters.status ?? '',
    });

    const columns: Column<AdmissionApplication>[] = [
        {
            key: 'applicant',
            header: 'Applicant',
            cell: (application) => (
                <Link
                    href={show(application.id)}
                    className="block min-w-48 hover:underline"
                >
                    <span className="block font-medium">
                        {application.full_name}
                    </span>
                    <span className="block font-mono text-xs text-muted-foreground">
                        {application.application_no}
                    </span>
                </Link>
            ),
        },
        {
            key: 'class',
            header: 'Class',
            cell: (application) => (
                <span>
                    {application.school_class}{' '}
                    <span className="text-muted-foreground">
                        · {application.academic_year}
                    </span>
                </span>
            ),
        },
        {
            key: 'guardian',
            header: 'Guardian',
            cell: (application) => application.guardian_name,
        },
        {
            key: 'received',
            header: 'Received',
            className: 'whitespace-nowrap',
            cell: (application) => format.date(application.created_at),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (application) => (
                <AdmissionStatusBadge
                    status={application.status}
                    label={application.status_label}
                />
            ),
        },
    ];

    const tabs = [{ value: '', label: 'All' }, ...statuses];

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Admissions"
                description="Applications move from pending to approved or rejected, then approved applicants are enrolled."
                actions={
                    can.create && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus aria-hidden /> New application
                            </Link>
                        </Button>
                    )
                }
            />
            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <nav
                    aria-label="Filter by status"
                    className="flex gap-1 overflow-x-auto"
                >
                    {tabs.map((tab) => {
                        const total =
                            tab.value === ''
                                ? Object.values(counts).reduce(
                                      (sum, count) => sum + Number(count),
                                      0,
                                  )
                                : Number(counts[tab.value] ?? 0);
                        const active = values.status === tab.value;

                        return (
                            <button
                                key={tab.value}
                                type="button"
                                aria-pressed={active}
                                onClick={() => update({ status: tab.value })}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm whitespace-nowrap hover:bg-muted',
                                    active && 'bg-muted font-medium',
                                )}
                            >
                                {tab.label}{' '}
                                <span className="text-muted-foreground tabular-nums">
                                    {total}
                                </span>
                            </button>
                        );
                    })}
                </nav>
                <SearchInput
                    value={values.search}
                    onChange={(search) =>
                        update({ search }, { debounce: true })
                    }
                    placeholder="Name, number or guardian"
                />
            </div>
            <DataTable
                caption="Admission applications"
                rows={applications.data}
                columns={columns}
                rowKey={(application) => application.id}
                empty="No applications."
            />
            <Pagination meta={applications.meta} />
        </div>
    );
}

AdmissionsIndex.layout = {
    breadcrumbs: [{ title: 'Admissions', href: index() }],
};
