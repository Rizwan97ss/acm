import { Link, router, useForm } from '@inertiajs/react';
import { CalendarRange, CheckCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { statusStyles } from '@/lib/attendance';
import { cn } from '@/lib/utils';
import { index, register, store } from '@/routes/academics/attendance';
import type { EnumOption, SectionOption } from '@/types';

type Row = {
    student_id: number;
    name: string;
    admission_no: string;
    roll_number: number | null;
    status: string | null;
    remarks: string | null;
};

type Props = {
    year: { id: number; name: string } | null;
    sections: SectionOption[];
    selected: { section_id: number | null; date: string };
    rows: Row[];
    statuses: EnumOption[];
    canRecord: boolean;
    isRecorded: boolean;
};

export default function AttendanceIndex({
    year,
    sections,
    selected,
    rows,
    statuses,
    canRecord,
    isRecorded,
}: Props) {
    const form = useForm({
        entries: rows.map((row) => ({
            student_id: row.student_id,
            status: row.status ?? 'present',
            remarks: row.remarks ?? '',
        })),
    });

    const visit = (changes: Partial<Props['selected']>): void =>
        router.get(
            index.url(),
            { ...selected, ...changes },
            { preserveScroll: true, preserveState: false, replace: true },
        );

    const setStatus = (studentId: number, status: string): void =>
        form.setData(
            'entries',
            form.data.entries.map((entry) =>
                entry.student_id === studentId ? { ...entry, status } : entry,
            ),
        );

    const counts = statuses.map((status) => ({
        ...status,
        count: form.data.entries.filter(
            (entry) => entry.status === status.value,
        ).length,
    }));

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            section_id: selected.section_id,
            date: selected.date,
        }));
        form.post(store.url(), { preserveScroll: true });
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Attendance"
                description={
                    year
                        ? `Daily roll call · ${year.name}`
                        : 'Set a current academic year to take attendance.'
                }
                actions={
                    <Button variant="outline" asChild>
                        <Link
                            href={register({
                                query: {
                                    section_id:
                                        selected.section_id ?? undefined,
                                },
                            })}
                        >
                            <CalendarRange aria-hidden /> Monthly register
                        </Link>
                    </Button>
                }
            />

            <div className="flex flex-col gap-2 sm:flex-row">
                <NativeSelect
                    aria-label="Section"
                    value={selected.section_id ?? ''}
                    onChange={(e) =>
                        visit({ section_id: Number(e.target.value) })
                    }
                    className="sm:w-64"
                >
                    {sections.length === 0 && (
                        <option value="">No sections assigned to you</option>
                    )}
                    {sections.map((section) => (
                        <option key={section.id} value={section.id}>
                            {section.name}
                        </option>
                    ))}
                </NativeSelect>
                <Input
                    type="date"
                    aria-label="Date"
                    value={selected.date}
                    onChange={(e) =>
                        e.target.value && visit({ date: e.target.value })
                    }
                    className="sm:w-44"
                />
            </div>

            {rows.length === 0 ? (
                <Card>
                    <CardHeader>
                        <CardTitle>No students to mark</CardTitle>
                        <CardDescription>
                            Enroll students in this section first.
                        </CardDescription>
                    </CardHeader>
                </Card>
            ) : (
                <form onSubmit={submit} className="space-y-4">
                    <div className="flex flex-wrap items-center gap-2">
                        {counts.map((status) => (
                            <span
                                key={status.value}
                                className={cn(
                                    'rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset',
                                    statusStyles[status.value].active,
                                )}
                            >
                                {status.label}: {status.count}
                            </span>
                        ))}
                        {!isRecorded && (
                            <span className="text-xs text-muted-foreground">
                                Not recorded yet — everyone starts as present.
                            </span>
                        )}
                        {canRecord && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="ml-auto"
                                onClick={() =>
                                    form.setData(
                                        'entries',
                                        form.data.entries.map((entry) => ({
                                            ...entry,
                                            status: 'present',
                                        })),
                                    )
                                }
                            >
                                <CheckCheck aria-hidden /> Mark all present
                            </Button>
                        )}
                    </div>

                    <ul className="divide-y overflow-hidden rounded-2xl border bg-card shadow-sm">
                        {rows.map((row, i) => {
                            const entry = form.data.entries[i];

                            return (
                                <li
                                    key={row.student_id}
                                    className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center"
                                >
                                    <div className="flex min-w-0 flex-1 items-center gap-3">
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-navy-500 to-royal-500 text-xs font-semibold text-white">
                                            {row.roll_number ?? '–'}
                                        </span>
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {row.name}
                                            </p>
                                            <p className="font-mono text-xs text-muted-foreground">
                                                {row.admission_no}
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        className="flex gap-1.5"
                                        role="radiogroup"
                                        aria-label={`Attendance for ${row.name}`}
                                    >
                                        {statuses.map((status) => {
                                            const active =
                                                entry.status === status.value;

                                            return (
                                                <button
                                                    key={status.value}
                                                    type="button"
                                                    role="radio"
                                                    aria-checked={active}
                                                    disabled={!canRecord}
                                                    onClick={() =>
                                                        setStatus(
                                                            row.student_id,
                                                            status.value,
                                                        )
                                                    }
                                                    className={cn(
                                                        'h-9 min-w-9 rounded-lg px-2.5 text-sm font-semibold ring-1 transition ring-inset disabled:cursor-not-allowed disabled:opacity-60',
                                                        active
                                                            ? statusStyles[
                                                                  status.value
                                                              ].active
                                                            : statusStyles[
                                                                  status.value
                                                              ].idle,
                                                    )}
                                                    title={status.label}
                                                >
                                                    <span aria-hidden>
                                                        {
                                                            statusStyles[
                                                                status.value
                                                            ].short
                                                        }
                                                    </span>
                                                    <span className="sr-only">
                                                        {status.label}
                                                    </span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                    <Input
                                        aria-label={`Remarks for ${row.name}`}
                                        placeholder="Remarks"
                                        value={entry.remarks}
                                        disabled={!canRecord}
                                        onChange={(e) =>
                                            form.setData(
                                                'entries',
                                                form.data.entries.map(
                                                    (item, index) =>
                                                        index === i
                                                            ? {
                                                                  ...item,
                                                                  remarks:
                                                                      e.target
                                                                          .value,
                                                              }
                                                            : item,
                                                ),
                                            )
                                        }
                                        className="sm:w-48"
                                    />
                                </li>
                            );
                        })}
                    </ul>

                    {(form.errors as Record<string, string | undefined>)
                        .date && (
                        <p className="text-sm text-destructive">
                            {(form.errors as Record<string, string>).date}
                        </p>
                    )}
                    {canRecord ? (
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="bg-gradient-to-r from-navy-600 to-royal-600 text-white"
                        >
                            Save attendance
                        </Button>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            This date can no longer be edited by you.
                        </p>
                    )}
                </form>
            )}
        </div>
    );
}

AttendanceIndex.layout = {
    breadcrumbs: [{ title: 'Attendance', href: index() }],
};
