import { Link, router } from '@inertiajs/react';
import { CalendarClock, Plus } from 'lucide-react';
import Pagination from '@/components/data/pagination';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
import { useFormat } from '@/hooks/use-format';
import { cn } from '@/lib/utils';
import { create, index, show } from '@/routes/academics/assignments';
import type { AssignmentSummary, Paginated, SectionOption } from '@/types';

type Props = {
    assignments: Pick<
        Paginated<
            AssignmentSummary & {
                submissions_count: number;
                my_status: 'pending' | 'submitted' | 'graded' | null;
            }
        >,
        'data' | 'meta'
    >;
    sections: SectionOption[];
    filters: { section_id: number | null; when: 'upcoming' | 'past' };
    can: { create: boolean };
};

export default function AssignmentsIndex({
    assignments,
    sections,
    filters,
    can,
}: Props) {
    const format = useFormat();
    const visit = (changes: Partial<Props['filters']>): void =>
        router.get(
            index.url(),
            { ...filters, ...changes },
            { preserveScroll: true, preserveState: true, replace: true },
        );

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Assignments & homework"
                description="Work set for each class, with submissions and feedback."
                actions={
                    can.create && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus aria-hidden /> Set work
                            </Link>
                        </Button>
                    )
                }
            />
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                <div
                    className="grid grid-cols-2 gap-1 rounded-xl bg-muted p-1 sm:w-64"
                    role="tablist"
                    aria-label="Due date"
                >
                    {(['upcoming', 'past'] as const).map((when) => (
                        <button
                            key={when}
                            type="button"
                            role="tab"
                            aria-selected={filters.when === when}
                            onClick={() => visit({ when })}
                            className={cn(
                                'rounded-lg px-3 py-1.5 text-sm capitalize',
                                filters.when === when &&
                                    'bg-background font-medium shadow-sm',
                            )}
                        >
                            {when}
                        </button>
                    ))}
                </div>
                {sections.length > 1 && (
                    <NativeSelect
                        aria-label="Section"
                        value={filters.section_id ?? ''}
                        onChange={(e) =>
                            visit({
                                section_id:
                                    e.target.value === ''
                                        ? null
                                        : Number(e.target.value),
                            })
                        }
                        className="sm:w-60"
                    >
                        <option value="">All my sections</option>
                        {sections.map((section) => (
                            <option key={section.id} value={section.id}>
                                {section.name}
                            </option>
                        ))}
                    </NativeSelect>
                )}
            </div>

            {assignments.data.length === 0 ? (
                <Card>
                    <CardHeader>
                        <CardTitle>
                            Nothing{' '}
                            {filters.when === 'upcoming' ? 'due' : 'here'}
                        </CardTitle>
                        <CardDescription>
                            {filters.when === 'upcoming'
                                ? 'No work is due soon.'
                                : 'No past work found.'}
                        </CardDescription>
                    </CardHeader>
                </Card>
            ) : (
                <ul className="grid gap-3 md:grid-cols-2">
                    {assignments.data.map((assignment) => (
                        <li key={assignment.id}>
                            <Link
                                href={show(assignment.id)}
                                className="group flex h-full gap-4 rounded-2xl border bg-card p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <span
                                    className="w-1.5 shrink-0 rounded-full"
                                    style={{
                                        backgroundColor:
                                            assignment.subject?.color ??
                                            'var(--primary)',
                                    }}
                                    aria-hidden
                                />
                                <div className="min-w-0 flex-1 space-y-1.5">
                                    <div className="flex items-start justify-between gap-2">
                                        <p className="font-semibold group-hover:underline">
                                            {assignment.title}
                                        </p>
                                        <TonePill tone="info">
                                            {assignment.type_label}
                                        </TonePill>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {assignment.subject?.name} ·{' '}
                                        {assignment.section}
                                    </p>
                                    <div className="flex flex-wrap items-center gap-3 text-xs">
                                        <span className="flex items-center gap-1 text-muted-foreground">
                                            <CalendarClock
                                                className="size-3.5"
                                                aria-hidden
                                            />{' '}
                                            Due{' '}
                                            {format.date(
                                                `${assignment.due_on}T12:00:00Z`,
                                            )}
                                        </span>
                                        {assignment.my_status === null ? (
                                            <span className="text-muted-foreground">
                                                {assignment.submissions_count}{' '}
                                                submitted
                                            </span>
                                        ) : assignment.my_status ===
                                          'graded' ? (
                                            <TonePill tone="success">
                                                Graded
                                            </TonePill>
                                        ) : assignment.my_status ===
                                          'submitted' ? (
                                            <TonePill tone="info">
                                                Submitted
                                            </TonePill>
                                        ) : (
                                            <TonePill tone="warning">
                                                To do
                                            </TonePill>
                                        )}
                                    </div>
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
            <Pagination meta={assignments.meta} />
        </div>
    );
}

AssignmentsIndex.layout = {
    breadcrumbs: [{ title: 'Assignments', href: index() }],
};
