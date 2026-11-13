import { Link } from '@inertiajs/react';
import { Lock, Pencil, Plus } from 'lucide-react';
import ConfirmDelete from '@/components/confirm-delete';
import type { Column } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import Pagination from '@/components/data/pagination';
import SearchInput from '@/components/data/search-input';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTableFilters } from '@/hooks/use-table-filters';
import { create, destroy, edit, index } from '@/routes/admin/roles';
import type { Paginated, Role } from '@/types';

type Props = {
    roles: Paginated<Role>;
    filters: { search?: string };
    can: { create: boolean };
};

export default function RolesIndex({ roles, filters, can }: Props) {
    const { values, update } = useTableFilters(index.url(), {
        search: filters.search ?? '',
    });

    const columns: Column<Role>[] = [
        {
            key: 'name',
            header: 'Role',
            cell: (role) => (
                <div className="min-w-48">
                    <p className="flex items-center gap-1.5 font-medium">
                        {role.name}
                        {role.is_system && (
                            <Lock
                                className="size-3 text-muted-foreground"
                                aria-label="System role"
                            />
                        )}
                    </p>
                    {role.description && (
                        <p className="line-clamp-1 text-xs text-muted-foreground">
                            {role.description}
                        </p>
                    )}
                </div>
            ),
        },
        {
            key: 'scope',
            header: 'Scope',
            cell: (role) => (
                <Badge variant="outline">
                    {role.school?.name ?? 'All schools'}
                </Badge>
            ),
        },
        {
            key: 'parent',
            header: 'Inherits from',
            cell: (role) => role.parent?.name ?? '—',
        },
        {
            key: 'permissions',
            header: 'Permissions',
            cell: (role) => (
                <span className="tabular-nums">
                    {role.slug === 'super-admin'
                        ? 'All'
                        : role.permissions_count}
                </span>
            ),
        },
        {
            key: 'users',
            header: 'Users',
            cell: (role) => (
                <span className="tabular-nums">{role.users_count}</span>
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'text-right',
            cell: (role) => (
                <div className="flex justify-end gap-1">
                    {role.can.update && (
                        <Button variant="ghost" size="sm" asChild>
                            <Link
                                href={edit(role.id)}
                                aria-label={`Edit ${role.name}`}
                            >
                                <Pencil aria-hidden />
                            </Link>
                        </Button>
                    )}
                    {role.can.delete && (
                        <ConfirmDelete
                            action={destroy(role.id)}
                            title={`Delete the ${role.name} role?`}
                            description="Roles that are still assigned to users cannot be deleted."
                        />
                    )}
                </div>
            ),
        },
    ];

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Roles & permissions"
                description="System roles are shared by every school; create custom roles for your school, optionally inheriting from one."
                actions={
                    can.create && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus aria-hidden /> New role
                            </Link>
                        </Button>
                    )
                }
            />
            <SearchInput
                value={values.search}
                onChange={(search) => update({ search }, { debounce: true })}
                placeholder="Search roles"
            />
            <DataTable
                caption="Roles"
                rows={roles.data}
                columns={columns}
                rowKey={(role) => role.id}
                empty="No roles found."
            />
            <Pagination meta={roles.meta} />
        </div>
    );
}

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Roles & permissions', href: index() }],
};
