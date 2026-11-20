import { Link, router } from '@inertiajs/react';
import {
    CalendarClock,
    CheckCircle2,
    Clock,
    FileQuestion,
    PlayCircle,
    Trophy,
} from 'lucide-react';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { NativeSelect } from '@/components/ui/native-select';
import { useFormat } from '@/hooks/use-format';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/exams/my';
import type { ExamState } from '@/types/exams';
import { mark, stateTone } from '@/types/exams';

type ExamCard = {
    id: number;
    title: string;
    subject: string;
    subject_color: string | null;
    starts_at: string;
    ends_at: string;
    duration_minutes: number;
    total_marks: number;
    passing_marks: number;
    questions_count: number;
    max_attempts: number;
    attempts_used: number;
    state: ExamState;
    state_label: string;
    can_start: boolean;
    in_progress: boolean;
    handed_in: boolean;
    results_published: boolean;
};

type Props = {
    student: { id: number; name: string };
    children: { id: number; name: string }[];
    isExaminee: boolean;
    exams: ExamCard[];
    history: {
        exam_id: number;
        title: string;
        subject: string;
        date: string;
        percentage: number;
        grade: string | null;
        passed: boolean | null;
    }[];
};

function ExamTile({
    exam,
    isExaminee,
    query,
}: {
    exam: ExamCard;
    isExaminee: boolean;
    query: Record<string, string>;
}) {
    const { dateTime } = useFormat();
    const actionable = isExaminee && exam.can_start;

    return (
        <li>
            <Link
                href={show(exam.id, { query })}
                className={cn(
                    'group flex h-full flex-col rounded-2xl border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md',
                    actionable && 'border-gold-400 ring-1 ring-gold-300',
                )}
            >
                <div className="flex items-start justify-between gap-3">
                    <span
                        className="inline-flex items-center gap-1.5 text-xs font-semibold"
                        style={{ color: exam.subject_color ?? undefined }}
                    >
                        <span
                            className="size-2 rounded-full bg-current"
                            aria-hidden
                        />
                        {exam.subject}
                    </span>
                    <TonePill tone={stateTone[exam.state]}>
                        {exam.state_label}
                    </TonePill>
                </div>
                <h3 className="mt-2 font-semibold group-hover:text-primary">
                    {exam.title}
                </h3>
                <dl className="mt-3 grid gap-1 text-sm text-muted-foreground">
                    <div className="flex items-center gap-2">
                        <CalendarClock className="size-4" aria-hidden />
                        <dt className="sr-only">Window</dt>
                        <dd>
                            {dateTime(exam.starts_at)} –{' '}
                            {dateTime(exam.ends_at)}
                        </dd>
                    </div>
                    <div className="flex items-center gap-2">
                        <Clock className="size-4" aria-hidden />
                        <dt className="sr-only">Length</dt>
                        <dd>
                            {exam.duration_minutes} min · {exam.questions_count}{' '}
                            questions · {mark(exam.total_marks)} marks
                        </dd>
                    </div>
                </dl>
                <p className="mt-auto pt-4 text-sm font-medium">
                    {exam.in_progress && isExaminee ? (
                        <span className="inline-flex items-center gap-1.5 text-amber-700 dark:text-amber-300">
                            <PlayCircle className="size-4" aria-hidden /> Resume
                            now
                        </span>
                    ) : actionable ? (
                        <span className="inline-flex items-center gap-1.5 text-primary">
                            <PlayCircle className="size-4" aria-hidden /> Open
                            and start
                        </span>
                    ) : exam.results_published ? (
                        <span className="inline-flex items-center gap-1.5 text-emerald-700 dark:text-emerald-300">
                            <Trophy className="size-4" aria-hidden /> Results
                            are out
                        </span>
                    ) : exam.handed_in ? (
                        <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                            <CheckCircle2 className="size-4" aria-hidden />{' '}
                            Handed in; waiting for results
                        </span>
                    ) : (
                        <span className="text-muted-foreground">
                            View details
                        </span>
                    )}
                </p>
            </Link>
        </li>
    );
}

