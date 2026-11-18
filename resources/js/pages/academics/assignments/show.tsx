import { Link, useForm } from '@inertiajs/react';
import { CalendarClock, Paperclip, Pencil } from 'lucide-react';
import type { FormEvent } from 'react';
import ConfirmDelete from '@/components/confirm-delete';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { fileSize } from '@/components/learning/format';
import { useFormat } from '@/hooks/use-format';
import {
    destroy,
    edit,
    grade,
    index,
    submit as submitRoute,
} from '@/routes/academics/assignments';
import type { AssignmentSummary } from '@/types';

type FileLink = { id: number; name: string; size: number; url: string };

function FileList({ files }: { files: FileLink[] }) {
    if (files.length === 0) {
        return null;
    }

    return (
        <ul className="flex flex-wrap gap-2">
            {files.map((file) => (
                <li key={file.id}>
                    <a
                        href={file.url}
                        className="inline-flex items-center gap-1.5 rounded-lg border bg-background px-3 py-1.5 text-sm hover:bg-muted"
                    >
                        <Paperclip
                            className="size-3.5 text-muted-foreground"
                            aria-hidden
                        />
                        {file.name}
                        <span className="text-xs text-muted-foreground">
                            {fileSize(file.size)}
                        </span>
                    </a>
                </li>
            ))}
        </ul>
    );
}

type Submission = {
    id: number;
    files?: FileLink[];
    content: string;
    submitted_at: string;
    late: boolean;
    marks: number | null;
    feedback: string | null;
    graded: boolean;
};

type Props = {
    assignment: AssignmentSummary & {
        attachments?: FileLink[];
        for_chosen_students?: boolean;
    };
    students: { id: number; name: string; submission: Submission | null }[];
    can: { update: boolean; grade: boolean; submit: boolean };
};

function GradeForm({
    assignment,
    submission,
}: {
    assignment: AssignmentSummary;
    submission: Submission;
}) {
    const form = useForm({
        marks: submission.marks === null ? '' : String(submission.marks),
        feedback: submission.feedback ?? '',
    });

    const save = (event: FormEvent): void => {
        event.preventDefault();
        form.put(grade.url([assignment.id, submission.id]), {
            preserveScroll: true,
        });
    };

    return (
        <form
            onSubmit={save}
            className="grid gap-2 sm:grid-cols-[7rem_1fr_auto] sm:items-end"
        >
            {assignment.max_marks !== null && (
                <FormField
                    id={`marks-${submission.id}`}
                    label={`Marks /${assignment.max_marks}`}
                    error={form.errors.marks}
                >
                    <Input
                        id={`marks-${submission.id}`}
                        type="number"
                        min={0}
                        max={assignment.max_marks}
                        step="0.5"
                        value={form.data.marks}
                        onChange={(e) => form.setData('marks', e.target.value)}
                    />
                </FormField>
            )}
            <FormField
                id={`feedback-${submission.id}`}
                label="Feedback"
                error={form.errors.feedback}
                className={
                    assignment.max_marks === null ? 'sm:col-span-2' : undefined
                }
            >
                <Input
                    id={`feedback-${submission.id}`}
                    value={form.data.feedback}
                    onChange={(e) => form.setData('feedback', e.target.value)}
                />
            </FormField>
            <Button type="submit" size="sm" disabled={form.processing}>
                Save
            </Button>
        </form>
    );
}

function SubmitForm({
    assignment,
    existing,
}: {
    assignment: AssignmentSummary;
    existing: Submission | null;
}) {
    const form = useForm({
        content: existing?.content ?? '',
        files: [] as File[],
    });

    const send = (event: FormEvent): void => {
        event.preventDefault();
        form.post(submitRoute.url(assignment.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => form.setData('files', []),
        });
    };

    return (
        <form onSubmit={send} className="space-y-3">
            <FormField
                id="content"
                label="Your answer"
                error={form.errors.content}
                required
            >
                <Textarea
                    id="content"
                    rows={8}
                    value={form.data.content}
                    onChange={(e) => form.setData('content', e.target.value)}
                />
            </FormField>
            <FormField
                id="submission-files"
                label="Attach your work"
                error={
                    form.errors.files ??
                    (form.errors as Record<string, string>)['files.0']
                }
                description="Optional: photos of written work, documents or recordings (up to 5 files)."
            >
                <Input
                    id="submission-files"
                    type="file"
                    multiple
                    onChange={(e) =>
                        form.setData(
                            'files',
                            Array.from(e.target.files ?? []).slice(0, 5),
                        )
                    }
                />
            </FormField>
            {existing?.files && existing.files.length > 0 && (
                <FileList files={existing.files} />
            )}
            <Button
                type="submit"
                disabled={form.processing}
                className="bg-gradient-to-r from-navy-600 to-royal-600 text-white"
            >
                {existing ? 'Update submission' : 'Submit'}
            </Button>
        </form>
    );
}

