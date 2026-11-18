import { Link, router } from '@inertiajs/react';
import { BookOpenText, Layers, Plus } from 'lucide-react';
import Pagination from '@/components/data/pagination';
import SearchInput from '@/components/data/search-input';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { useFormat } from '@/hooks/use-format';
import { create, index, show } from '@/routes/learning/courses';
import type {
    CourseSummary,
    PaginationMeta,
    TeachingOption,
} from '@/types/learning';

type Row = CourseSummary & {
    chapters_count: number;
    lessons_count: number;
    published_lessons_count: number;
};

type Props = {
    courses: { data: Row[]; meta: PaginationMeta };
    filters: {
        search?: string;
        school_class_id?: number;
        subject_id?: number;
        status?: string;
    };
    options: TeachingOption[];
};

export default function CoursesIndex({ courses, filters, options }: Props) {
    const { date } = useFormat();
    const subjects = Array.from(
        new Map(
            options
                .flatMap((option) => option.subjects)
                .map((subject) => [subject.id, subject]),
        ).values(),
    );

    const visit = (changes: Partial<Props['filters']>): void =>
        router.get(
            index.url(),
            { ...filters, ...changes },
            { preserveScroll: true, preserveState: true, replace: true },
        );

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Courses"
                description="Your learning content for each class and subject: chapters, lessons and materials."
                actions={
                    <Button asChild>
                        <Link href={create()}>
                            <Plus aria-hidden /> New course
                        </Link>
                    </Button>
                }
            />

            <div className="flex flex-col gap-2 md:flex-row md:items-center">
                <SearchInput
                    value={filters.search ?? ''}
                    onChange={(search) => visit({ search })}
                    placeholder="Search courses"
                />
                <NativeSelect
                    aria-label="Class"
                    value={filters.school_class_id ?? ''}
                    onChange={(event) =>
                        visit({
                            school_class_id: event.target.value
                                ? Number(event.target.value)
                                : undefined,
                        })
                    }
                    className="md:w-44"
                >
                    <option value="">All classes</option>
                    {options.map((option) => (
                        <option key={option.id} value={option.id}>
                            {option.name}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect
                    aria-label="Subject"
                    value={filters.subject_id ?? ''}
                    onChange={(event) =>
                        visit({
                            subject_id: event.target.value
                                ? Number(event.target.value)
                                : undefined,
                        })
                    }
                    className="md:w-44"
                >
                    <option value="">All subjects</option>
                    {subjects.map((subject) => (
                        <option key={subject.id} value={subject.id}>
                            {subject.name}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect
                    aria-label="Status"
                    value={filters.status ?? ''}
                    onChange={(event) =>
                        visit({ status: event.target.value || undefined })
                    }
                    className="md:w-40"
                >
                    <option value="">Any status</option>
                    <option value="published">Published</option>
                    <option value="draft">Draft</option>
                </NativeSelect>
            </div>

            {courses.data.length === 0 ? (
                <div className="rounded-2xl border border-dashed p-10 text-center">
                    <BookOpenText
                        className="mx-auto size-8 text-muted-foreground"
                        aria-hidden
                    />
                    <p className="mt-3 font-medium">No courses yet</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Create a course for a class and subject, then add
                        chapters and lessons.
                    </p>
                </div>
            ) : (
                <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {courses.data.map((course) => (
                        <li key={course.id}>
                            <Link
                                href={show(course.id)}
                                className="group flex h-full flex-col overflow-hidden rounded-2xl border bg-card shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
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
                                <div className="flex flex-1 flex-col p-5">
                                    <div className="flex items-start justify-between gap-2">
                                        <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                            {course.subject.name}
                                        </p>
                                        <TonePill
                                            tone={
                                                course.status === 'published'
                                                    ? 'success'
                                                    : 'neutral'
                                            }
                                        >
                                            {course.status === 'published'
                                                ? 'Published'
                                                : 'Draft'}
                                        </TonePill>
                                    </div>
                                    <h2 className="mt-1 text-lg font-semibold group-hover:text-primary">
                                        {course.title}
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {course.audience}
                                    </p>
                                    <div className="mt-auto flex items-center gap-4 pt-4 text-xs text-muted-foreground">
                                        <span className="inline-flex items-center gap-1">
                                            <Layers
                                                className="size-3.5"
                                                aria-hidden
                                            />
                                            {course.chapters_count} chapters
                                        </span>
                                        <span>
                                            {course.published_lessons_count}/
                                            {course.lessons_count} lessons live
                                        </span>
                                        <span className="ml-auto">
                                            {date(course.updated_at)}
                                        </span>
                                    </div>
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            <Pagination meta={courses.meta} />
        </div>
    );
}

CoursesIndex.layout = {
    breadcrumbs: [{ title: 'Courses', href: index() }],
};
