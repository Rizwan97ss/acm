import { Link, router, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    CheckCircle2,
    Clock,
    Paperclip,
    Pencil,
    Plus,
    Trash2,
    Users,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import ConfirmDelete from '@/components/confirm-delete';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/hooks/use-format';
import {
    destroy as destroyChapter,
    move as moveChapter,
    update as updateChapter,
} from '@/routes/learning/chapters';
import { store as storeLesson } from '@/routes/learning/chapters/lessons';
import { destroy, edit, index } from '@/routes/learning/courses';
import { store as storeChapter } from '@/routes/learning/courses/chapters';
import {
    edit as editLesson,
    move as moveLesson,
} from '@/routes/learning/lessons';
import type { CourseSummary, PublishStatus } from '@/types/learning';

type LessonRow = {
    id: number;
    title: string;
    status: PublishStatus;
    publish_at: string | null;
    estimated_minutes: number | null;
    materials_count: number;
    completed_count: number;
};

type Chapter = {
    id: number;
    title: string;
    description: string | null;
    lessons: LessonRow[];
};

type Props = {
    course: CourseSummary & { student_ids: number[]; audience_size: number };
    chapters: Chapter[];
};

function AddLesson({ chapterId }: { chapterId: number }) {
    const form = useForm({ title: '' });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.post(storeLesson.url(chapterId), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="flex gap-2 border-t p-3">
            <Input
                aria-label="New lesson title"
                placeholder="New lesson title"
                value={form.data.title}
                onChange={(event) => form.setData('title', event.target.value)}
                className="h-9"
            />
            <Button
                type="submit"
                size="sm"
                variant="outline"
                disabled={form.processing || form.data.title.trim() === ''}
            >
                <Plus aria-hidden /> Add lesson
            </Button>
        </form>
    );
}

function ChapterCard({
    chapter,
    number,
    audience,
}: {
    chapter: Chapter;
    number: number;
    audience: number;
}) {
    const { dateTime } = useFormat();
    const [renaming, setRenaming] = useState(false);
    const form = useForm({
        title: chapter.title,
        description: chapter.description ?? '',
    });
    const move = (direction: 'up' | 'down'): void =>
        router.post(
            moveChapter.url(chapter.id),
            { direction },
            { preserveScroll: true },
        );

    return (
        <section
            className="overflow-hidden rounded-2xl border bg-card shadow-sm"
            aria-labelledby={`chapter-${chapter.id}`}
        >
            <header className="flex flex-wrap items-center gap-2 border-b bg-muted/40 px-4 py-3">
                <span
                    className="flex size-7 items-center justify-center rounded-lg bg-navy-700 text-xs font-bold text-white"
                    aria-hidden
                >
                    {number}
                </span>
                {renaming ? (
                    <form
                        className="flex flex-1 gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.put(updateChapter.url(chapter.id), {
                                preserveScroll: true,
                                onSuccess: () => setRenaming(false),
                            });
                        }}
                    >
                        <Input
                            aria-label="Chapter title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            className="h-8"
                            autoFocus
                        />
                        <Button
                            size="sm"
                            type="submit"
                            disabled={form.processing}
                        >
                            Save
                        </Button>
                    </form>
                ) : (
                    <h2
                        id={`chapter-${chapter.id}`}
                        className="flex-1 font-semibold"
                    >
                        {chapter.title}
                    </h2>
                )}
                <div className="flex gap-1">
                    <Button
                        size="icon"
                        variant="ghost"
                        className="size-8"
                        aria-label="Move chapter up"
                        onClick={() => move('up')}
                    >
                        <ArrowUp aria-hidden />
                    </Button>
                    <Button
                        size="icon"
                        variant="ghost"
                        className="size-8"
                        aria-label="Move chapter down"
                        onClick={() => move('down')}
                    >
                        <ArrowDown aria-hidden />
                    </Button>
                    <Button
                        size="icon"
                        variant="ghost"
                        className="size-8"
                        aria-label="Rename chapter"
                        onClick={() => setRenaming(!renaming)}
                    >
                        <Pencil aria-hidden />
                    </Button>
                    {chapter.lessons.length === 0 && (
                        <Button
                            size="icon"
                            variant="ghost"
                            className="size-8 text-destructive"
                            aria-label="Delete chapter"
                            onClick={() =>
                                router.delete(destroyChapter.url(chapter.id), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            <Trash2 aria-hidden />
                        </Button>
                    )}
                </div>
            </header>

            {chapter.lessons.length === 0 ? (
                <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                    No lessons yet. Add the first one below.
                </p>
            ) : (
                <ol className="divide-y">
                    {chapter.lessons.map((lesson) => (
                        <li
                            key={lesson.id}
                            className="flex flex-wrap items-center gap-3 px-4 py-3"
                        >
                            <div className="min-w-0 flex-1">
                                <Link
                                    href={editLesson(lesson.id)}
                                    className="font-medium hover:text-primary hover:underline"
                                >
                                    {lesson.title}
                                </Link>
                                <div className="mt-0.5 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                    <span className="inline-flex items-center gap-1">
                                        <Paperclip
                                            className="size-3"
                                            aria-hidden
                                        />
                                        {lesson.materials_count} materials
                                    </span>
                                    {lesson.estimated_minutes && (
                                        <span className="inline-flex items-center gap-1">
                                            <Clock
                                                className="size-3"
                                                aria-hidden
                                            />
                                            {lesson.estimated_minutes} min
                                        </span>
                                    )}
                                    {lesson.status === 'published' && (
                                        <span className="inline-flex items-center gap-1">
                                            <CheckCircle2
                                                className="size-3 text-emerald-600"
                                                aria-hidden
                                            />
                                            {lesson.completed_count}/{audience}{' '}
                                            completed
                                        </span>
                                    )}
                                    {lesson.publish_at && (
                                        <span>
                                            From {dateTime(lesson.publish_at)}
                                        </span>
                                    )}
                                </div>
                            </div>
                            <TonePill
                                tone={
                                    lesson.status === 'published'
                                        ? 'success'
                                        : 'neutral'
                                }
                            >
                                {lesson.status === 'published'
                                    ? 'Published'
                                    : 'Draft'}
                            </TonePill>
                            <div className="flex gap-1">
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    className="size-8"
                                    aria-label={`Move ${lesson.title} up`}
                                    onClick={() =>
                                        router.post(
                                            moveLesson.url(lesson.id),
                                            { direction: 'up' },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <ArrowUp aria-hidden />
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    className="size-8"
                                    aria-label={`Move ${lesson.title} down`}
                                    onClick={() =>
                                        router.post(
                                            moveLesson.url(lesson.id),
                                            { direction: 'down' },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <ArrowDown aria-hidden />
                                </Button>
                            </div>
                        </li>
                    ))}
                </ol>
            )}
            <AddLesson chapterId={chapter.id} />
        </section>
    );
}

export default function CourseShow({ course, chapters }: Props) {
    const form = useForm({ title: '' });

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={course.title}
                description={`${course.subject.name} · ${course.audience}`}
                actions={
                    <>
                        <Button asChild variant="outline">
                            <Link href={edit(course.id)}>
                                <Pencil aria-hidden /> Course settings
                            </Link>
                        </Button>
                        <ConfirmDelete
                            action={destroy(course.id)}
                            title="Delete this course?"
                            description="All chapters, lessons, uploaded files and student progress for this course will be removed permanently."
                        />
                    </>
                }
            />

            <div className="flex flex-wrap items-center gap-3 rounded-2xl border bg-card p-4 text-sm shadow-sm">
                <TonePill
                    tone={course.status === 'published' ? 'success' : 'warning'}
                >
                    {course.status === 'published'
                        ? 'Published to students'
                        : 'Draft: students cannot see this yet'}
                </TonePill>
                <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                    <Users className="size-4" aria-hidden />
                    {course.audience_size} students
                    {course.student_ids.length > 0 &&
                        ' (selected students only)'}
                </span>
                {course.description && (
                    <p className="w-full text-muted-foreground">
                        {course.description}
                    </p>
                )}
            </div>

            <div className="grid gap-4">
                {chapters.map((chapter, position) => (
                    <ChapterCard
                        key={chapter.id}
                        chapter={chapter}
                        number={position + 1}
                        audience={course.audience_size}
                    />
                ))}
            </div>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(storeChapter.url(course.id), {
                        preserveScroll: true,
                        onSuccess: () => form.reset(),
                    });
                }}
                className="flex max-w-xl gap-2"
            >
                <Input
                    aria-label="New chapter title"
                    placeholder="New chapter title"
                    value={form.data.title}
                    onChange={(event) =>
                        form.setData('title', event.target.value)
                    }
                />
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing || form.data.title.trim() === ''}
                >
                    <Plus aria-hidden /> Add chapter
                </Button>
            </form>
        </div>
    );
}

CourseShow.layout = {
    breadcrumbs: [{ title: 'Courses', href: index() }],
};
