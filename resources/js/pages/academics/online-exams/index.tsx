import { Link } from '@inertiajs/react';
import { BarChart3, FileQuestion, Library, Plus } from 'lucide-react';
import Pagination from '@/components/data/pagination';
import SearchInput from '@/components/data/search-input';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { useFormat } from '@/hooks/use-format';
import { useTableFilters } from '@/hooks/use-table-filters';
import { index as reports } from '@/routes/academics/online-exam-reports';
import { create, index, show } from '@/routes/academics/online-exams';
import { index as bank } from '@/routes/academics/question-bank';
import type { OnlineExam } from '@/types/exams';
import { mark, stateTone } from '@/types/exams';
import type { PaginationMeta } from '@/types/learning';

type Row = OnlineExam & {
    questions_count: number;
    attempts_count: number;
    to_mark_count: number;
};

type Option = { id: number; name: string };

type Props = {
    exams: { data: Row[]; meta: PaginationMeta };
    filters: {
        search?: string;
        subject_id?: number;
        class_id?: number;
        state?: string;
    };
    classes: Option[];
    subjects: Option[];
    states: { value: string; label: string }[];
    can: { create: boolean; reports: boolean; bank: boolean };
};

export default function OnlineExamsIndex({
    exams,
    filters,
    classes,
    subjects,
    states,
    can,
}: Props) {
    const { dateTime } = useFormat();
    const { values, update } = useTableFilters(index.url(), {
        search: filters.search ?? '',
        class_id: filters.class_id ?? '',
        subject_id: filters.subject_id ?? '',
        state: filters.state ?? '',
    });

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Online exams"
                description="Set timed MCQ, written or mixed exams for your classes. Objective answers are marked automatically; results reach students only when you publish them."
                actions={
                    <>
                        {can.bank && (
                            <Button asChild variant="outline">
                                <Link href={bank()}>
                                    <Library aria-hidden /> Question bank
                                </Link>
                            </Button>
                        )}
                        <Button asChild variant="outline">
                            <Link href={reports()}>
                                <BarChart3 aria-hidden /> Reports
                            </Link>
                        </Button>
                        {can.create && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus aria-hidden /> New exam
                                </Link>
                            </Button>
                        )}
                    </>
                }
            />

            <div className="flex flex-col gap-2 lg:flex-row">
                <SearchInput
                    value={values.search}
                    onChange={(search) =>
                        update({ search }, { debounce: true })
                    }
                    placeholder="Exam title"
                />
                <NativeSelect
                    aria-label="Filter by class"
                    value={values.class_id}
                    onChange={(event) =>
                        update({ class_id: event.target.value })
                    }
                    className="lg:w-44"
                >
                    <option value="">All classes</option>
                    {classes.map((option) => (
                        <option key={option.id} value={option.id}>
                            {option.name}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect
                    aria-label="Filter by subject"
                    value={values.subject_id}
                    onChange={(event) =>
                        update({ subject_id: event.target.value })
                    }
                    className="lg:w-44"
                >
                    <option value="">All subjects</option>
                    {subjects.map((option) => (
                        <option key={option.id} value={option.id}>
                            {option.name}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect
                    aria-label="Filter by stage"
                    value={values.state}
                    onChange={(event) => update({ state: event.target.value })}
                    className="lg:w-48"
                >
                    <option value="">Every stage</option>
                    {states.map((state) => (
                        <option key={state.value} value={state.value}>
                            {state.label}
                        </option>
                    ))}
                </NativeSelect>
            </div>

            {exams.data.length === 0 ? (
                <div className="rounded-2xl border border-dashed p-10 text-center">
                    <FileQuestion
                        className="mx-auto size-8 text-muted-foreground"
                        aria-hidden
                    />
                    <p className="mt-3 font-medium">No online exams here yet</p>
                    {can.create && (
                        <p className="mt-1 text-sm text-muted-foreground">
                            Create one, add questions (or pull them from the
                            question bank), then publish it.
                        </p>
                    )}
                </div>
            ) : (
                <div className="overflow-x-auto rounded-2xl border bg-card shadow-sm">
                    <table className="w-full text-sm">
                        <caption className="sr-only">Online exams</caption>
                        <thead className="bg-gradient-to-r from-navy-50 via-royal-50 to-gold-50 text-left text-xs text-navy-950/70 uppercase dark:from-navy-500/10 dark:via-royal-500/10 dark:to-gold-500/10 dark:text-navy-100/80">
                            <tr>
                                <th scope="col" className="px-4 py-3">
                                    Exam
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    For
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Window
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 text-right"
                                >
                                    Questions
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 text-right"
                                >
                                    Attempts
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Stage
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {exams.data.map((exam) => (
                                <tr key={exam.id} className="hover:bg-muted/40">
                                    <td className="px-4 py-3">
                                        <Link
                                            href={show(exam.id)}
                                            className="font-medium hover:text-primary hover:underline"
                                        >
                                            {exam.title}
                                        </Link>
                                        <p className="text-xs text-muted-foreground">
                                            {exam.subject} ·{' '}
                                            {mark(exam.total_marks)} marks ·{' '}
                                            {exam.duration_minutes} min
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {exam.audience}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                        {dateTime(exam.starts_at)}
                                        <span className="block text-xs">
                                            to {dateTime(exam.ends_at)}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {exam.questions_count}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {exam.attempts_count}
                                        {exam.to_mark_count > 0 && (
                                            <span className="ml-2">
                                                <TonePill tone="warning">
                                                    {exam.to_mark_count} to mark
                                                </TonePill>
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <TonePill tone={stateTone[exam.state]}>
                                            {exam.state_label}
                                        </TonePill>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            <Pagination meta={exams.meta} />
        </div>
    );
}

OnlineExamsIndex.layout = {
    breadcrumbs: [{ title: 'Online exams', href: index() }],
};
