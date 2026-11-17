import { Link } from '@inertiajs/react';
import { KeyRound, FileUp, Plus } from 'lucide-react';
import type { Column } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import Pagination from '@/components/data/pagination';
import SearchInput from '@/components/data/search-input';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { useTableFilters } from '@/hooks/use-table-filters';
import { show as importPage } from '@/routes/imports';
import { create, index, show } from '@/routes/people/students';
import type { EnumOption, Paginated, SectionOption, Student } from '@/types';

type Props = {
    students: Paginated<Student>;
    filters: { search?: string; section_id?: number; status?: string };
    sections: SectionOption[];
    statuses: EnumOption[];
    can: { create: boolean };
};

export default function StudentsIndex({
    students,
    filters,
    sections,
    statuses,
    can,
}: Props) {
    const { values, update } = useTableFilters(index.url(), {
        search: filters.search ?? '',
        section_id: filters.section_id ?? '',
        status: filters.status ?? '',
    });

    const columns: Column<Student>[] = [
        {
            key: 'name',
            header: 'Student',
            cell: (student) => (
                <Link
                    href={show(student.id)}
                    className="block min-w-48 hover:underline"
                >
                    <span className="block font-medium">
                        {student.full_name}
                    </span>
                    <span className="block font-mono text-xs text-muted-foreground">
                        {student.admission_no}
                    </span>
                </Link>
            ),
        },
        {
            key: 'section',
            header: 'Class',
            cell: (student) =>
                student.current_enrollment?.section ? (
                    <span>
                        {student.current_enrollment.section}
                        {student.current_enrollment.roll_number !== null && (
                            <span className="text-muted-foreground">
                                {' '}
                                · #{student.current_enrollment.roll_number}
                            </span>
                        )}
                    </span>
                ) : (
                    <span className="text-muted-foreground">Not enrolled</span>
                ),
        },
        {
            key: 'gender',
            header: 'Gender',
            cell: (student) => student.gender_label,
        },
        {
            key: 'status',
            header: 'Status',
            cell: (student) => (
                <Badge
                    variant={
                        student.status === 'active' ? 'secondary' : 'outline'
                    }
                >
                    {student.status_label}
                </Badge>
            ),
        },
        {
            key: 'portal',
            header: 'Portal',
            cell: (student) =>
                student.has_portal_account ? (
                    <KeyRound
                        className="size-4 text-emerald-600"
                        aria-label="Has a portal account"
                    />
                ) : (
                    <span
                        className="text-muted-foreground"
                        aria-label="No portal account"
                    >
                        —
                    </span>
                ),
        },
    ];

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Students"
                description="Student records, their current class and portal access."
                actions={
                    can.create && (
                        <>
                            <Button variant="outline" asChild>
                                <Link href={importPage('students')}>
                                    <FileUp aria-hidden /> Import
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus aria-hidden /> New student
                                </Link>
                            </Button>
                        </>
                    )
                }
            />
            <div className="flex flex-col gap-2 md:flex-row">
                <SearchInput
                    value={values.search}
                    onChange={(search) =>
                        update({ search }, { debounce: true })
                    }
                    placeholder="Name, admission no. or exact national ID"
                />
                <NativeSelect
                    aria-label="Filter by section"
                    value={values.section_id}
                    onChange={(e) => update({ section_id: e.target.value })}
                    className="md:w-56"
                >
                    <option value="">All sections</option>
                    {sections.map((section) => (
                        <option key={section.id} value={section.id}>
                            {section.name}
                        </option>
                    ))}
                </NativeSelect>
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
                caption="Students"
                rows={students.data}
                columns={columns}
                rowKey={(student) => student.id}
                empty="No students match these filters."
            />
            <Pagination meta={students.meta} />
        </div>
    );
}

StudentsIndex.layout = {
    breadcrumbs: [{ title: 'Students', href: index() }],
};
