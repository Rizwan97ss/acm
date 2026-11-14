import { Link, router, useForm } from '@inertiajs/react';
import {
    BarChart3,
    Eye,
    EyeOff,
    Pencil,
    PenLine,
    Plus,
    Sparkles,
    Trash2,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useFormat } from '@/hooks/use-format';
import {
    edit,
    index,
    publish,
    results,
    unpublish,
} from '@/routes/academics/exams';
import { edit as marks } from '@/routes/academics/exams/marks';
import {
    destroy as destroyPaper,
    generate,
    store as storePaper,
    update as updatePaper,
} from '@/routes/academics/exams/papers';
import type { ExamSummary, Option } from '@/types';

type Paper = {
    id: number;
    subject_id: number;
    subject: string;
    code: string;
    held_on: string | null;
    max_marks: number;
    pass_marks: number;
    marks_count: number;
    entry_sections: number[];
};

type ClassGroup = {
    id: number;
    name: string;
    sections: { id: number; name: string }[];
    papers: Paper[];
};

type Props = {
    exam: ExamSummary;
    classes: ClassGroup[];
    classOptions: Option[];
    subjects: Option[];
    can: {
        update: boolean;
        publish: boolean;
        enterMarks: boolean;
        viewResults: boolean;
    };
};

type PaperDraft = {
    id: number | null;
    school_class_id: number | '';
    subject_id: number | '';
    held_on: string;
    max_marks: number;
    pass_marks: number;
};

