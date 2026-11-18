import { Link, router, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    Eye,
    Link2,
    Trash2,
    Upload,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import FormField from '@/components/form-field';
import {
    fileSize,
    isoToLocalInput,
    localInputToIso,
} from '@/components/learning/format';
import { materialIcons } from '@/components/learning/material-viewer';
import ProgressBar from '@/components/learning/progress-bar';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import {
    index as coursesIndex,
    show as showCourse,
} from '@/routes/learning/courses';
import { destroy as destroyLesson, update } from '@/routes/learning/lessons';
import { store as storeMaterial } from '@/routes/learning/lessons/materials';
import {
    destroy as destroyMaterial,
    move as moveMaterial,
    update as updateMaterial,
} from '@/routes/learning/materials';
import type { MaterialKind, PublishStatus } from '@/types/learning';

type Material = {
    id: number;
    kind: MaterialKind;
    kind_label: string;
    title: string;
    description: string | null;
    url: string | null;
    original_name: string | null;
    size: number;
    is_downloadable: boolean;
};

type Props = {
    lesson: {
        id: number;
        title: string;
        summary: string | null;
        body: string | null;
        estimated_minutes: number | null;
        status: PublishStatus;
        publish_at: string | null;
        chapter: string;
        course: { id: number; title: string; subject: string };
    };
    materials: Material[];
    limits: {
        max_upload_mb: number;
        extensions: string[];
        used_bytes: number;
        quota_bytes: number | null;
        allow_downloads: boolean;
    };
};

function AddMaterial({
    lessonId,
    limits,
}: {
    lessonId: number;
    limits: Props['limits'];
}) {
    const [mode, setMode] = useState<'file' | 'link'>('file');
    const form = useForm<{
        title: string;
        description: string;
        is_downloadable: boolean;
        file: File | null;
        url: string;
    }>({
        title: '',
        description: '',
        is_downloadable: limits.allow_downloads,
        file: null,
        url: '',
    });
    const [clientError, setClientError] = useState<string | null>(null);

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.transform((data) =>
            mode === 'file' ? { ...data, url: '' } : { ...data, file: null },
        );
        form.post(storeMaterial.url(lessonId), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setClientError(null);
            },
        });
    };

    const pickFile = (file: File | null): void => {
        setClientError(null);

        if (file && file.size > limits.max_upload_mb * 1024 * 1024) {
            setClientError(
                `This file is larger than ${limits.max_upload_mb} MB.`,
            );
            form.setData('file', null);

            return;
        }

        if (
            file &&
            !limits.extensions.includes(
                file.name.split('.').pop()?.toLowerCase() ?? '',
            )
        ) {
            setClientError('This file type is not allowed.');
            form.setData('file', null);

            return;
        }

        form.setData((data) => ({
            ...data,
            file,
            title: data.title || (file?.name.replace(/\.[^.]+$/, '') ?? ''),
        }));
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-4 rounded-2xl border bg-card p-5 shadow-sm"
        >
            <div className="flex items-center justify-between gap-2">
                <h2 className="font-semibold">Add material</h2>
                <div
                    className="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1 text-sm"
                    role="tablist"
                    aria-label="Material type"
                >
                    {(['file', 'link'] as const).map((option) => (
                        <button
                            key={option}
                            type="button"
                            role="tab"
                            aria-selected={mode === option}
                            onClick={() => setMode(option)}
                            className={cn(
                                'rounded-md px-3 py-1',
                                mode === option &&
                                    'bg-background font-medium shadow-sm',
                            )}
                        >
                            {option === 'file' ? 'Upload file' : 'Add link'}
                        </button>
                    ))}
                </div>
            </div>

            {mode === 'file' ? (
                <FormField
                    id="material-file"
                    label="File"
                    error={clientError ?? form.errors.file}
                    description={`PDF, Word, PowerPoint, Excel, MP3, MP4, images. Up to ${limits.max_upload_mb} MB.`}
                >
                    <Input
                        id="material-file"
                        type="file"
                        accept={limits.extensions
                            .map((extension) => `.${extension}`)
                            .join(',')}
                        onChange={(event) =>
                            pickFile(event.target.files?.[0] ?? null)
                        }
                    />
                </FormField>
            ) : (
                <FormField
                    id="material-url"
                    label="Link"
                    error={form.errors.url}
                    description="A YouTube video, website, Google Doc or any other https:// link."
                >
                    <Input
                        id="material-url"
                        type="url"
                        placeholder="https://"
                        value={form.data.url}
                        onChange={(event) =>
                            form.setData('url', event.target.value)
                        }
                    />
                </FormField>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
                <FormField
                    id="material-title"
                    label="Title"
                    required
                    error={form.errors.title}
                >
                    <Input
                        id="material-title"
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                    />
                </FormField>
                <FormField
                    id="material-description"
                    label="Note for students"
                    error={form.errors.description}
                >
                    <Input
                        id="material-description"
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData('description', event.target.value)
                        }
                    />
                </FormField>
            </div>

            {mode === 'file' && (
                <div className="flex items-center gap-2">
                    <Checkbox
                        id="material-download"
                        checked={form.data.is_downloadable}
                        onCheckedChange={(checked) =>
                            form.setData('is_downloadable', checked === true)
                        }
                    />
                    <Label htmlFor="material-download" className="font-normal">
                        Students may download this file
                    </Label>
                </div>
            )}

            {form.progress && (
                <ProgressBar
                    percent={form.progress.percentage ?? 0}
                    label="Uploading"
                />
            )}

            <div>
                <Button
                    type="submit"
                    disabled={
                        form.processing ||
                        (mode === 'file'
                            ? !form.data.file
                            : form.data.url === '')
                    }
                >
                    {form.processing ? (
                        <Spinner />
                    ) : mode === 'file' ? (
                        <Upload aria-hidden />
                    ) : (
                        <Link2 aria-hidden />
                    )}
                    {mode === 'file' ? 'Upload' : 'Add link'}
                </Button>
            </div>
        </form>
    );
}

