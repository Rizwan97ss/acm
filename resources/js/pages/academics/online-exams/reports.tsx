import { Link } from '@inertiajs/react';
import { Award, Download, FileQuestion, Percent, Users } from 'lucide-react';
import BarChart from '@/components/charts/bar-chart';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { useFormat } from '@/hooks/use-format';
import { useTableFilters } from '@/hooks/use-table-filters';
import {
    exportMethod,
    index as reportsIndex,
} from '@/routes/academics/online-exam-reports';
import { index, report } from '@/routes/academics/online-exams';
import { mark } from '@/types/exams';
import type { TeachingOption } from '@/types/learning';

type Aggregate = {
    attempts: number;
    average: number | null;
    pass_rate: number | null;
    highest: number | null;
};

type Props = {
    overview: {
        summary: {
            exams: number;
            attempts: number;
            average: number | null;
            pass_rate: number | null;
        };
        exams: (Aggregate & {
            id: number;
            title: string;
            class: string;
            subject: string;
            date: string;
        })[];
        classes: (Aggregate & { name: string })[];
        subjects: (Aggregate & { name: string })[];
        students: {
            student_id: number;
            name: string;
            admission_no: string;
            exams: number;
            average: number | null;
            passed: number;
            best: number | null;
            lowest: number | null;
            rank: number;
        }[];
    };
    filters: {
        class_id: number | null;
        subject_id: number | null;
        section_id: number | null;
    };
    options: TeachingOption[];
    can: { export: boolean };
};

function CsvButton({
    type,
    query,
}: {
    type: string;
    query: Record<string, string>;
}) {
    return (
        <Button asChild size="sm" variant="outline">
            <a href={exportMethod.url({ query: { ...query, type } })}>
                <Download aria-hidden /> CSV
            </a>
        </Button>
    );
}

