import { Link, router } from '@inertiajs/react';
import {
    Bookmark,
    BookOpenText,
    CalendarClock,
    ClipboardList,
    PlayCircle,
    Sparkles,
    Video,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { relativeTime } from '@/components/learning/format';
import ProgressBar from '@/components/learning/progress-bar';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { useFormat } from '@/hooks/use-format';
import { cn } from '@/lib/utils';
import { show as showAssignment } from '@/routes/academics/assignments';
import { index } from '@/routes/learn';
import { show as showCourse } from '@/routes/learn/courses';
import { show as showLesson } from '@/routes/learn/lessons';
import { join } from '@/routes/learn/live-classes';
import { show as showQuiz } from '@/routes/learn/quizzes';
import type { Progress, SubjectBadge } from '@/types/learning';

type Props = {
    learner: { id: number; name: string; is_self: boolean } | null;
    children: { id: number; name: string }[];
    courses?: {
        id: number;
        title: string;
        description: string | null;
        subject: SubjectBadge;
        progress: Progress;
    }[];
    recent?: {
        lesson_id: number;
        title: string;
        course: string;
        subject_color: string | null;
        completed: boolean;
        last_viewed_at: string;
    }[];
    bookmarks?: { lesson_id: number; title: string; course: string }[];
    liveClasses?: {
        id: number;
        title: string;
        subject: SubjectBadge;
        provider: string;
        starts_at: string;
        ends_at: string;
        joinable_from: string;
    }[];
    quizzes?: {
        id: number;
        title: string;
        type: string;
        subject: SubjectBadge;
        closes_at: string | null;
        is_open: boolean;
        attempts_used: number;
        max_attempts: number;
        best_score: number | null;
    }[];
    dueWork?: { id: number; title: string; subject: string; due_on: string }[];
};

function useNow(intervalMs = 30_000): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), intervalMs);

        return () => window.clearInterval(timer);
    }, [intervalMs]);

    return now;
}

function SectionCard({
    title,
    icon: Icon,
    children,
    className,
}: {
    title: string;
    icon: typeof Video;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <section
            className={cn('rounded-2xl border bg-card shadow-sm', className)}
        >
            <header className="flex items-center gap-2 border-b px-5 py-3">
                <Icon
                    className="size-4 text-royal-600 dark:text-royal-300"
                    aria-hidden
                />
                <h2 className="font-semibold">{title}</h2>
            </header>
            <div className="p-5">{children}</div>
        </section>
    );
}

