import { Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Bookmark,
    BookmarkCheck,
    CheckCircle2,
    Circle,
    Clock,
} from 'lucide-react';
import type { StudentMaterial } from '@/components/learning/material-viewer';
import MaterialViewer from '@/components/learning/material-viewer';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/learn';
import { show as showCourse } from '@/routes/learn/courses';
import { bookmark, complete, show as showLesson } from '@/routes/learn/lessons';

type Props = {
    learner: { id: number; name: string; is_self: boolean };
    lesson: {
        id: number;
        title: string;
        summary: string | null;
        body: string | null;
        estimated_minutes: number | null;
        chapter: string;
        course: {
            id: number;
            title: string;
            subject: string;
            color: string | null;
        };
        completed: boolean;
        bookmarked: boolean;
    };
    materials: StudentMaterial[];
    previousId: number | null;
    nextId: number | null;
};

export default function LearnLesson({
    learner,
    lesson,
    materials,
    previousId,
    nextId,
}: Props) {
    const query = learner.is_self ? undefined : { student: learner.id };

    const toggleComplete = (): void => {
        router.post(
            complete.url(lesson.id),
            { completed: !lesson.completed },
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (!lesson.completed && nextId) {
                        router.visit(showLesson.url(nextId));
                    }
                },
            },
        );
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={lesson.title}
                description={`${lesson.course.subject} · ${lesson.course.title} · ${lesson.chapter}`}
                actions={
                    <>
                        <Button asChild variant="outline">
                            <Link
                                href={showCourse(lesson.course.id, { query })}
                            >
                                <ArrowLeft aria-hidden /> Course
                            </Link>
                        </Button>
                        {learner.is_self && (
                            <Button
                                variant="outline"
                                aria-pressed={lesson.bookmarked}
                                onClick={() =>
                                    router.post(
                                        bookmark.url(lesson.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {lesson.bookmarked ? (
                                    <BookmarkCheck aria-hidden />
                                ) : (
                                    <Bookmark aria-hidden />
                                )}
                                {lesson.bookmarked ? 'Bookmarked' : 'Bookmark'}
                            </Button>
                        )}
                    </>
                }
            />

            <div className="grid gap-6 xl:grid-cols-[1fr_20rem]">
                <div className="grid content-start gap-5">
                    {(lesson.summary || lesson.body) && (
                        <article className="rounded-2xl border bg-card p-6 shadow-sm">
                            {lesson.summary && (
                                <p className="text-lg text-muted-foreground">
                                    {lesson.summary}
                                </p>
                            )}
                            {lesson.body && (
                                <div className="mt-4 leading-relaxed whitespace-pre-line">
                                    {lesson.body}
                                </div>
                            )}
                        </article>
                    )}
                    {materials.map((material) => (
                        <MaterialViewer key={material.id} material={material} />
                    ))}
                    {materials.length === 0 && !lesson.body && (
                        <p className="rounded-2xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                            Your teacher has not added anything to this lesson
                            yet.
                        </p>
                    )}
                </div>

                <aside className="grid content-start gap-4 xl:sticky xl:top-20">
                    <div className="rounded-2xl border bg-card p-5 shadow-sm">
                        {lesson.estimated_minutes && (
                            <p className="mb-3 flex items-center gap-2 text-sm text-muted-foreground">
                                <Clock className="size-4" aria-hidden /> About{' '}
                                {lesson.estimated_minutes} minutes
                            </p>
                        )}
                        {learner.is_self ? (
                            <Button
                                className="w-full"
                                variant={
                                    lesson.completed ? 'outline' : 'default'
                                }
                                onClick={toggleComplete}
                            >
                                {lesson.completed ? (
                                    <CheckCircle2
                                        aria-hidden
                                        className="text-emerald-600"
                                    />
                                ) : (
                                    <Circle aria-hidden />
                                )}
                                {lesson.completed
                                    ? 'Completed'
                                    : nextId
                                      ? 'Mark complete and continue'
                                      : 'Mark as complete'}
                            </Button>
                        ) : (
                            <p className="text-sm">
                                {lesson.completed
                                    ? `${learner.name} has completed this lesson.`
                                    : `${learner.name} has not completed this lesson yet.`}
                            </p>
                        )}
                    </div>
                    <nav
                        aria-label="Lessons"
                        className="grid grid-cols-2 gap-2"
                    >
                        {previousId ? (
                            <Button asChild variant="outline">
                                <Link href={showLesson(previousId, { query })}>
                                    <ArrowLeft aria-hidden /> Previous
                                </Link>
                            </Button>
                        ) : (
                            <span />
                        )}
                        {nextId && (
                            <Button asChild variant="outline">
                                <Link href={showLesson(nextId, { query })}>
                                    Next <ArrowRight aria-hidden />
                                </Link>
                            </Button>
                        )}
                    </nav>
                </aside>
            </div>
        </div>
    );
}

LearnLesson.layout = {
    breadcrumbs: [{ title: 'My learning', href: index() }],
};
