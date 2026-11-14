import { Link } from '@inertiajs/react';
import { CalendarCheck, FileBadge, NotebookPen } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useFormat } from '@/hooks/use-format';
import { progress } from '@/routes/academics';
import { show as showAssignment } from '@/routes/academics/assignments';
import { reportCard } from '@/routes/academics/exams';

type Child = {
    id: number;
    name: string;
    admission_no: string;
    section: string | null;
    attendance: {
        present: number;
        absent: number;
        late: number;
        excused: number;
        total: number;
        percentage: number | null;
    } | null;
    results: {
        exam_id: number;
        exam: string;
        percentage: number | null;
        grade: string | null;
        rank: number | null;
        class_size: number;
        passed: boolean | null;
    }[];
    assignments: {
        id: number;
        title: string;
        subject: string;
        color: string | null;
        due_on: string;
        submitted: boolean;
    }[];
};

const ringColors = [
    'from-indigo-500 to-fuchsia-500',
    'from-emerald-500 to-sky-500',
    'from-orange-500 to-pink-500',
];

function AttendanceRing({ percentage }: { percentage: number | null }) {
    const value = percentage ?? 0;
    const circumference = 2 * Math.PI * 40;

    return (
        <div className="relative size-28 shrink-0">
            <svg
                viewBox="0 0 100 100"
                className="size-full -rotate-90"
                aria-hidden
            >
                <circle
                    cx="50"
                    cy="50"
                    r="40"
                    className="fill-none stroke-muted"
                    strokeWidth="10"
                />
                <circle
                    cx="50"
                    cy="50"
                    r="40"
                    className={`fill-none ${value >= 90 ? 'stroke-emerald-500' : value >= 75 ? 'stroke-amber-500' : 'stroke-rose-500'}`}
                    strokeWidth="10"
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={circumference * (1 - value / 100)}
                />
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center">
                <span className="text-xl font-bold tabular-nums">
                    {percentage !== null ? `${percentage}%` : '—'}
                </span>
                <span className="text-[10px] text-muted-foreground uppercase">
                    attendance
                </span>
            </div>
        </div>
    );
}

export default function Progress({
    year,
    children,
}: {
    year: { id: number; name: string } | null;
    children: Child[];
}) {
    const format = useFormat();

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="My progress"
                description={
                    year
                        ? `Academic year ${year.name}`
                        : 'No current academic year.'
                }
            />
            {children.length === 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>No student linked</CardTitle>
                        <CardDescription>
                            Ask the school office to link your account to a
                            student record.
                        </CardDescription>
                    </CardHeader>
                </Card>
            )}
            {children.map((child, index) => (
                <section key={child.id} className="space-y-4">
                    <div
                        className={`flex flex-col gap-4 rounded-3xl bg-gradient-to-r p-5 text-white shadow-lg sm:flex-row sm:items-center sm:justify-between ${ringColors[index % ringColors.length]}`}
                    >
                        <div>
                            <h2 className="text-xl font-bold">{child.name}</h2>
                            <p className="text-sm text-white/85">
                                {child.section ?? 'Not enrolled this year'} ·{' '}
                                {child.admission_no}
                            </p>
                        </div>
                        {child.attendance && (
                            <p className="text-sm text-white/90">
                                {child.attendance.present} present ·{' '}
                                {child.attendance.late} late ·{' '}
                                {child.attendance.absent} absent
                            </p>
                        )}
                    </div>
                    <div className="grid gap-4 lg:grid-cols-3">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <CalendarCheck
                                        className="size-4 text-emerald-500"
                                        aria-hidden
                                    />{' '}
                                    Attendance
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex items-center gap-4">
                                <AttendanceRing
                                    percentage={
                                        child.attendance?.percentage ?? null
                                    }
                                />
                                <p className="text-sm text-muted-foreground">
                                    Late arrivals count as present. Excused days
                                    are not counted.
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileBadge
                                        className="size-4 text-indigo-500"
                                        aria-hidden
                                    />{' '}
                                    Results
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="space-y-2">
                                    {child.results.map((result) => (
                                        <li key={result.exam_id}>
                                            <Link
                                                href={reportCard([
                                                    result.exam_id,
                                                    child.id,
                                                ])}
                                                className="flex items-center justify-between gap-2 rounded-xl border p-3 text-sm transition hover:bg-muted"
                                            >
                                                <span className="font-medium">
                                                    {result.exam}
                                                </span>
                                                <span className="flex items-center gap-2">
                                                    <span className="tabular-nums">
                                                        {result.percentage ??
                                                            '—'}
                                                        %
                                                    </span>
                                                    <span className="rounded-md bg-gradient-to-br from-indigo-500 to-violet-600 px-2 py-0.5 text-xs font-bold text-white">
                                                        {result.grade ?? '—'}
                                                    </span>
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                    {child.results.length === 0 && (
                                        <li className="text-sm text-muted-foreground">
                                            No published results yet.
                                        </li>
                                    )}
                                </ul>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <NotebookPen
                                        className="size-4 text-orange-500"
                                        aria-hidden
                                    />{' '}
                                    Due soon
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="space-y-2">
                                    {child.assignments.map((assignment) => (
                                        <li key={assignment.id}>
                                            <Link
                                                href={showAssignment(
                                                    assignment.id,
                                                )}
                                                className="flex items-center gap-3 rounded-xl border p-3 text-sm transition hover:bg-muted"
                                            >
                                                <span
                                                    className="h-8 w-1 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            assignment.color ??
                                                            'var(--primary)',
                                                    }}
                                                    aria-hidden
                                                />
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate font-medium">
                                                        {assignment.title}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {assignment.subject} ·
                                                        due{' '}
                                                        {format.date(
                                                            `${assignment.due_on}T12:00:00Z`,
                                                        )}
                                                    </span>
                                                </span>
                                                {assignment.submitted ? (
                                                    <TonePill tone="success">
                                                        Done
                                                    </TonePill>
                                                ) : (
                                                    <TonePill tone="warning">
                                                        To do
                                                    </TonePill>
                                                )}
                                            </Link>
                                        </li>
                                    ))}
                                    {child.assignments.length === 0 && (
                                        <li className="text-sm text-muted-foreground">
                                            Nothing due.
                                        </li>
                                    )}
                                </ul>
                            </CardContent>
                        </Card>
                    </div>
                </section>
            ))}
        </div>
    );
}

Progress.layout = {
    breadcrumbs: [{ title: 'My progress', href: progress() }],
};
