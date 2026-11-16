import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/academics/exams';
import { update } from '@/routes/academics/exams/marks';

type Row = {
    student_id: number;
    name: string;
    admission_no: string;
    roll_number: number | null;
    marks_obtained: number | null;
    is_absent: boolean;
    remarks: string | null;
};

type Props = {
    exam: { id: number; name: string; published: boolean };
    paper: {
        id: number;
        subject: string;
        class: string;
        max_marks: number;
        pass_marks: number;
    };
    section: { id: number; name: string };
    rows: Row[];
    canEdit: boolean;
};

export default function MarksEntry({
    exam,
    paper,
    section,
    rows,
    canEdit,
}: Props) {
    const form = useForm({
        marks: rows.map((row) => ({
            student_id: row.student_id,
            marks_obtained:
                row.marks_obtained === null ? '' : String(row.marks_obtained),
            is_absent: row.is_absent,
            remarks: row.remarks ?? '',
        })),
    });
    const errors = form.errors as Record<string, string | undefined>;

    const setRow = (
        i: number,
        changes: Partial<(typeof form.data.marks)[number]>,
    ): void =>
        form.setData(
            'marks',
            form.data.marks.map((row, index) =>
                index === i ? { ...row, ...changes } : row,
            ),
        );

    const entered = form.data.marks.filter(
        (row) => row.is_absent || row.marks_obtained !== '',
    ).length;

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.put(update.url([exam.id, paper.id, section.id]), {
            preserveScroll: true,
        });
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={`${paper.subject} · ${section.name}`}
                description={`${exam.name} · out of ${paper.max_marks}, pass mark ${paper.pass_marks} · ${entered}/${rows.length} entered`}
                actions={
                    <Button variant="outline" asChild>
                        <Link href={show(exam.id)}>Back to exam</Link>
                    </Button>
                }
            />
            {exam.published && (
                <p className="rounded-xl bg-amber-100 px-4 py-3 text-sm text-amber-900 dark:bg-amber-500/15 dark:text-amber-200">
                    Results are published. Unpublish the exam to change marks.
                </p>
            )}
            <form onSubmit={submit} className="space-y-4">
                <div className="overflow-x-auto rounded-2xl border bg-card shadow-sm">
                    <table className="w-full min-w-[620px] text-sm">
                        <caption className="sr-only">Marks entry</caption>
                        <thead className="bg-gradient-to-r from-navy-50 to-crimson-50 text-left text-xs text-navy-950/70 uppercase dark:from-navy-500/10 dark:to-crimson-500/10 dark:text-navy-100/80">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Student
                                </th>
                                <th
                                    scope="col"
                                    className="w-32 px-4 py-3 font-medium"
                                >
                                    Marks
                                </th>
                                <th
                                    scope="col"
                                    className="w-24 px-4 py-3 font-medium"
                                >
                                    Absent
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Remarks
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {rows.map((row, i) => {
                                const entry = form.data.marks[i];
                                const value =
                                    entry.marks_obtained === ''
                                        ? null
                                        : Number(entry.marks_obtained);
                                const failing =
                                    value !== null && value < paper.pass_marks;
                                const error =
                                    errors[
                                        `marks.${row.student_id}.marks_obtained`
                                    ] ?? errors[`marks.${i}.marks_obtained`];

                                return (
                                    <tr key={row.student_id}>
                                        <td className="px-4 py-2">
                                            <span className="font-medium">
                                                {row.name}
                                            </span>
                                            <span className="ml-2 text-xs text-muted-foreground tabular-nums">
                                                #{row.roll_number ?? '–'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2">
                                            <Input
                                                type="number"
                                                min={0}
                                                max={paper.max_marks}
                                                step="0.25"
                                                aria-label={`Marks for ${row.name}`}
                                                aria-invalid={
                                                    error ? true : undefined
                                                }
                                                disabled={
                                                    !canEdit || entry.is_absent
                                                }
                                                value={entry.marks_obtained}
                                                onChange={(e) =>
                                                    setRow(i, {
                                                        marks_obtained:
                                                            e.target.value,
                                                    })
                                                }
                                                className={cn(
                                                    'tabular-nums',
                                                    failing &&
                                                        'border-rose-400 text-rose-700 dark:text-rose-300',
                                                )}
                                            />
                                            {error && (
                                                <p className="mt-1 text-xs text-destructive">
                                                    {error}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-4 py-2">
                                            <Checkbox
                                                aria-label={`${row.name} was absent`}
                                                checked={entry.is_absent}
                                                disabled={!canEdit}
                                                onCheckedChange={(checked) =>
                                                    setRow(i, {
                                                        is_absent:
                                                            checked === true,
                                                        marks_obtained:
                                                            checked === true
                                                                ? ''
                                                                : entry.marks_obtained,
                                                    })
                                                }
                                            />
                                        </td>
                                        <td className="px-4 py-2">
                                            <Input
                                                aria-label={`Remarks for ${row.name}`}
                                                disabled={!canEdit}
                                                value={entry.remarks}
                                                onChange={(e) =>
                                                    setRow(i, {
                                                        remarks: e.target.value,
                                                    })
                                                }
                                            />
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
                {errors.exam && (
                    <p className="text-sm text-destructive">{errors.exam}</p>
                )}
                {canEdit && rows.length > 0 && (
                    <Button
                        type="submit"
                        disabled={form.processing}
                        className="bg-gradient-to-r from-navy-600 to-royal-600 text-white"
                    >
                        Save marks
                    </Button>
                )}
            </form>
        </div>
    );
}

MarksEntry.layout = {
    breadcrumbs: [{ title: 'Exams & results', href: index() }],
};