export default function ShowAssignment({ assignment, students, can }: Props) {
    const format = useFormat();
    const submittedCount = students.filter(
        (student) => student.submission !== null,
    ).length;
    const own = can.submit ? students[0] : undefined;

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={assignment.title}
                description={`${assignment.subject?.name} · ${assignment.section} · set by ${assignment.author ?? 'staff'}`}
                actions={
                    can.update && (
                        <>
                            <Button variant="outline" asChild>
                                <Link href={edit(assignment.id)}>
                                    <Pencil aria-hidden /> Edit
                                </Link>
                            </Button>
                            <ConfirmDelete
                                action={destroy(assignment.id)}
                                title="Delete this assignment?"
                                description="Submissions and feedback are deleted too."
                            />
                        </>
                    )
                }
            />
            <Card className="overflow-hidden">
                <div
                    className="h-1.5"
                    style={{
                        backgroundColor:
                            assignment.subject?.color ?? 'var(--primary)',
                    }}
                    aria-hidden
                />
                <CardHeader className="flex flex-row flex-wrap items-center gap-2">
                    <TonePill tone="info">{assignment.type_label}</TonePill>
                    <span className="flex items-center gap-1 text-sm text-muted-foreground">
                        <CalendarClock className="size-4" aria-hidden /> Due{' '}
                        {format.date(`${assignment.due_on}T12:00:00Z`)}
                    </span>
                    {assignment.max_marks !== null && (
                        <span className="text-sm text-muted-foreground">
                            · marked out of {assignment.max_marks}
                        </span>
                    )}
                </CardHeader>
                <CardContent>
                    <p className="text-sm whitespace-pre-line">
                        {assignment.description}
                    </p>
                    {assignment.attachments &&
                        assignment.attachments.length > 0 && (
                            <div className="mt-4">
                                <FileList files={assignment.attachments} />
                            </div>
                        )}
                    {assignment.for_chosen_students && !can.submit && (
                        <p className="mt-3 text-xs text-muted-foreground">
                            Set for selected students only.
                        </p>
                    )}
                </CardContent>
            </Card>

            {can.submit && own && (
                <Card>
                    <CardHeader>
                        <CardTitle>Your work</CardTitle>
                        {own.submission && (
                            <CardDescription>
                                Submitted{' '}
                                {format.dateTime(own.submission.submitted_at)}
                                {own.submission.late && ' (late)'}
                            </CardDescription>
                        )}
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {own.submission?.graded ? (
                            <div className="rounded-xl bg-emerald-50 p-4 text-sm dark:bg-emerald-500/10">
                                <p className="font-semibold">
                                    Graded
                                    {own.submission.marks !== null &&
                                        `: ${own.submission.marks}/${assignment.max_marks}`}
                                </p>
                                {own.submission.feedback && (
                                    <p className="mt-1">
                                        {own.submission.feedback}
                                    </p>
                                )}
                            </div>
                        ) : (
                            <SubmitForm
                                assignment={assignment}
                                existing={own.submission}
                            />
                        )}
                    </CardContent>
                </Card>
            )}

            {!can.submit && (
                <Card>
                    <CardHeader>
                        <CardTitle>Submissions</CardTitle>
                        <CardDescription>
                            {submittedCount} of {students.length} submitted
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="divide-y">
                            {students.map((student) => (
                                <li key={student.id} className="space-y-2 py-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="font-medium">
                                            {student.name}
                                        </span>
                                        {student.submission === null ? (
                                            <TonePill tone="warning">
                                                Not submitted
                                            </TonePill>
                                        ) : student.submission.graded ? (
                                            <TonePill tone="success">
                                                Graded
                                                {student.submission.marks !==
                                                null
                                                    ? ` · ${student.submission.marks}`
                                                    : ''}
                                            </TonePill>
                                        ) : (
                                            <TonePill
                                                tone={
                                                    student.submission.late
                                                        ? 'danger'
                                                        : 'info'
                                                }
                                            >
                                                {student.submission.late
                                                    ? 'Submitted late'
                                                    : 'Submitted'}
                                            </TonePill>
                                        )}
                                    </div>
                                    {student.submission && (
                                        <>
                                            {student.submission.content && (
                                                <p className="rounded-xl bg-muted/60 p-3 text-sm whitespace-pre-line">
                                                    {student.submission.content}
                                                </p>
                                            )}
                                            <FileList
                                                files={
                                                    student.submission.files ??
                                                    []
                                                }
                                            />
                                            {can.grade ? (
                                                <GradeForm
                                                    assignment={assignment}
                                                    submission={
                                                        student.submission
                                                    }
                                                />
                                            ) : (
                                                student.submission.feedback && (
                                                    <p className="text-sm text-muted-foreground">
                                                        Feedback:{' '}
                                                        {
                                                            student.submission
                                                                .feedback
                                                        }
                                                    </p>
                                                )
                                            )}
                                        </>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

ShowAssignment.layout = {
    breadcrumbs: [{ title: 'Assignments', href: index() }],
};
