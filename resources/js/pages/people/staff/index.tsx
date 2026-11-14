import { Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import ConfirmDelete from '@/components/confirm-delete';
import type { Column } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import Pagination from '@/components/data/pagination';
import SearchInput from '@/components/data/search-input';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { useTableFilters } from '@/hooks/use-table-filters';
import { create, destroy, edit, index } from '@/routes/people/staff';
import type { Employee, EnumOption, Paginated } from '@/types';

type Props = {
    employees: Paginated<Employee>;
    filters: { search?: string; kind?: string; status?: string };
    statuses: EnumOption[];
    teachingOnly: boolean;
    can: { create: boolean };
};

export default function StaffIndex({
    employees,
    filters,
    statuses,
    teachingOnly,
    can,
}: Props) {
    const { values, update } = useTableFilters(index.url(), {
        search: filters.search ?? '',
        kind: filters.kind ?? '',
        status: filters.status ?? '',
    });

    const columns: Column<Employee>[] = [
        {
            key: 'name',
            header: 'Name',
            cell: (employee) => (
                <div className="min-w-48">
                    <p className="font-medium">{employee.full_name}</p>
                    <p className="font-mono text-xs text-muted-foreground">
                        {employee.employee_no}
                    </p>
                </div>
            ),
        },
        {
            key: 'role',
            header: 'Role',
            cell: (employee) => (
                <div>
                    <p>{employee.designation}</p>
                    <p className="text-xs text-muted-foreground">
                        {employee.department ?? '—'}
                    </p>
                </div>
            ),
        },
        {
            key: 'type',
            header: 'Type',
            cell: (employee) => (
                <Badge variant="outline">
                    {employee.is_teaching ? 'Teaching' : 'Non-teaching'}
                </Badge>
            ),
        },
        {
            key: 'employment',
            header: 'Employment',
            cell: (employee) => employee.employment_type_label,
        },
        {
            key: 'status',
            header: 'Status',
            cell: (employee) => (
                <Badge
                    variant={
                        employee.status === 'active' ? 'secondary' : 'outline'
                    }
                >
                    {employee.status_label}
                </Badge>
            ),
        },
        {
            key: 'account',
            header: 'Account',
            cell: (employee) =>
                employee.user ? (
                    <span className="text-xs">{employee.user.email}</span>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'actions',
            header: '',
            className: 'text-right',
            cell: (employee) => (
                <div className="flex justify-end gap-1">
                    {employee.can.update && (
                        <Button variant="ghost" size="sm" asChild>
                            <Link
                                href={edit(employee.id)}
                                aria-label={`Edit ${employee.full_name}`}
                            >
                                <Pencil aria-hidden />
                            </Link>
                        </Button>
                    )}
                    {employee.can.delete && (
                        <ConfirmDelete
                            action={destroy(employee.id)}
                            label="Archive"
                            title={`Archive ${employee.full_name}?`}
                            description="The record is kept for history but hidden from the directory."
                        />
                    )}
                </div>
            ),
        },
    ];

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={teachingOnly ? 'Teachers' : 'Staff'}
                description={
                    teachingOnly
                        ? 'Teaching staff of the school.'
                        : 'Teaching and non-teaching staff.'
                }
                actions={
                    can.create && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus aria-hidden /> New staff member
                            </Link>
                        </Button>
                    )
                }
            />
            <div className="flex flex-col gap-2 md:flex-row">
                <SearchInput
                    value={values.search}
                    onChange={(search) =>
                        update({ search }, { debounce: true })
                    }
                    placeholder="Name, number or designation"
                />
                {!teachingOnly && (
                    <NativeSelect
                        aria-label="Filter by type"
                        value={values.kind}
                        onChange={(e) => update({ kind: e.target.value })}
                        className="md:w-48"
                    >
                        <option value="">All staff</option>
                        <option value="teaching">Teaching</option>
                        <option value="non_teaching">Non-teaching</option>
                    </NativeSelect>
                )}
                <NativeSelect
                    aria-label="Filter by status"
                    value={values.status}
                    onChange={(e) => update({ status: e.target.value })}
                    className="md:w-44"
                >
                    <option value="">Any status</option>
                    {statuses.map((status) => (
                        <option key={status.value} value={status.value}>
                            {status.label}
                        </option>
                    ))}
                </NativeSelect>
            </div>
            <DataTable
                caption="Staff"
                rows={employees.data}
                columns={columns}
                rowKey={(employee) => employee.id}
                empty="No staff found."
            />
            <Pagination meta={employees.meta} />
        </div>
    );
}

StaffIndex.layout = {
    breadcrumbs: [{ title: 'Staff', href: index() }],
};