export default function LearnIndex({
    learner,
    children,
    courses = [],
    recent = [],
    bookmarks = [],
    liveClasses = [],
    quizzes = [],
    dueWork = [],
}: Props) {
    const { date, dateTime } = useFormat();
    const now = useNow();
    const query =
        learner && !learner.is_self ? { student: learner.id } : undefined;

    if (learner === null) {
        return (
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="My learning"
                    description="Courses, lessons, live classes and quizzes from your teachers."
                />
                <p className="rounded-2xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                    Your learning page appears once you are enrolled in a class
                    for the current year.
                </p>
            </div>
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <section className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-navy-900 via-navy-700 to-royal-600 p-6 text-white shadow-xl md:p-8">
                <div
                    className="pointer-events-none absolute -top-10 -right-10 size-48 rounded-full bg-gold-400/20 blur-2xl"
                    aria-hidden
                />
                <div className="relative flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm text-white/75">
                            {learner.is_self ? 'Welcome back' : 'Learning for'}
                        </p>
                        <h1 className="font-display text-4xl">
                            {learner.name}
                        </h1>
                        <p className="mt-1 text-sm text-white/80">
                            {courses.length} courses · {liveClasses.length} live
                            classes coming up ·{' '}
                            {quizzes.filter((quiz) => quiz.is_open).length}{' '}
                            quizzes open
                        </p>
                    </div>
                    {children.length > 1 && (
                        <NativeSelect
                            aria-label="Choose child"
                            value={learner.id}
                            onChange={(event) =>
                                router.get(index.url(), {
                                    student: event.target.value,
                                })
                            }
                            className="w-52 border-white/30 bg-white/10 text-white"
                        >
                            {children.map((child) => (
                                <option
                                    key={child.id}
                                    value={child.id}
                                    className="text-foreground"
                                >
                                    {child.name}
                                </option>
                            ))}
                        </NativeSelect>
                    )}
                </div>
                {recent[0] && (
                    <Link
                        href={showLesson(recent[0].lesson_id, { query })}
                        className="relative mt-5 inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-navy-900 shadow-[4px_4px_0_var(--color-gold-400)] transition hover:translate-x-px hover:translate-y-px hover:shadow-[3px_3px_0_var(--color-gold-400)]"
                    >
                        <PlayCircle className="size-4" aria-hidden />
                        Continue: {recent[0].title}
                    </Link>
                )}
            </section>

            <div className="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
                <div className="grid content-start gap-6">
                    {liveClasses.length > 0 && (
                        <SectionCard title="Live classes" icon={Video}>
                            <ul className="grid gap-3">
                                {liveClasses.map((liveClass) => {
                                    const joinable =
                                        now >=
                                            new Date(
                                                liveClass.joinable_from,
                                            ).getTime() &&
                                        now <=
                                            new Date(
                                                liveClass.ends_at,
                                            ).getTime();
                                    const live =
                                        now >=
                                            new Date(
                                                liveClass.starts_at,
                                            ).getTime() &&
                                        now <=
                                            new Date(
                                                liveClass.ends_at,
                                            ).getTime();

                                    return (
                                        <li
                                            key={liveClass.id}
                                            className="flex flex-wrap items-center gap-3 rounded-xl border p-3"
                                        >
                                            <span
                                                className="h-10 w-1.5 rounded-full"
                                                style={{
                                                    background:
                                                        liveClass.subject
                                                            .color ??
                                                        'var(--primary)',
                                                }}
                                                aria-hidden
                                            />
                                            <div className="min-w-0 flex-1">
                                                <p className="font-medium">
                                                    {liveClass.title}
                                                    {live && (
                                                        <span className="ml-2 align-middle">
                                                            <TonePill tone="danger">
                                                                Live now
                                                            </TonePill>
                                                        </span>
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {liveClass.subject.name} ·{' '}
                                                    {liveClass.provider} ·{' '}
                                                    {dateTime(
                                                        liveClass.starts_at,
                                                    )}{' '}
                                                    (
                                                    {relativeTime(
                                                        liveClass.starts_at,
                                                        now,
                                                    )}
                                                    )
                                                </p>
                                            </div>
                                            {learner.is_self &&
                                                (joinable ? (
                                                    <Button asChild size="sm">
                                                        <a
                                                            href={join.url(
                                                                liveClass.id,
                                                            )}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                        >
                                                            <Video
                                                                aria-hidden
                                                            />{' '}
                                                            Join
                                                        </a>
                                                    </Button>
                                                ) : (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        disabled
                                                    >
                                                        Opens{' '}
                                                        {relativeTime(
                                                            liveClass.joinable_from,
                                                            now,
                                                        )}
                                                    </Button>
                                                ))}
                                        </li>
                                    );
                                })}
                            </ul>
                        </SectionCard>
                    )}

                    <SectionCard title="My courses" icon={BookOpenText}>
                        {courses.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Your teachers have not published any courses
                                yet.
                            </p>
                        ) : (
                            <ul className="grid gap-4 sm:grid-cols-2">
                                {courses.map((course) => (
                                    <li key={course.id}>
                                        <Link
                                            href={showCourse(course.id, {
                                                query,
                                            })}
                                            className="group flex h-full flex-col overflow-hidden rounded-2xl border bg-background transition hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            <span
                                                className="h-1.5"
                                                style={{
                                                    background:
                                                        course.subject.color ??
                                                        'var(--primary)',
                                                }}
                                                aria-hidden
                                            />
                                            <div className="flex flex-1 flex-col p-4">
                                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                    {course.subject.name}
                                                </p>
                                                <h3 className="mt-1 font-semibold group-hover:text-primary">
                                                    {course.title}
                                                </h3>
                                                {course.description && (
                                                    <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                                        {course.description}
                                                    </p>
                                                )}
                                                <ProgressBar
                                                    className="mt-auto pt-4"
                                                    percent={
                                                        course.progress.percent
                                                    }
                                                    label={`${course.progress.completed} of ${course.progress.total} lessons`}
                                                />
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </SectionCard>

                    {recent.length > 1 && (
                        <SectionCard title="Recently viewed" icon={Sparkles}>
                            <ul className="divide-y">
                                {recent.map((item) => (
                                    <li
                                        key={item.lesson_id}
                                        className="flex items-center gap-3 py-2"
                                    >
                                        <span
                                            className="size-2 rounded-full"
                                            style={{
                                                background:
                                                    item.subject_color ??
                                                    'var(--primary)',
                                            }}
                                            aria-hidden
                                        />
                                        <Link
                                            href={showLesson(item.lesson_id, {
                                                query,
                                            })}
                                            className="min-w-0 flex-1 truncate text-sm hover:underline"
                                        >
                                            {item.title}{' '}
                                            <span className="text-muted-foreground">
                                                · {item.course}
                                            </span>
                                        </Link>
                                        {item.completed && (
                                            <TonePill tone="success">
                                                Done
                                            </TonePill>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </SectionCard>
                    )}
                </div>

                <div className="grid content-start gap-6">
                    <SectionCard title="Quizzes & tests" icon={ClipboardList}>
                        {quizzes.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No quizzes right now.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {quizzes.map((quiz) => (
                                    <li key={quiz.id} className="py-2.5">
                                        <div className="flex items-start justify-between gap-2">
                                            {learner.is_self ? (
                                                <Link
                                                    href={showQuiz(quiz.id)}
                                                    className="font-medium hover:underline"
                                                >
                                                    {quiz.title}
                                                </Link>
                                            ) : (
                                                <span className="font-medium">
                                                    {quiz.title}
                                                </span>
                                            )}
                                            {quiz.best_score !== null ? (
                                                <TonePill tone="success">
                                                    {quiz.best_score} pts
                                                </TonePill>
                                            ) : quiz.is_open ? (
                                                <TonePill tone="info">
                                                    Open
                                                </TonePill>
                                            ) : (
                                                <TonePill tone="neutral">
                                                    Closed
                                                </TonePill>
                                            )}
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {quiz.type} · {quiz.subject.name}
                                            {quiz.closes_at &&
                                                ` · closes ${dateTime(quiz.closes_at)}`}{' '}
                                            · {quiz.attempts_used}/
                                            {quiz.max_attempts} attempts
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </SectionCard>

                    <SectionCard title="Homework due" icon={CalendarClock}>
                        {dueWork.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nothing due. Well done!
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {dueWork.map((work) => (
                                    <li
                                        key={work.id}
                                        className="flex items-center justify-between gap-2 py-2 text-sm"
                                    >
                                        <Link
                                            href={showAssignment(work.id)}
                                            className="min-w-0 truncate hover:underline"
                                        >
                                            {work.title}{' '}
                                            <span className="text-muted-foreground">
                                                · {work.subject}
                                            </span>
                                        </Link>
                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {date(work.due_on)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </SectionCard>

                    {bookmarks.length > 0 && (
                        <SectionCard title="Bookmarks" icon={Bookmark}>
                            <ul className="divide-y">
                                {bookmarks.map((bookmark) => (
                                    <li
                                        key={bookmark.lesson_id}
                                        className="py-2 text-sm"
                                    >
                                        <Link
                                            href={showLesson(
                                                bookmark.lesson_id,
                                                { query },
                                            )}
                                            className="hover:underline"
                                        >
                                            {bookmark.title}
                                        </Link>
                                        <p className="text-xs text-muted-foreground">
                                            {bookmark.course}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </SectionCard>
                    )}
                </div>
            </div>
        </div>
    );
}

LearnIndex.layout = {
    breadcrumbs: [{ title: 'My learning', href: index() }],
};