export default function LessonEdit({ lesson, materials, limits }: Props) {
    const form = useForm({
        title: lesson.title,
        summary: lesson.summary ?? '',
        body: lesson.body ?? '',
        estimated_minutes: lesson.estimated_minutes ?? '',
        status: lesson.status,
        publish_at: isoToLocalInput(lesson.publish_at),
    });

    const save = (event: FormEvent): void => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            estimated_minutes:
                data.estimated_minutes === '' ? null : data.estimated_minutes,
            publish_at: localInputToIso(data.publish_at),
        }));
        form.put(update.url(lesson.id), { preserveScroll: true });
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={lesson.title}
                description={`${lesson.course.subject} · ${lesson.course.title} · ${lesson.chapter}`}
                actions={
                    <Button asChild variant="outline">
                        <Link href={showCourse(lesson.course.id)}>
                            <ArrowLeft aria-hidden /> Back to course
                        </Link>
                    </Button>
                }
            />

            <div className="grid gap-6 xl:grid-cols-[1.2fr_1fr]">
                <form
                    onSubmit={save}
                    className="grid content-start gap-5 rounded-2xl border bg-card p-6 shadow-sm sm:grid-cols-2"
                >
                    <FormField
                        id="lesson-title"
                        label="Lesson title"
                        required
                        error={form.errors.title}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="lesson-title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="lesson-summary"
                        label="Summary"
                        error={form.errors.summary}
                        className="sm:col-span-2"
                        description="One or two sentences shown in the lesson list."
                    >
                        <Textarea
                            id="lesson-summary"
                            rows={2}
                            value={form.data.summary}
                            onChange={(event) =>
                                form.setData('summary', event.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="lesson-body"
                        label="Lesson notes"
                        error={form.errors.body}
                        className="sm:col-span-2"
                        description="Explanations, instructions or reading for students. Line breaks are kept."
                    >
                        <Textarea
                            id="lesson-body"
                            rows={10}
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="lesson-minutes"
                        label="Time needed (minutes)"
                        error={form.errors.estimated_minutes}
                    >
                        <Input
                            id="lesson-minutes"
                            type="number"
                            min={1}
                            max={600}
                            value={form.data.estimated_minutes}
                            onChange={(event) =>
                                form.setData(
                                    'estimated_minutes',
                                    event.target.value === ''
                                        ? ''
                                        : Number(event.target.value),
                                )
                            }
                        />
                    </FormField>
                    <FormField
                        id="lesson-status"
                        label="Status"
                        error={form.errors.status}
                    >
                        <NativeSelect
                            id="lesson-status"
                            value={form.data.status}
                            onChange={(event) =>
                                form.setData(
                                    'status',
                                    event.target.value as PublishStatus,
                                )
                            }
                        >
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </NativeSelect>
                    </FormField>
                    <FormField
                        id="lesson-publish"
                        label="Publish on"
                        error={form.errors.publish_at}
                        description="Optional. Students are notified when it goes live."
                        className="sm:col-span-2"
                    >
                        <Input
                            id="lesson-publish"
                            type="datetime-local"
                            value={form.data.publish_at}
                            onChange={(event) =>
                                form.setData('publish_at', event.target.value)
                            }
                        />
                    </FormField>
                    <div className="flex flex-wrap gap-2 sm:col-span-2">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Save lesson
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            className="text-destructive"
                            onClick={() => {
                                if (
                                    window.confirm(
                                        'Delete this lesson and its materials?',
                                    )
                                ) {
                                    router.delete(destroyLesson.url(lesson.id));
                                }
                            }}
                        >
                            <Trash2 aria-hidden /> Delete lesson
                        </Button>
                    </div>
                </form>

                <div className="grid content-start gap-4">
                    <AddMaterial lessonId={lesson.id} limits={limits} />

                    <section
                        className="rounded-2xl border bg-card shadow-sm"
                        aria-labelledby="materials-title"
                    >
                        <header className="flex items-center justify-between border-b px-5 py-3">
                            <h2 id="materials-title" className="font-semibold">
                                Materials ({materials.length})
                            </h2>
                            {limits.quota_bytes !== null && (
                                <span className="text-xs text-muted-foreground">
                                    {fileSize(limits.used_bytes)} of{' '}
                                    {fileSize(limits.quota_bytes)} used
                                </span>
                            )}
                        </header>
                        {materials.length === 0 ? (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                No materials yet.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {materials.map((material) => {
                                    const Icon = materialIcons[material.kind];

                                    return (
                                        <li
                                            key={material.id}
                                            className="flex items-start gap-3 px-5 py-3"
                                        >
                                            <Icon
                                                className="mt-0.5 size-5 shrink-0 text-navy-600 dark:text-navy-300"
                                                aria-hidden
                                            />
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate font-medium">
                                                    {material.title}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {material.kind_label}
                                                    {material.size > 0 &&
                                                        ` · ${fileSize(material.size)}`}
                                                    {material.kind !== 'link' &&
                                                        (material.is_downloadable
                                                            ? ' · downloadable'
                                                            : ' · view only')}
                                                </p>
                                                {material.kind !== 'link' && (
                                                    <button
                                                        type="button"
                                                        className="mt-1 text-xs text-primary hover:underline"
                                                        onClick={() =>
                                                            router.put(
                                                                updateMaterial.url(
                                                                    material.id,
                                                                ),
                                                                {
                                                                    title: material.title,
                                                                    description:
                                                                        material.description,
                                                                    is_downloadable:
                                                                        !material.is_downloadable,
                                                                },
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        {material.is_downloadable
                                                            ? 'Make view only'
                                                            : 'Allow download'}
                                                    </button>
                                                )}
                                            </div>
                                            <div className="flex shrink-0 gap-1">
                                                {material.url && (
                                                    <Button
                                                        asChild
                                                        size="icon"
                                                        variant="ghost"
                                                        className="size-8"
                                                    >
                                                        <a
                                                            href={material.url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            aria-label={`Preview ${material.title}`}
                                                        >
                                                            <Eye aria-hidden />
                                                        </a>
                                                    </Button>
                                                )}
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    className="size-8"
                                                    aria-label="Move up"
                                                    onClick={() =>
                                                        router.post(
                                                            moveMaterial.url(
                                                                material.id,
                                                            ),
                                                            { direction: 'up' },
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <ArrowUp aria-hidden />
                                                </Button>
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    className="size-8"
                                                    aria-label="Move down"
                                                    onClick={() =>
                                                        router.post(
                                                            moveMaterial.url(
                                                                material.id,
                                                            ),
                                                            {
                                                                direction:
                                                                    'down',
                                                            },
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <ArrowDown aria-hidden />
                                                </Button>
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    className="size-8 text-destructive"
                                                    aria-label={`Remove ${material.title}`}
                                                    onClick={() => {
                                                        if (
                                                            window.confirm(
                                                                `Remove “${material.title}”?`,
                                                            )
                                                        ) {
                                                            router.delete(
                                                                destroyMaterial.url(
                                                                    material.id,
                                                                ),
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }
                                                    }}
                                                >
                                                    <Trash2 aria-hidden />
                                                </Button>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </section>
                </div>
            </div>
        </div>
    );
}

LessonEdit.layout = {
    breadcrumbs: [{ title: 'Courses', href: coursesIndex() }],
};
