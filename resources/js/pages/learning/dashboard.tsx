import { Link } from '@inertiajs/react';
import {
    BookOpenText,
    ClipboardCheck,
    FileStack,
    GraduationCap,
    HardDrive,
    Users,
    Video,
} from 'lucide-react';
import { fileSize, relativeTime } from '@/components/learning/format';
import ProgressBar from '@/components/learning/progress-bar';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useFormat } from '@/hooks/use-format';
import { cn } from '@/lib/utils';
import { dashboard as learningDashboard } from '@/routes/learning';
import {
    index as courses,
    show as showCourse,
} from '@/routes/learning/courses';
import { index as liveClassesIndex } from '@/routes/learning/live-classes';
import {
    index as quizzesIndex,
    show as showQuiz,
} from '@/routes/learning/quizzes';

type EngagementStudent = {
    student_id: number;
    name: string;
    section: string | null;
    score: number;
    band: 'low' | 'medium' | 'high';
    completion: number | null;
    assessment: number | null;
    submission: number | null;
    days_since_active: number | null;
    reasons: string[];
};

type Props = {
    isSupervisor: boolean;
    stats: {
        courses: number;
        published_courses: number;
        lessons: number;
        materials: number;
        active_learners: number;
        to_mark: number;
    };
    storage: { used: number; quota: number | null } | null;
    liveClasses: {
        id: number;
        title: string;
        subject: string;
        color: string | null;
        audience: string;
        starts_at: string;
        provider: string;
    }[];
    courseProgress?: {
        id: number;
        title: string;
        subject: string;
        color: string | null;
        lessons: number;
        audience: number;
        started: number;
        average_completion: number;
    }[];
    quizResults?: {
        id: number;
        title: string;
        subject: string;
        attempts: number;
        average: number | null;
        to_mark: number;
    }[];
    engagement?: {
        available: boolean;
        data: {
            summary: {
                students: number;
                average_score: number | null;
                average_completion: number | null;
                average_quiz: number | null;
                inactive_30_days: number;
            } | null;
            bands: { band: string; students: number }[];
            sections: {
                section: string;
                average_score: number | null;
                students: number;
            }[];
            students: EngagementStudent[];
        } | null;
    };
};

const bandTone = { low: 'danger', medium: 'warning', high: 'success' } as const;

function Panel({
    title,
    action,
    children,
}: {
    title: string;
    action?: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-2xl border bg-card shadow-sm">
            <header className="flex items-center justify-between border-b px-5 py-3">
                <h2 className="font-semibold">{title}</h2>
                {action}
            </header>
            <div className="p-5">{children}</div>
        </section>
    );
}

function Loading() {
    return (
        <div className="space-y-3">
            <Skeleton className="h-4 w-2/3" />
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-4 w-5/6" />
        </div>
    );
}

