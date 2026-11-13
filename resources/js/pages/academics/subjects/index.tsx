import { Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
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
import { useTableFilters } from '@/hooks/use-table-filters';
import { create, destroy, edit, index } from '@/routes/academics/subjects';
import type { Paginated, Subject } from '@/types';

type Props = {
    subjects: Paginated<Subject>;
    filters: { search?: string; type?: string };
    types: { value: string; label: string }[];
    can: { create: boolean };
};

export default function SubjectsIndex({
    subjects,
    filters,
    types,
    can,
}: Props) {
    const { values, update } = useTableFilters(index.url(), {
        search: filters.search ?? '',
        type: filters.type ?? '',
    });

    const columns: Column<Subject>[] = [
        {
            key: 'name',
            header: 'Subject',
            cell: (subject) => (
                <div className="flex min-w-48 items-center gap-3">
                    <span
                        className="size-3 shrink-0 rounded-full border"
                        style={{
                            backgroundColor: subject.color ?? 'transparent',
                        }}
                        aria-hidden
                    />
                    <div>
                        <p className="font-medium">{subject.name}</p>
                        <p className="font-mono text-xs text-muted-foreground">
                            {subject.code}
                        </p>
                    </div>
                </div>
            ),
        },
        {
            key: 'type',
            header: 'Type',
            cell: (subject) => (
                <Badge variant="outline">{subject.type_label}</Badge>
            ),
        },
        {
            key: 'classes',
            header: 'Classes',
            cell: (subject) => (
                <span className="tabular-nums">{subject.classes_count}</span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (subject) => <StatusBadge active={subject.is_active} />,
        },
        {
            key: 'actions',
            header: '',
            className: 'text-right',
            cell: (subject) => (
                <div className="flex justify-end gap-1">
                    {subject.can.update && (
                        <Button variant="ghost" size="sm" asChild>
                            <Link
                                href={edit(subject.id)}
                                aria-label={`Edit ${subject.name}`}
                            >
                                <Pencil aria-hidden />
                            </Link>
                        </Button>
                    )}
                    {subject.can.delete && (
                        <ConfirmDelete
                            action={destroy(subject.id)}
                            title={`Delete ${subject.name}?`}
                            description="Subjects used in a timetable cannot be deleted; deactivate them instead."
                        />
                    )}
                </div>
            ),
        },
    ];

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Subjects"
                description="The subject catalogue that classes and timetables draw from."
                actions={
                    can.create && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus aria-hidden /> New subject
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
                    aria-label="Filter by type"
                    value={values.type}
                    onChange={(event) => update({ type: event.target.value })}
                    className="sm:w-48"
                >
                    <option value="">All types</option>
                    {types.map((type) => (
                        <option key={type.value} value={type.value}>
                            {type.label}
                        </option>
                    ))}
                </NativeSelect>
            </div>
            <DataTable
                caption="Subjects"
                rows={subjects.data}
                columns={columns}
                rowKey={(subject) => subject.id}
                empty="No subjects yet."
            />
            <Pagination meta={subjects.meta} />
        </div>
    );
}

SubjectsIndex.layout = {
    breadcrumbs: [{ title: 'Subjects', href: index() }],
};
