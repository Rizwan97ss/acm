import { Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { LessonSlot } from '@/components/academics/lesson-dialog';
import LessonDialog from '@/components/academics/lesson-dialog';
import PageHeader from '@/components/page-header';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import { edit as editPeriods } from '@/routes/academics/periods';
import { index } from '@/routes/academics/timetable';
import type {
    Option,
    PeriodData,
    SubjectAllocation,
    TimetableLesson,
} from '@/types';

type Selected = {
    view: 'section' | 'teacher';
    academic_year_id: number | null;
    section_id: number | null;
    teacher_id: number | null;
};

type Props = {
    years: { id: number; name: string; is_current: boolean }[];
    sections: { id: number; name: string; room: string | null }[];
    teachers: Option[];
    periods: (PeriodData & { id: number })[];
    days: { value: number; label: string }[];
    selected: Selected;
    entries: TimetableLesson[];
    subjects: SubjectAllocation[];
    canEdit: boolean;
};

export default function TimetableIndex({
    years,
    sections,
    teachers,
    periods,
    days,
    selected,
    entries,
    subjects,
    canEdit,
}: Props) {
    const [slot, setSlot] = useState<LessonSlot | null>(null);
    const lessons = new Map(
        entries.map((entry) => [
            `${entry.day_of_week}-${entry.period_id}`,
            entry,
        ]),
    );
    const section = sections.find((item) => item.id === selected.section_id);

    const visit = (changes: Partial<Selected>): void => {
        const next = { ...selected, ...changes };

        router.get(
            index.url(),
            Object.fromEntries(
                Object.entries(next).filter(([, value]) => value !== null),
            ),
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const ready =
        selected.academic_year_id !== null &&
        periods.length > 0 &&
        (selected.view === 'teacher' || section !== undefined);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Timetable"
                description={
                    selected.view === 'section'
                        ? 'Weekly lessons per section. Clashes for teachers and rooms are blocked automatically.'
                        : 'Weekly teaching schedule.'
                }
            />

            <div className="flex flex-col gap-2 md:flex-row md:items-center">
                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={selected.view}
                    onValueChange={(value) =>
                        value && visit({ view: value as Selected['view'] })
                    }
                    aria-label="Timetable view"
                >
                    <ToggleGroupItem value="section" className="px-3">
                        By section
                    </ToggleGroupItem>
                    <ToggleGroupItem value="teacher" className="px-3">
                        By teacher
                    </ToggleGroupItem>
                </ToggleGroup>
                <NativeSelect
                    aria-label="Academic year"
                    value={selected.academic_year_id ?? ''}
                    onChange={(e) =>
                        visit({ academic_year_id: Number(e.target.value) })
                    }
                    className="md:w-44"
                >
                    {years.map((year) => (
                        <option key={year.id} value={year.id}>
                            {year.name}
                            {year.is_current ? ' (current)' : ''}
                        </option>
                    ))}
                </NativeSelect>
                {selected.view === 'section' ? (
                    <NativeSelect
                        aria-label="Section"
                        value={selected.section_id ?? ''}
                        onChange={(e) =>
                            visit({ section_id: Number(e.target.value) })
                        }
                        className="md:w-56"
                    >
                        {sections.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </NativeSelect>
                ) : (
                    <NativeSelect
                        aria-label="Teacher"
                        value={selected.teacher_id ?? ''}
                        onChange={(e) =>
                            visit({ teacher_id: Number(e.target.value) })
                        }
                        className="md:w-56"
                    >
                        {teachers.map((teacher) => (
                            <option key={teacher.id} value={teacher.id}>
                                {teacher.name}
                            </option>
                        ))}
                    </NativeSelect>
                )}
            </div>

            {!ready && (
                <Card>
                    <CardHeader>
                        <CardTitle>Nothing to show yet</CardTitle>
                        <CardDescription>
                            A timetable needs an academic year, at least one
                            section and a{' '}
                            <Link
                                href={editPeriods()}
                                className="font-medium text-primary underline-offset-4 hover:underline"
                            >
                                bell schedule
                            </Link>
                            .
                        </CardDescription>
                    </CardHeader>
                </Card>
            )}

            {ready && (
                <div className="grid gap-4 xl:grid-cols-[1fr_16rem]">
                    <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                        <table className="w-full min-w-[720px] table-fixed text-sm">
                            <caption className="sr-only">
                                {selected.view === 'section'
                                    ? `Timetable for ${section?.name}`
                                    : 'Teacher timetable'}
                            </caption>
                            <thead className="border-b bg-muted/40 text-xs text-muted-foreground">
                                <tr>
                                    <th
                                        scope="col"
                                        className="w-28 px-3 py-2 text-left font-medium"
                                    >
                                        Period
                                    </th>
                                    {days.map((day) => (
                                        <th
                                            key={day.value}
                                            scope="col"
                                            className="px-2 py-2 text-left font-medium"
                                        >
                                            {day.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {periods.map((period) => (
                                    <tr
                                        key={period.id}
                                        className={cn(
                                            period.is_break && 'bg-muted/30',
                                        )}
                                    >
                                        <th
                                            scope="row"
                                            className="px-3 py-2 text-left align-top font-normal"
                                        >
                                            <span className="block font-medium">
                                                {period.name}
                                            </span>
                                            <span className="text-xs text-muted-foreground tabular-nums">
                                                {period.starts_at}–
                                                {period.ends_at}
                                            </span>
                                        </th>
                                        {period.is_break ? (
                                            <td
                                                colSpan={days.length}
                                                className="px-2 py-2 text-center text-xs tracking-widest text-muted-foreground uppercase"
                                            >
                                                {period.name}
                                            </td>
                                        ) : (
                                            days.map((day) => {
                                                const lesson =
                                                    lessons.get(
                                                        `${day.value}-${period.id}`,
                                                    ) ?? null;
                                                const open = (): void =>
                                                    setSlot({
                                                        dayOfWeek: day.value,
                                                        dayLabel: day.label,
                                                        periodId: period.id,
                                                        periodLabel: `${period.name} (${period.starts_at}–${period.ends_at})`,
                                                        lesson,
                                                    });

                                                return (
                                                    <td
                                                        key={day.value}
                                                        className="p-1 align-top"
                                                    >
                                                        {lesson ? (
                                                            <button
                                                                type="button"
                                                                disabled={
                                                                    !canEdit
                                                                }
                                                                onClick={open}
                                                                className="block h-full min-h-14 w-full rounded-md border-l-4 bg-muted/40 px-2 py-1.5 text-left transition-colors enabled:hover:bg-muted disabled:cursor-default"
                                                                style={{
                                                                    borderLeftColor:
                                                                        lesson
                                                                            .subject
                                                                            .color ??
                                                                        'var(--primary)',
                                                                }}
                                                                aria-label={`${day.label} ${period.name}: ${lesson.subject.name}`}
                                                            >
                                                                <span className="block truncate font-medium">
                                                                    {
                                                                        lesson
                                                                            .subject
                                                                            .name
                                                                    }
                                                                </span>
                                                                <span className="block truncate text-xs text-muted-foreground">
                                                                    {selected.view ===
                                                                    'section'
                                                                        ? (lesson
                                                                              .teacher
                                                                              ?.name ??
                                                                          'No teacher')
                                                                        : lesson
                                                                              .section
                                                                              .name}
                                                                </span>
                                                                {(lesson.room ??
                                                                    (selected.view ===
                                                                    'section'
                                                                        ? section?.room
                                                                        : null)) && (
                                                                    <span className="block truncate text-xs text-muted-foreground">
                                                                        {lesson.room ??
                                                                            section?.room}
                                                                    </span>
                                                                )}
                                                            </button>
                                                        ) : canEdit ? (
                                                            <button
                                                                type="button"
                                                                onClick={open}
                                                                className="flex min-h-14 w-full items-center justify-center rounded-md border border-dashed text-muted-foreground/50 transition-colors hover:border-primary hover:text-primary"
                                                                aria-label={`Add lesson on ${day.label}, ${period.name}`}
                                                            >
                                                                <Plus
                                                                    className="size-4"
                                                                    aria-hidden
                                                                />
                                                            </button>
                                                        ) : (
                                                            <div className="min-h-14" />
                                                        )}
                                                    </td>
                                                );
                                            })
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {selected.view === 'section' && (
                        <Card className="h-fit">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Weekly allocation
                                </CardTitle>
                                <CardDescription>
                                    Scheduled vs planned periods
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {subjects.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Assign subjects to this class first.
                                    </p>
                                ) : (
                                    <ul className="space-y-2.5">
                                        {subjects.map((subject) => {
                                            const complete =
                                                subject.scheduled ===
                                                subject.planned;
                                            const over =
                                                subject.scheduled >
                                                subject.planned;

                                            return (
                                                <li
                                                    key={subject.id}
                                                    className="space-y-1"
                                                >
                                                    <div className="flex items-center justify-between gap-2 text-sm">
                                                        <span className="flex min-w-0 items-center gap-2">
                                                            <span
                                                                className="size-2.5 shrink-0 rounded-full"
                                                                style={{
                                                                    backgroundColor:
                                                                        subject.color ??
                                                                        'var(--primary)',
                                                                }}
                                                                aria-hidden
                                                            />
                                                            <span className="truncate">
                                                                {subject.name}
                                                            </span>
                                                        </span>
                                                        <span
                                                            className={cn(
                                                                'shrink-0 tabular-nums',
                                                                over
                                                                    ? 'text-destructive'
                                                                    : complete
                                                                      ? 'text-foreground'
                                                                      : 'text-muted-foreground',
                                                            )}
                                                        >
                                                            {subject.scheduled}/
                                                            {subject.planned}
                                                            {over && (
                                                                <span className="sr-only">
                                                                    {' '}
                                                                    (over plan)
                                                                </span>
                                                            )}
                                                        </span>
                                                    </div>
                                                    <div className="h-1.5 rounded-full bg-muted">
                                                        <div
                                                            className={cn(
                                                                'h-1.5 rounded-full',
                                                                over
                                                                    ? 'bg-destructive'
                                                                    : 'bg-primary',
                                                            )}
                                                            style={{
                                                                width: `${Math.min(100, subject.planned > 0 ? (subject.scheduled / subject.planned) * 100 : 0)}%`,
                                                            }}
                                                        />
                                                    </div>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    )}
                </div>
            )}

            {slot &&
                selected.academic_year_id !== null &&
                selected.section_id !== null && (
                    <LessonDialog
                        key={`${slot.dayOfWeek}-${slot.periodId}`}
                        slot={slot}
                        academicYearId={selected.academic_year_id}
                        sectionId={selected.section_id}
                        subjects={subjects}
                        teachers={teachers}
                        onClose={() => setSlot(null)}
                    />
                )}
        </div>
    );
}

TimetableIndex.layout = {
    breadcrumbs: [{ title: 'Timetable', href: index() }],
};