export default function MyExams({
    student,
    children,
    isExaminee,
    exams,
    history,
}: Props) {
    const { date } = useFormat();
    const query: Record<string, string> =
        children.length > 0 ? { student: String(student.id) } : {};
    const now = exams.filter(
        (exam) =>
            exam.state === 'active' && (exam.can_start || exam.in_progress),
    );
    const upcoming = exams.filter((exam) => exam.state === 'scheduled');
    const past = exams.filter(
        (exam) => !now.includes(exam) && !upcoming.includes(exam),
    );

    const group = (title: string, list: ExamCard[], empty: string) => (
        <section className="space-y-3">
            <h2 className="text-lg font-semibold">{title}</h2>
            {list.length === 0 ? (
                <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                    {empty}
                </p>
            ) : (
                <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {list.map((exam) => (
                        <ExamTile
                            key={exam.id}
                            exam={exam}
                            isExaminee={isExaminee}
                            query={query}
                        />
                    ))}
                </ul>
            )}
        </section>
    );

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={isExaminee ? 'My exams' : `Exams: ${student.name}`}
                description={
                    isExaminee
                        ? 'Your online exams. The timer starts when you begin, your answers save as you go, and results appear here once your teacher publishes them.'
                        : 'Online exams and published results for your child.'
                }
                actions={
                    children.length > 1 && (
                        <NativeSelect
                            aria-label="Choose child"
                            value={student.id}
                            onChange={(event) =>
                                router.get(index.url(), {
                                    student: event.target.value,
                                })
                            }
                            className="w-52"
                        >
                            {children.map((child) => (
                                <option key={child.id} value={child.id}>
                                    {child.name}
                                </option>
                            ))}
                        </NativeSelect>
                    )
                }
            />

            {exams.length === 0 ? (
                <div className="rounded-2xl border border-dashed p-10 text-center">
                    <FileQuestion
                        className="mx-auto size-8 text-muted-foreground"
                        aria-hidden
                    />
                    <p className="mt-3 font-medium">No online exams yet</p>
                </div>
            ) : (
                <>
                    {now.length > 0 &&
                        group('Open now', now, 'Nothing open right now.')}
                    {group('Coming up', upcoming, 'No exams scheduled.')}
                    {past.length > 0 &&
                        group('Earlier', past, 'Nothing here yet.')}
                </>
            )}

            {history.length > 0 && (
                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Results this year</h2>
                    <div className="overflow-x-auto rounded-2xl border bg-card shadow-sm">
                        <table className="w-full text-sm">
                            <caption className="sr-only">
                                Published exam results
                            </caption>
                            <thead className="bg-muted text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th scope="col" className="px-4 py-2">
                                        Exam
                                    </th>
                                    <th scope="col" className="px-4 py-2">
                                        Date
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-2 text-right"
                                    >
                                        Score
                                    </th>
                                    <th scope="col" className="px-4 py-2">
                                        Grade
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {history.map((row) => (
                                    <tr key={row.exam_id}>
                                        <td className="px-4 py-2">
                                            <Link
                                                href={show(row.exam_id, {
                                                    query,
                                                })}
                                                className="font-medium hover:text-primary hover:underline"
                                            >
                                                {row.title}
                                            </Link>
                                            <span className="block text-xs text-muted-foreground">
                                                {row.subject}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2 whitespace-nowrap text-muted-foreground">
                                            {date(row.date)}
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {mark(row.percentage)}%
                                        </td>
                                        <td className="px-4 py-2">
                                            {row.grade ?? '—'}
                                            {row.passed !== null && (
                                                <span
                                                    className={cn(
                                                        'ml-2 text-xs font-medium',
                                                        row.passed
                                                            ? 'text-emerald-700 dark:text-emerald-300'
                                                            : 'text-rose-700 dark:text-rose-300',
                                                    )}
                                                >
                                                    {row.passed
                                                        ? 'Pass'
                                                        : 'Fail'}
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}
        </div>
    );
}

MyExams.layout = {
    breadcrumbs: [{ title: 'Exams', href: index() }],
};
