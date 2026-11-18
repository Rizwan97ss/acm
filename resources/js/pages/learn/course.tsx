import { Link } from '@inertiajs/react';
import { CheckCircle2, Circle, Clock } from 'lucide-react';
import ProgressBar from '@/components/learning/progress-bar';
import PageHeader from '@/components/page-header';
import { index } from '@/routes/learn';
import { show as showLesson } from '@/routes/learn/lessons';
import type { Progress, SubjectBadge } from '@/types/learning';

type Props = {
    learner: { id: number; name: string; is_self: boolean };
    course: {
        id: number;
        title: string;
        description: string | null;
        subject: SubjectBadge;
        teacher: string | null;
        progress: Progress;
    };
    chapters: {
        id: number;
        title: string;
        description: string | null;
        lessons: {
            id: number;
            title: string;
            summary: string | null;
            estimated_minutes: number | null;
            completed: boolean;
        }[];
    }[];
};

export default function LearnCourse({ learner, course, chapters }: Props) {
    const query = learner.is_self ? undefined : { student: learner.id };
    const next = chapters
        .flatMap((chapter) => chapter.lessons)
        .find((lesson) => !lesson.completed);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={course.title}
                description={`${course.subject.name}${course.teacher ? ` · ${course.teacher}` : ''}`}
            />

            <div className="grid gap-4 rounded-2xl border bg-card p-5 shadow-sm md:grid-cols-[1fr_16rem] md:items-center">
                <div>
                    {course.description && (
                        <p className="text-muted-foreground">
                            {course.description}
                        </p>
                    )}
                    {next && (
                        <Link
                            href={showLesson(next.id, { query })}
                            className="mt-3 inline-flex items-center rounded-lg bg-navy-900 px-4 py-2 text-sm font-semibold text-white shadow-[3px_3px_0_var(--color-gold-400)] hover:bg-navy-800"
                        >
                            {course.progress.completed === 0
                                ? 'Start the course'
                                : `Next: ${next.title}`}
                        </Link>
                    )}
                </div>
                <ProgressBar
                    percent={course.progress.percent}
                    label={`${course.progress.completed} of ${course.progress.total} lessons done`}
                />
            </div>

            {chapters.length === 0 ? (
                <p className="rounded-2xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                    No lessons have been published yet.
                </p>
            ) : (
                <ol className="grid gap-4">
                    {chapters.map((chapter, position) => (
                        <li
                            key={chapter.id}
                            className="overflow-hidden rounded-2xl border bg-card shadow-sm"
                        >
                            <header className="border-b bg-muted/40 px-5 py-3">
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Chapter {position + 1}
                                </p>
                                <h2 className="font-semibold">
                                    {chapter.title}
                                </h2>
                                {chapter.description && (
                                    <p className="text-sm text-muted-foreground">
                                        {chapter.description}
                                    </p>
                                )}
                            </header>
                            <ol className="divide-y">
                                {chapter.lessons.map((lesson) => (
                                    <li key={lesson.id}>
                                        <Link
                                            href={showLesson(lesson.id, {
                                                query,
                                            })}
                                            className="flex items-start gap-3 px-5 py-3 transition hover:bg-muted/40 focus-visible:bg-muted/40 focus-visible:outline-none"
                                        >
                                            {lesson.completed ? (
                                                <CheckCircle2
                                                    className="mt-0.5 size-5 shrink-0 text-emerald-600"
                                                    aria-label="Completed"
                                                />
                                            ) : (
                                                <Circle
                                                    className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                                    aria-label="Not completed"
                                                />
                                            )}
                                            <div className="min-w-0 flex-1">
                                                <p className="font-medium">
                                                    {lesson.title}
                                                </p>
                                                {lesson.summary && (
                                                    <p className="text-sm text-muted-foreground">
                                                        {lesson.summary}
                                                    </p>
                                                )}
                                            </div>
                                            {lesson.estimated_minutes && (
                                                <span className="inline-flex shrink-0 items-center gap-1 text-xs text-muted-foreground">
                                                    <Clock
                                                        className="size-3.5"
                                                        aria-hidden
                                                    />
                                                    {lesson.estimated_minutes}{' '}
                                                    min
                                                </span>
                                            )}
                                        </Link>
                                    </li>
                                ))}
                            </ol>
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
}

LearnCourse.layout = {
    breadcrumbs: [{ title: 'My learning', href: index() }],
};