export default function LearningDashboard({
    isSupervisor,
    stats,
    storage,
    liveClasses,
    courseProgress,
    quizResults,
    engagement,
}: Props) {
    const { dateTime } = useFormat();
    const bandTotal =
        engagement?.data?.bands.reduce((sum, band) => sum + band.students, 0) ??
        0;

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Learning overview"
                description={
                    isSupervisor
                        ? 'Online learning across the whole school.'
                        : 'How your classes are getting on with your courses.'
                }
                actions={
                    <Button asChild>
                        <Link href={courses()}>
                            <BookOpenText aria-hidden /> Courses
                        </Link>
                    </Button>
                }
            />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Published courses"
                    value={`${stats.published_courses} / ${stats.courses}`}
                    hint={`${stats.lessons} lessons live`}
                    icon={GraduationCap}
                    tone="violet"
                />
                <StatCard
                    label="Learning materials"
                    value={stats.materials}
                    hint="Files and links"
                    icon={FileStack}
                    tone="sky"
                />
                <StatCard
                    label="Active learners"
                    value={stats.active_learners}
                    hint="Opened a lesson in the last 7 days"
                    icon={Users}
                    tone="emerald"
                />
                <StatCard
                    label="Quizzes to mark"
                    value={stats.to_mark}
                    hint="Written answers waiting"
                    icon={ClipboardCheck}
                    tone="orange"
                />
            </div>

            <div className="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
                <Panel title="Course completion">
                    {courseProgress === undefined ? (
                        <Loading />
                    ) : courseProgress.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No published courses yet.
                        </p>
                    ) : (
                        <ul className="space-y-4">
                            {courseProgress.map((course) => (
                                <li key={course.id}>
                                    <div className="mb-1 flex items-baseline justify-between gap-2 text-sm">
                                        <Link
                                            href={showCourse(course.id)}
                                            className="truncate font-medium hover:underline"
                                        >
                                            <span
                                                className="mr-2 inline-block size-2 rounded-full"
                                                style={{
                                                    background:
                                                        course.color ??
                                                        'var(--primary)',
                                                }}
                                                aria-hidden
                                            />
                                            {course.title}
                                        </Link>
                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {course.started}/{course.audience}{' '}
                                            started · {course.lessons} lessons
                                        </span>
                                    </div>
                                    <ProgressBar
                                        percent={course.average_completion}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>

                <div className="grid content-start gap-6">
                    <Panel
                        title="Upcoming live classes"
                        action={
                            <Link
                                href={liveClassesIndex()}
                                className="text-sm text-primary hover:underline"
                            >
                                All
                            </Link>
                        }
                    >
                        {liveClasses.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nothing scheduled.
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {liveClasses.map((liveClass) => (
                                    <li
                                        key={liveClass.id}
                                        className="flex items-start gap-3"
                                    >
                                        <Video
                                            className="mt-0.5 size-4 shrink-0 text-royal-600"
                                            aria-hidden
                                        />
                                        <div className="min-w-0 text-sm">
                                            <p className="font-medium">
                                                {liveClass.title}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {liveClass.subject} ·{' '}
                                                {liveClass.audience} ·{' '}
                                                {dateTime(liveClass.starts_at)}{' '}
                                                (
                                                {relativeTime(
                                                    liveClass.starts_at,
                                                )}
                                                )
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel
                        title="Quiz results"
                        action={
                            <Link
                                href={quizzesIndex()}
                                className="text-sm text-primary hover:underline"
                            >
                                All
                            </Link>
                        }
                    >
                        {quizResults === undefined ? (
                            <Loading />
                        ) : quizResults.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No published quizzes yet.
                            </p>
                        ) : (
                            <ul className="divide-y text-sm">
                                {quizResults.map((quiz) => (
                                    <li
                                        key={quiz.id}
                                        className="flex items-center gap-2 py-2"
                                    >
                                        <Link
                                            href={showQuiz(quiz.id)}
                                            className="min-w-0 flex-1 truncate hover:underline"
                                        >
                                            {quiz.title}
                                        </Link>
                                        {quiz.to_mark > 0 && (
                                            <TonePill tone="warning">
                                                {quiz.to_mark} to mark
                                            </TonePill>
                                        )}
                                        <span className="w-24 text-right text-muted-foreground tabular-nums">
                                            {quiz.average !== null
                                                ? `avg ${quiz.average}%`
                                                : `${quiz.attempts} tries`}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    {storage && (
                        <Panel title="Storage">
                            <div className="flex items-center gap-3">
                                <HardDrive
                                    className="size-5 text-muted-foreground"
                                    aria-hidden
                                />
                                <div className="flex-1">
                                    {storage.quota !== null ? (
                                        <ProgressBar
                                            percent={
                                                (storage.used / storage.quota) *
                                                100
                                            }
                                            label={`${fileSize(storage.used)} of ${fileSize(storage.quota)}`}
                                        />
                                    ) : (
                                        <p className="text-sm">
                                            {fileSize(storage.used)} used (no
                                            limit)
                                        </p>
                                    )}
                                </div>
                            </div>
                        </Panel>
                    )}
                </div>
            </div>

            <Panel title="Student engagement">
                {engagement === undefined ? (
                    <Loading />
                ) : !engagement.available ? (
                    <p className="text-sm text-muted-foreground">
                        The analytics service is not reachable right now. Course
                        and quiz figures above are still up to date.
                    </p>
                ) : engagement.data?.summary == null ? (
                    <p className="text-sm text-muted-foreground">
                        No enrolled students to analyse yet.
                    </p>
                ) : (
                    <div className="grid gap-6 lg:grid-cols-[1fr_1.6fr]">
                        <div className="space-y-4">
                            <dl className="grid grid-cols-2 gap-3 text-sm">
                                {[
                                    [
                                        'Average engagement',
                                        engagement.data.summary.average_score,
                                    ],
                                    [
                                        'Lesson completion',
                                        engagement.data.summary
                                            .average_completion,
                                    ],
                                    [
                                        'Average quiz score',
                                        engagement.data.summary.average_quiz,
                                    ],
                                ].map(([label, value]) => (
                                    <div
                                        key={label as string}
                                        className="rounded-xl bg-muted/60 p-3"
                                    >
                                        <dt className="text-xs text-muted-foreground">
                                            {label}
                                        </dt>
                                        <dd className="text-xl font-bold tabular-nums">
                                            {value === null ? '—' : `${value}%`}
                                        </dd>
                                    </div>
                                ))}
                                <div className="rounded-xl bg-muted/60 p-3">
                                    <dt className="text-xs text-muted-foreground">
                                        Inactive 30+ days
                                    </dt>
                                    <dd className="text-xl font-bold tabular-nums">
                                        {
                                            engagement.data.summary
                                                .inactive_30_days
                                        }
                                    </dd>
                                </div>
                            </dl>
                            <div>
                                <p className="mb-2 text-xs font-medium text-muted-foreground uppercase">
                                    Engagement bands
                                </p>
                                <div
                                    className="flex h-3 overflow-hidden rounded-full bg-muted"
                                    role="img"
                                    aria-label={engagement.data.bands
                                        .map(
                                            (band) =>
                                                `${band.band}: ${band.students}`,
                                        )
                                        .join(', ')}
                                >
                                    {engagement.data.bands.map((band) => (
                                        <span
                                            key={band.band}
                                            className={cn(
                                                band.band === 'low'
                                                    ? 'bg-rose-500'
                                                    : band.band === 'medium'
                                                      ? 'bg-gold-400'
                                                      : 'bg-emerald-500',
                                            )}
                                            style={{
                                                width: `${bandTotal ? (band.students / bandTotal) * 100 : 0}%`,
                                            }}
                                        />
                                    ))}
                                </div>
                                <ul className="mt-2 flex gap-4 text-xs text-muted-foreground">
                                    {engagement.data.bands.map((band) => (
                                        <li
                                            key={band.band}
                                            className="capitalize"
                                        >
                                            {band.band}:{' '}
                                            <span className="font-medium text-foreground">
                                                {band.students}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                            {engagement.data.sections.length > 1 && (
                                <div>
                                    <p className="mb-2 text-xs font-medium text-muted-foreground uppercase">
                                        By section (lowest first)
                                    </p>
                                    <ul className="space-y-2">
                                        {engagement.data.sections
                                            .slice(0, 6)
                                            .map((section) => (
                                                <li key={section.section}>
                                                    <ProgressBar
                                                        percent={
                                                            section.average_score ??
                                                            0
                                                        }
                                                        label={`${section.section} (${section.students})`}
                                                    />
                                                </li>
                                            ))}
                                    </ul>
                                </div>
                            )}
                        </div>
                        <div>
                            <p className="mb-2 text-xs font-medium text-muted-foreground uppercase">
                                Students who may need a nudge
                            </p>
                            <div className="max-h-96 overflow-y-auto rounded-xl border">
                                <table className="w-full text-sm">
                                    <caption className="sr-only">
                                        Least engaged students
                                    </caption>
                                    <thead className="sticky top-0 bg-muted text-left text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-3 py-2"
                                            >
                                                Student
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-3 py-2"
                                            >
                                                Score
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-3 py-2"
                                            >
                                                Why
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {engagement.data.students
                                            .filter(
                                                (student) =>
                                                    student.band !== 'high',
                                            )
                                            .slice(0, 15)
                                            .map((student) => (
                                                <tr key={student.student_id}>
                                                    <td className="px-3 py-2">
                                                        <p className="font-medium">
                                                            {student.name}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {student.section}
                                                        </p>
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <TonePill
                                                            tone={
                                                                bandTone[
                                                                    student.band
                                                                ]
                                                            }
                                                        >
                                                            {student.score}
                                                        </TonePill>
                                                    </td>
                                                    <td className="px-3 py-2 text-xs text-muted-foreground">
                                                        {student.reasons.join(
                                                            ', ',
                                                        ) || '—'}
                                                    </td>
                                                </tr>
                                            ))}
                                    </tbody>
                                </table>
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                Score combines lesson completion, quiz results,
                                homework handed in and recent activity.
                            </p>
                        </div>
                    </div>
                )}
            </Panel>
        </div>
    );
}

LearningDashboard.layout = {
    breadcrumbs: [{ title: 'Learning overview', href: learningDashboard() }],
};
