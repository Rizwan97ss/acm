import { Link } from '@inertiajs/react';
import { Pencil, Plus, ShieldCheck } from 'lucide-react';
import ConfirmDelete from '@/components/confirm-delete';
import type { Column } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import Pagination from '@/components/data/pagination';
import SearchInput from '@/components/data/search-input';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { useFormat } from '@/hooks/use-format';
import { useTableFilters } from '@/hooks/use-table-filters';
import { create, destroy, edit, index } from '@/routes/admin/users';
import type { ManagedUser, Option, Paginated } from '@/types';

type Filters = {
    search?: string;
    role?: string | number;
    status?: string;
    sort?: string;
    direction?: 'asc' | 'desc';
};

type Props = {
    users: Paginated<ManagedUser>;
    filters: Filters;
    roles: Option[];
    can: { create: boolean };
};

export default function UsersIndex({ users, filters, roles, can }: Props) {
    const format = useFormat();
    const { values, update } = useTableFilters(index.url(), {
        search: filters.search ?? '',
        role: filters.role ?? '',
        status: filters.status ?? '',
        sort: filters.sort ?? '',
        direction: filters.direction ?? '',
    });

    const columns: Column<ManagedUser>[] = [
        {
            key: 'name',
            header: 'Name',
            sortKey: 'name',
            cell: (user) => (
                <div className="min-w-48">
                    <p className="font-medium">{user.name}</p>
                    <p className="text-xs text-muted-foreground">
                        {user.email}
                    </p>
                </div>
            ),
        },
        {
            key: 'phone',
            header: 'Phone',
            cell: (user) => (
                <span className="tabular-nums">{user.phone ?? '—'}</span>
            ),
        },
        {
            key: 'roles',
            header: 'Roles',
            cell: (user) => (
                <div className="flex flex-wrap gap-1">
                    {user.roles?.length
                        ? user.roles.map((role) => (
                              <Badge key={role.id} variant="secondary">
                                  {role.name}
                              </Badge>
                          ))
                        : '—'}
                </div>
            ),
        },
        {
            key: 'school',
            header: 'School',
            cell: (user) =>
                user.school?.name ?? (
                    <span className="text-muted-foreground">Platform</span>
                ),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (user) => (
                <div className="flex items-center gap-1.5">
                    <StatusBadge active={user.is_active} />
                    {user.two_factor_enabled && (
                        <span title="Two-factor enabled">
                            <ShieldCheck
                                className="size-4 text-emerald-600"
                                aria-label="Two-factor enabled"
                            />
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'last_login_at',
            header: 'Last sign-in',
            sortKey: 'last_login_at',
            cell: (user) => format.dateTime(user.last_login_at, 'Never'),
            className: 'whitespace-nowrap',
        },
        {
            key: 'actions',
            header: '',
            className: 'text-right',
            cell: (user) => (
                <div className="flex justify-end gap-1">
                    {user.can.update && (
                        <Button variant="ghost" size="sm" asChild>
                            <Link
                                href={edit(user.id)}
                                aria-label={`Edit ${user.name}`}
                            >
                                <Pencil aria-hidden />
                            </Link>
                        </Button>
                    )}
                    {user.can.delete && (
                        <ConfirmDelete
                            action={destroy(user.id)}
                            title={`Delete ${user.name}?`}
                            description="The account and its role assignments will be permanently removed."
                        />
                    )}
                </div>
            ),
        },
    ];

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Users"
                description="Staff, student and guardian accounts and the roles they hold."
                actions={
                    can.create && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus aria-hidden /> New user
                            </Link>
                        </Button>
                    )
                }
            />

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                <SearchInput
                    value={String(values.search)}
                    onChange={(value) =>
                        update({ search: value }, { debounce: true })
                    }
                    placeholder="Name, email or exact phone"
                />
                <NativeSelect
                    aria-label="Filter by role"
                    value={String(values.role)}
                    onChange={(event) => update({ role: event.target.value })}
                    className="sm:w-48"
                >
                    <option value="">All roles</option>
                    {roles.map((role) => (
                        <option key={role.id} value={role.id}>
                            {role.name}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect
                    aria-label="Filter by status"
                    value={String(values.status)}
                    onChange={(event) => update({ status: event.target.value })}
                    className="sm:w-40"
                >
                    <option value="">Any status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </NativeSelect>
            </div>

            <DataTable
                caption="Users"
                rows={users.data}
                columns={columns}
                rowKey={(user) => user.id}
                empty="No users match these filters."
                sort={{
                    column: values.sort || 'name',
                    direction: (values.direction || 'asc') as 'asc' | 'desc',
                }}
                onSort={(column, direction) =>
                    update({ sort: column, direction })
                }
            />

            <Pagination meta={users.meta} />
        </div>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
