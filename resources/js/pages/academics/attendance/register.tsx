import { router } from '@inertiajs/react';
import PageHeader from '@/components/page-header';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { statusStyles } from '@/lib/attendance';
import { cn } from '@/lib/utils';
import { index, register } from '@/routes/academics/attendance';
import type { SectionOption } from '@/types';

type Props = {
    sections: SectionOption[];
    selected: { section_id: number | null; month: string };
    days: string[];
    students: {
        student_id: number;
        name: string;
        roll_number: number | null;
        days: Record<string, string>;
        percentage: number | null;
    }[];
};

export default function AttendanceRegister({
    sections,
    selected,
    days,
    students,
}: Props) {
    const visit = (changes: Partial<Props['selected']>): void =>
        router.get(
            register.url(),
            { ...selected, ...changes },
            { preserveScroll: true, replace: true },
        );

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Attendance register"
                description="Monthly view. P present · A absent · L late · E excused."
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
                    {sections.map((section) => (
                        <option key={section.id} value={section.id}>
                            {section.name}
                        </option>
                    ))}
                </NativeSelect>
                <Input
                    type="month"
                    aria-label="Month"
                    value={selected.month}
                    onChange={(e) =>
                        e.target.value && visit({ month: e.target.value })
                    }
                    className="sm:w-44"
                />
            </div>
            <div className="overflow-x-auto rounded-2xl border bg-card shadow-sm">
                <table className="w-full text-xs">
                    <caption className="sr-only">
                        Attendance register for {selected.month}
                    </caption>
                    <thead className="bg-gradient-to-r from-indigo-50 to-fuchsia-50 dark:from-indigo-500/10 dark:to-fuchsia-500/10">
                        <tr>
                            <th
                                scope="col"
                                className="sticky left-0 z-10 bg-indigo-50 px-3 py-2 text-left font-medium dark:bg-[oklch(0.21_0.03_278)]"
                            >
                                Student
                            </th>
                            {days.map((day) => (
                                <th
                                    key={day}
                                    scope="col"
                                    className="w-7 px-0.5 py-2 text-center font-medium tabular-nums"
                                >
                                    {Number(day.slice(8))}
                                </th>
                            ))}
                            <th
                                scope="col"
                                className="px-3 py-2 text-right font-medium"
                            >
                                %
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {students.map((student) => (
                            <tr key={student.student_id}>
                                <th
                                    scope="row"
                                    className="sticky left-0 z-10 bg-card px-3 py-1.5 text-left font-normal whitespace-nowrap"
                                >
                                    <span className="text-muted-foreground tabular-nums">
                                        {student.roll_number ?? '–'}.
                                    </span>{' '}
                                    {student.name}
                                </th>
                                {days.map((day) => {
                                    const status = student.days[day];

                                    return (
                                        <td
                                            key={day}
                                            className="px-0.5 py-1 text-center"
                                        >
                                            {status ? (
                                                <span
                                                    className={cn(
                                                        'inline-flex size-6 items-center justify-center rounded-md text-[10px] font-bold',
                                                        statusStyles[status]
                                                            .active,
                                                    )}
                                                    title={status}
                                                >
                                                    {statusStyles[status].short}
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground/40">
                                                    ·
                                                </span>
                                            )}
                                        </td>
                                    );
                                })}
                                <td
                                    className={cn(
                                        'px-3 py-1.5 text-right font-semibold tabular-nums',
                                        student.percentage !== null &&
                                            student.percentage < 75
                                            ? 'text-rose-600'
                                            : '',
                                    )}
                                >
                                    {student.percentage ?? '—'}
                                </td>
                            </tr>
                        ))}
                        {students.length === 0 && (
                            <tr>
                                <td
                                    colSpan={days.length + 2}
                                    className="px-3 py-8 text-center text-muted-foreground"
                                >
                                    No students enrolled.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

AttendanceRegister.layout = {
    breadcrumbs: [
        { title: 'Attendance', href: index() },
        { title: 'Register', href: register() },
    ],
};
