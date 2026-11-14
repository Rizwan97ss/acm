import { Link } from '@inertiajs/react';
import { KeyRound, Pencil, Plus } from 'lucide-react';
import ConfirmDelete from '@/components/confirm-delete';
import type { Column } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import Pagination from '@/components/data/pagination';
import SearchInput from '@/components/data/search-input';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { useTableFilters } from '@/hooks/use-table-filters';
import { create, destroy, edit, index } from '@/routes/people/guardians';
import type { Guardian, Paginated } from '@/types';

type Props = {
    guardians: Paginated<Guardian>;
    filters: { search?: string };
    can: { create: boolean };
};

export default function GuardiansIndex({ guardians, filters, can }: Props) {
    const { values, update } = useTableFilters(index.url(), {
        search: filters.search ?? '',
    });

    const columns: Column<Guardian>[] = [
        {
            key: 'name',
            header: 'Guardian',
            cell: (guardian) => (
                <div className="min-w-48">
                    <p className="font-medium">{guardian.full_name}</p>
                    <p className="text-xs text-muted-foreground">
                        {guardian.occupation ?? '—'}
                    </p>
                </div>
            ),
        },
        {
            key: 'contact',
            header: 'Contact',
            cell: (guardian) => (
                <div className="text-sm">
                    <p className="tabular-nums">{guardian.phone ?? '—'}</p>
                    <p className="text-xs text-muted-foreground">
                        {guardian.email ?? ''}
                    </p>
                </div>
            ),
        },
        {
            key: 'students',
            header: 'Children',
            cell: (guardian) => (
                <span className="tabular-nums">{guardian.students_count}</span>
            ),
        },
        {
            key: 'portal',
            header: 'Portal',
            cell: (guardian) =>
                guardian.has_portal_account ? (
                    <KeyRound
                        className="size-4 text-emerald-600"
                        aria-label="Has a portal account"
                    />
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'actions',
            header: '',
            className: 'text-right',
            cell: (guardian) => (
                <div className="flex justify-end gap-1">
                    {guardian.can.update && (
                        <Button variant="ghost" size="sm" asChild>
                            <Link
                                href={edit(guardian.id)}
                                aria-label={`Edit ${guardian.full_name}`}
                            >
                                <Pencil aria-hidden />
                            </Link>
                        </Button>
                    )}
                    {guardian.can.delete && (
                        <ConfirmDelete
                            action={destroy(guardian.id)}
                            title={`Delete ${guardian.full_name}?`}
                            description="Guardians still linked to students cannot be deleted."
                        />
                    )}
                </div>
            ),
        },
    ];

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Guardians"
                description="Parents and guardians. One guardian can be linked to several children."
                actions={
                    can.create && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus aria-hidden /> New guardian
                            </Link>
                        </Button>
                    )
                }
            />
            <SearchInput
                value={values.search}
                onChange={(search) => update({ search }, { debounce: true })}
                placeholder="Name, email or exact phone"
            />
            <DataTable
                caption="Guardians"
                rows={guardians.data}
                columns={columns}
                rowKey={(guardian) => guardian.id}
                empty="No guardians found."
            />
            <Pagination meta={guardians.meta} />
        </div>
    );
}

GuardiansIndex.layout = {
    breadcrumbs: [{ title: 'Guardians', href: index() }],
};