function PaperDialog({
    examId,
    draft,
    classOptions,
    subjects,
    onClose,
}: {
    examId: number;
    draft: PaperDraft;
    classOptions: Option[];
    subjects: Option[];
    onClose: () => void;
}) {
    const form = useForm(draft);

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (draft.id) {
            form.put(updatePaper.url([examId, draft.id]), options);
        } else {
            form.post(storePaper.url(examId), options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle>
                    {draft.id ? 'Edit paper' : 'Add paper'}
                </DialogTitle>
                <DialogDescription>
                    One subject sat by one class.
                </DialogDescription>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="paper-class"
                        label="Class"
                        error={form.errors.school_class_id}
                        required
                    >
                        <NativeSelect
                            id="paper-class"
                            value={form.data.school_class_id}
                            onChange={(e) =>
                                form.setData(
                                    'school_class_id',
                                    Number(e.target.value),
                                )
                            }
                            required
                        >
                            <option value="" disabled>
                                Select…
                            </option>
                            {classOptions.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField
                        id="paper-subject"
                        label="Subject"
                        error={form.errors.subject_id}
                        required
                    >
                        <NativeSelect
                            id="paper-subject"
                            value={form.data.subject_id}
                            onChange={(e) =>
                                form.setData(
                                    'subject_id',
                                    Number(e.target.value),
                                )
                            }
                            required
                        >
                            <option value="" disabled>
                                Select…
                            </option>
                            {subjects.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField
                        id="paper-max"
                        label="Maximum marks"
                        error={form.errors.max_marks}
                        required
                    >
                        <Input
                            id="paper-max"
                            type="number"
                            min={1}
                            step="0.5"
                            value={form.data.max_marks}
                            onChange={(e) =>
                                form.setData(
                                    'max_marks',
                                    Number(e.target.value),
                                )
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="paper-pass"
                        label="Pass marks"
                        error={form.errors.pass_marks}
                        required
                    >
                        <Input
                            id="paper-pass"
                            type="number"
                            min={0}
                            step="0.5"
                            value={form.data.pass_marks}
                            onChange={(e) =>
                                form.setData(
                                    'pass_marks',
                                    Number(e.target.value),
                                )
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="paper-date"
                        label="Date"
                        error={form.errors.held_on}
                    >
                        <Input
                            id="paper-date"
                            type="date"
                            value={form.data.held_on}
                            onChange={(e) =>
                                form.setData('held_on', e.target.value)
                            }
                        />
                    </FormField>
                    <div className="flex items-end justify-end">
                        <Button type="submit" disabled={form.processing}>
                            Save paper
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function ShowExam({
    exam,
    classes,
    classOptions,
    subjects,
    can,
}: Props) {
    const format = useFormat();
    const [draft, setDraft] = useState<PaperDraft | null>(null);
    const day = (value: string): string => format.date(`${value}T12:00:00Z`);
    const onError = (errors: Record<string, string>): void => {
        const message = Object.values(errors)[0];

        if (message) {
            toast.error(message);
        }
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={exam.name}
                description={`${exam.academic_year}${exam.term ? ` · ${exam.term}` : ''} · ${day(exam.starts_on)} – ${day(exam.ends_on)} · ${exam.grade_scale} scale`}
                actions={
                    <>
                        {exam.published_at ? (
                            <TonePill tone="success">Published</TonePill>
                        ) : (
                            <TonePill tone="warning">Draft</TonePill>
                        )}
                        {can.update && (
                            <Button variant="outline" asChild>
                                <Link href={edit(exam.id)}>
                                    <Pencil aria-hidden /> Edit
                                </Link>
                            </Button>
                        )}
                        {can.publish &&
                            (exam.published_at ? (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.visit(unpublish(exam.id), {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    <EyeOff aria-hidden /> Unpublish
                                </Button>
                            ) : (
                                <Button
                                    className="bg-gradient-to-r from-emerald-600 to-teal-600 text-white"
                                    onClick={() =>
                                        router.visit(publish(exam.id), {
                                            preserveScroll: true,
                                            onError,
                                        })
                                    }
                                >
                                    <Eye aria-hidden /> Publish results
                                </Button>
                            ))}
                    </>
                }
            />

            {can.update && (
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        onClick={() =>
                            router.visit(generate(exam.id), {
                                preserveScroll: true,
                                onError,
                            })
                        }
                    >
                        <Sparkles aria-hidden /> Add papers for every class
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() =>
                            setDraft({
                                id: null,
                                school_class_id: '',
                                subject_id: '',
                                held_on: '',
                                max_marks: 100,
                                pass_marks: 40,
                            })
                        }
                    >
                        <Plus aria-hidden /> Add one paper
                    </Button>
                </div>
            )}

            {classes.length === 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>No papers yet</CardTitle>
                        <CardDescription>
                            Add papers to decide which subjects each class sits.
                        </CardDescription>
                    </CardHeader>
                </Card>
            )}

            {classes.map((group) => (
                <Card key={group.id}>
                    <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <CardTitle>{group.name}</CardTitle>
                        {can.viewResults && group.sections.length > 0 && (
                            <div className="flex flex-wrap gap-1.5">
                                {group.sections.map((section) => (
                                    <Button
                                        key={section.id}
                                        variant="secondary"
                                        size="sm"
                                        asChild
                                    >
                                        <Link
                                            href={results([
                                                exam.id,
                                                section.id,
                                            ])}
                                        >
                                            <BarChart3 aria-hidden /> Results{' '}
                                            {section.name}
                                        </Link>
                                    </Button>
                                ))}
                            </div>
                        )}
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full min-w-[560px] text-sm">
                            <caption className="sr-only">
                                Papers for {group.name}
                            </caption>
                            <thead className="text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th
                                        scope="col"
                                        className="py-2 pr-3 font-medium"
                                    >
                                        Subject
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-2 pr-3 font-medium"
                                    >
                                        Date
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-2 pr-3 font-medium"
                                    >
                                        Max / pass
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-2 pr-3 font-medium"
                                    >
                                        Marks
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-2 font-medium"
                                    />
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {group.papers.map((paper) => (
                                    <tr key={paper.id}>
                                        <td className="py-2.5 pr-3 font-medium">
                                            {paper.subject}
                                        </td>
                                        <td className="py-2.5 pr-3 text-muted-foreground">
                                            {paper.held_on
                                                ? day(paper.held_on)
                                                : '—'}
                                        </td>
                                        <td className="py-2.5 pr-3 tabular-nums">
                                            {paper.max_marks} /{' '}
                                            {paper.pass_marks}
                                        </td>
                                        <td className="py-2.5 pr-3">
                                            <div className="flex flex-wrap gap-1">
                                                {can.enterMarks &&
                                                    group.sections
                                                        .filter((section) =>
                                                            paper.entry_sections.includes(
                                                                section.id,
                                                            ),
                                                        )
                                                        .map((section) => (
                                                            <Button
                                                                key={section.id}
                                                                variant="outline"
                                                                size="sm"
                                                                className="h-7"
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={marks(
                                                                        [
                                                                            exam.id,
                                                                            paper.id,
                                                                            section.id,
                                                                        ],
                                                                    )}
                                                                >
                                                                    <PenLine
                                                                        aria-hidden
                                                                    />{' '}
                                                                    {
                                                                        section.name
                                                                    }
                                                                </Link>
                                                            </Button>
                                                        ))}
                                                <span className="self-center text-xs text-muted-foreground">
                                                    {paper.marks_count} entered
                                                </span>
                                            </div>
                                        </td>
                                        <td className="py-2.5 text-right whitespace-nowrap">
                                            {can.update && (
                                                <>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Edit ${paper.subject}`}
                                                        onClick={() =>
                                                            setDraft({
                                                                id: paper.id,
                                                                school_class_id:
                                                                    group.id,
                                                                subject_id:
                                                                    paper.subject_id,
                                                                held_on:
                                                                    paper.held_on ??
                                                                    '',
                                                                max_marks:
                                                                    paper.max_marks,
                                                                pass_marks:
                                                                    paper.pass_marks,
                                                            })
                                                        }
                                                    >
                                                        <Pencil aria-hidden />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Remove ${paper.subject}`}
                                                        disabled={
                                                            paper.marks_count >
                                                            0
                                                        }
                                                        onClick={() =>
                                                            router.visit(
                                                                destroyPaper([
                                                                    exam.id,
                                                                    paper.id,
                                                                ]),
                                                                {
                                                                    preserveScroll: true,
                                                                    onError,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <Trash2 aria-hidden />
                                                    </Button>
                                                </>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            ))}

            {draft && (
                <PaperDialog
                    key={draft.id ?? 'new'}
                    examId={exam.id}
                    draft={draft}
                    classOptions={classOptions}
                    subjects={subjects}
                    onClose={() => setDraft(null)}
                />
            )}
        </div>
    );
}

ShowExam.layout = {
    breadcrumbs: [{ title: 'Exams & results', href: index() }],
};
