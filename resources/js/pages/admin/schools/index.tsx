import { Link, router } from '@inertiajs/react';
import { ArrowRightLeft, Building2, Pencil, Plus } from 'lucide-react';
import SchoolSwitchController from '@/actions/App/Http/Controllers/Admin/SchoolSwitchController';
import ConfirmDelete from '@/components/confirm-delete';
import type { Column } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import Pagination from '@/components/data/pagination';
import SearchInput from '@/components/data/search-input';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { useTableFilters } from '@/hooks/use-table-filters';
import { create, destroy, edit, index } from '@/routes/admin/schools';
import type { Paginated, School } from '@/types';

type Props = {
    schools: Paginated<School>;
    filters: { search?: string; status?: string };
    can: { create: boolean };
};

export default function SchoolsIndex({ schools, filters, can }: Props) {
    const { values, update } = useTableFilters(index.url(), {
        search: filters.search ?? '',
        status: filters.status ?? '',
    });

    const columns: Column<School>[] = [
        {
            key: 'name',
            header: 'School',
            cell: (school) => (
                <div className="flex min-w-56 items-center gap-3">
                    {school.logo_url ? (
                        <img
                            src={school.logo_url}
                            alt=""
                            className="size-9 rounded-md border object-cover"
                        />
                    ) : (
                        <span className="flex size-9 items-center justify-center rounded-md bg-muted">
                            <Building2
                                className="size-4 text-muted-foreground"
                                aria-hidden
                            />
                        </span>
                    )}
                    <div>
                        <p className="font-medium">{school.name}</p>
                        <p className="text-xs text-muted-foreground">
                            {school.parent
                                ? `Branch of ${school.parent.name}`
                                : school.code}
                        </p>
                    </div>
                </div>
            ),
        },
        {
            key: 'contact',
            header: 'Contact',
            cell: (school) => (
                <span className="text-muted-foreground">
                    {school.email ?? school.phone ?? '—'}
                </span>
            ),
        },
        {
            key: 'users',
            header: 'Users',
            cell: (school) => (
                <span className="tabular-nums">{school.users_count}</span>
            ),
        },
        {
            key: 'branches',
            header: 'Branches',
            cell: (school) => (
                <span className="tabular-nums">{school.branches_count}</span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (school) => <StatusBadge active={school.is_active} />,
        },
        {
            key: 'actions',
            header: '',
            className: 'text-right',
            cell: (school) => (
                <div className="flex justify-end gap-1">
                    {school.can.switch && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                router.visit(
                                    SchoolSwitchController.store(school.id),
                                )
                            }
                        >
                            <ArrowRightLeft aria-hidden />
                            <span className="sr-only sm:not-sr-only">
                                Work here
                            </span>
                        </Button>
                    )}
                    {school.can.update && (
                        <Button variant="ghost" size="sm" asChild>
                            <Link
                                href={edit(school.id)}
                                aria-label={`Edit ${school.name}`}
                            >
                                <Pencil aria-hidden />
                            </Link>
                        </Button>
                    )}
                    {school.can.delete && (
                        <ConfirmDelete
                            action={destroy(school.id)}
                            title={`Delete ${school.name}?`}
                            description="Only schools without users or branches can be deleted. Deactivate it otherwise."
                        />
                    )}
                </div>
            ),
        },
    ];

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Schools & branches"
                description="Every school keeps its own users, roles, settings and records."
                actions={
                    can.create && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus aria-hidden /> New school
                            </Link>
                        </Button>
                    )
                }
            />
            <div className="flex flex-col gap-2 sm:flex-row">
                <SearchInput
                    value={values.search}
                    onChange={(search) =>
                        update({ search }, { debounce: true })
                    }
                    placeholder="Name or code"
                />
                <NativeSelect
                    aria-label="Filter by status"
                    value={values.status}
                    onChange={(event) => update({ status: event.target.value })}
                    className="sm:w-40"
                >
                    <option value="">Any status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </NativeSelect>
            </div>
            <DataTable
                caption="Schools"
                rows={schools.data}
                columns={columns}
                rowKey={(school) => school.id}
                empty="No schools found."
            />
            <Pagination meta={schools.meta} />
        </div>
    );
}

SchoolsIndex.layout = {
    breadcrumbs: [{ title: 'Schools', href: index() }],
};