export default function OnlineExamReports({
    overview,
    filters,
    options,
    can,
}: Props) {
    const { date } = useFormat();
    const { values, update } = useTableFilters(reportsIndex.url(), {
        class_id: filters.class_id ?? '',
        section_id: filters.section_id ?? '',
        subject_id: filters.subject_id ?? '',
    });
    const selectedClass = options.find(
        (option) => option.id === Number(values.class_id),
    );
    const subjects = selectedClass
        ? selectedClass.subjects
        : options
              .flatMap((option) => option.subjects)
              .filter(
                  (subject, position, all) =>
                      all.findIndex((other) => other.id === subject.id) ===
                      position,
              )
              .sort((a, b) => a.name.localeCompare(b.name));
    const query = Object.fromEntries(
        Object.entries(values)
            .filter(([, value]) => value !== '' && value !== null)
            .map(([key, value]) => [key, String(value)]),
    );
    const { summary } = overview;

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Exam performance"
                description="Online exam results with approved or published results, by exam, class, subject and student."
                actions={
                    <Button asChild variant="outline">
                        <Link href={index()}>
                            <FileQuestion aria-hidden /> Online exams
                        </Link>
                    </Button>
                }
            />

            <div className="flex flex-col gap-2 sm:flex-row">
                <NativeSelect
                    aria-label="Class"
                    value={values.class_id}
                    onChange={(event) =>
                        update({
                            class_id: event.target.value,
                            section_id: '',
                            subject_id: '',
                        })
                    }
                    className="sm:w-48"
                >
                    <option value="">All classes</option>
                    {options.map((option) => (
                        <option key={option.id} value={option.id}>
                            {option.name}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect
                    aria-label="Section"
                    value={values.section_id}
                    disabled={!selectedClass}
                    onChange={(event) =>
                        update({ section_id: event.target.value })
                    }
                    className="sm:w-40"
                >
                    <option value="">All sections</option>
                    {selectedClass?.sections.map((section) => (
                        <option key={section.id} value={section.id}>
                            {section.name}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect
                    aria-label="Subject"
                    value={values.subject_id}
                    onChange={(event) =>
                        update({ subject_id: event.target.value })
                    }
                    className="sm:w-48"
                >
                    <option value="">All subjects</option>
                    {subjects.map((subject) => (
                        <option key={subject.id} value={subject.id}>
                            {subject.name}
                        </option>
                    ))}
                </NativeSelect>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Exams"
                    value={summary.exams}
                    icon={FileQuestion}
                    tone="sky"
                />
                <StatCard
                    label="Results"
                    value={summary.attempts}
                    hint="Marked student results"
                    icon={Users}
                    tone="violet"
                />
                <StatCard
                    label="Average"
                    value={
                        summary.average !== null ? `${summary.average}%` : '—'
                    }
                    icon={Percent}
                    tone="orange"
                />
                <StatCard
                    label="Pass rate"
                    value={
                        summary.pass_rate !== null
                            ? `${summary.pass_rate}%`
                            : '—'
                    }
                    icon={Award}
                    tone="emerald"
                />
            </div>

            {summary.exams === 0 ? (
                <p className="rounded-2xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                    No exams with approved results for this selection yet.
                </p>
            ) : (
                <>
                    <div className="grid gap-4 lg:grid-cols-2">
                        <section className="rounded-2xl border bg-card p-5 shadow-sm">
                            <div className="mb-4 flex items-start justify-between gap-2">
                                <div>
                                    <h2 className="font-semibold">
                                        Class averages
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Average percentage per class.
                                    </p>
                                </div>
                                {can.export && (
                                    <CsvButton type="classes" query={query} />
                                )}
                            </div>
                            <BarChart
                                title="Average percentage per class"
                                valueLabel="Average %"
                                data={overview.classes.map((row) => ({
                                    label: row.name,
                                    value: row.average ?? 0,
                                    detail: `Pass rate ${row.pass_rate ?? '—'}% · ${row.attempts} results`,
                                }))}
                            />
                        </section>
                        <section className="rounded-2xl border bg-card p-5 shadow-sm">
                            <div className="mb-4 flex items-start justify-between gap-2">
                                <div>
                                    <h2 className="font-semibold">
                                        Subject averages
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Average percentage per subject.
                                    </p>
                                </div>
                                {can.export && (
                                    <CsvButton type="subjects" query={query} />
                                )}
                            </div>
                            <BarChart
                                title="Average percentage per subject"
                                valueLabel="Average %"
                                data={overview.subjects.map((row) => ({
                                    label: row.name,
                                    value: row.average ?? 0,
                                    detail: `Pass rate ${row.pass_rate ?? '—'}% · ${row.attempts} results`,
                                }))}
                            />
                        </section>
                    </div>

                    <section className="space-y-3">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="text-lg font-semibold">By exam</h2>
                            {can.export && (
                                <CsvButton type="exams" query={query} />
                            )}
                        </div>
                        <div className="overflow-x-auto rounded-2xl border bg-card shadow-sm">
                            <table className="w-full text-sm">
                                <caption className="sr-only">
                                    Results by exam
                                </caption>
                                <thead className="bg-muted text-left text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th scope="col" className="px-4 py-2">
                                            Exam
                                        </th>
                                        <th scope="col" className="px-4 py-2">
                                            Date
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-2 text-right"
                                        >
                                            Students
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-2 text-right"
                                        >
                                            Average
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-2 text-right"
                                        >
                                            Pass rate
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-2 text-right"
                                        >
                                            Highest
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {overview.exams.map((exam) => (
                                        <tr key={exam.id}>
                                            <td className="px-4 py-2">
                                                <Link
                                                    href={report(exam.id)}
                                                    className="font-medium hover:text-primary hover:underline"
                                                >
                                                    {exam.title}
                                                </Link>
                                                <span className="block text-xs text-muted-foreground">
                                                    {exam.subject} ·{' '}
                                                    {exam.class}
                                                </span>
                                            </td>
                                            <td className="px-4 py-2 whitespace-nowrap text-muted-foreground">
                                                {date(exam.date)}
                                            </td>
                                            <td className="px-4 py-2 text-right tabular-nums">
                                                {exam.attempts}
                                            </td>
                                            <td className="px-4 py-2 text-right tabular-nums">
                                                {exam.average !== null
                                                    ? `${exam.average}%`
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-2 text-right tabular-nums">
                                                {exam.pass_rate !== null
                                                    ? `${exam.pass_rate}%`
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-2 text-right tabular-nums">
                                                {exam.highest !== null
                                                    ? `${exam.highest}%`
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section className="space-y-3">
                        <div className="flex items-center justify-between gap-2">
                            <div>
                                <h2 className="text-lg font-semibold">
                                    By student
                                </h2>
                                {!selectedClass && (
                                    <p className="text-sm text-muted-foreground">
                                        Choose a class to rank its students.
                                    </p>
                                )}
                            </div>
                            {can.export && overview.students.length > 0 && (
                                <CsvButton type="students" query={query} />
                            )}
                        </div>
                        {overview.students.length > 0 && (
                            <div className="overflow-x-auto rounded-2xl border bg-card shadow-sm">
                                <table className="w-full text-sm">
                                    <caption className="sr-only">
                                        Results by student
                                    </caption>
                                    <thead className="bg-muted text-left text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-4 py-2"
                                            >
                                                Rank
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-2"
                                            >
                                                Student
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-2 text-right"
                                            >
                                                Exams
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-2 text-right"
                                            >
                                                Average
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-2 text-right"
                                            >
                                                Passed
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-2 text-right"
                                            >
                                                Best / lowest
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {overview.students.map((student) => (
                                            <tr key={student.student_id}>
                                                <td className="px-4 py-2 tabular-nums">
                                                    {student.rank}
                                                </td>
                                                <td className="px-4 py-2">
                                                    <span className="font-medium">
                                                        {student.name}
                                                    </span>
                                                    <span className="block text-xs text-muted-foreground">
                                                        {student.admission_no}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-2 text-right tabular-nums">
                                                    {student.exams}
                                                </td>
                                                <td className="px-4 py-2 text-right tabular-nums">
                                                    {mark(student.average)}%
                                                </td>
                                                <td className="px-4 py-2 text-right tabular-nums">
                                                    {student.passed} /{' '}
                                                    {student.exams}
                                                </td>
                                                <td className="px-4 py-2 text-right tabular-nums">
                                                    {mark(student.best)}% /{' '}
                                                    {mark(student.lowest)}%
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                </>
            )}
        </div>
    );
}

OnlineExamReports.layout = {
    breadcrumbs: [
        { title: 'Online exams', href: index() },
        { title: 'Performance', href: reportsIndex() },
    ],
};
