import { Head, usePage } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/hooks/use-format';
import type { ExamResultRow, ResultPaper } from '@/types';

type Props = {
    exam: {
        id: number;
        name: string;
        academic_year: string;
        term: string | null;
        published: boolean;
    };
    student: {
        id: number;
        name: string;
        admission_no: string;
        date_of_birth: string;
    };
    result:
        | (ExamResultRow & {
              section: string;
              class_size: number;
              papers: ResultPaper[];
          })
        | null;
    attendance: {
        present: number;
        absent: number;
        late: number;
        excused: number;
        total: number;
        percentage: number | null;
    };
    bands: { grade: string; min_percentage: number; remark: string | null }[];
};

/**
 * A printable report card. Print styles hide the app chrome so only the card prints.
 */
export default function ReportCard({
    exam,
    student,
    result,
    attendance,
    bands,
}: Props) {
    const { branding, tenant } = usePage().props;
    const format = useFormat();

    return (
        <div className="flex flex-1 flex-col items-center gap-4 p-4 md:p-6 print:p-0">
            <Head title={`Report card · ${student.name}`} />
            <div className="flex w-full max-w-3xl justify-end print:hidden">
                <Button
                    onClick={() => window.print()}
                    className="bg-gradient-to-r from-indigo-600 to-violet-600 text-white"
                >
                    <Printer aria-hidden /> Print
                </Button>
            </div>

            <article className="w-full max-w-3xl overflow-hidden rounded-3xl border bg-card shadow-xl print:rounded-none print:border-0 print:shadow-none">
                <header className="flex items-center gap-4 bg-gradient-to-r from-indigo-700 via-violet-700 to-fuchsia-600 px-6 py-5 text-white print:bg-none print:text-black">
                    <span className="flex size-12 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/30">
                        {branding?.logoUrl ? (
                            <img
                                src={branding.logoUrl}
                                alt=""
                                className="size-full rounded-xl object-cover"
                            />
                        ) : (
                            <AppLogoIcon className="size-7 fill-current" />
                        )}
                    </span>
                    <div>
                        <p className="text-lg font-bold">
                            {tenant?.current?.name ?? branding?.name}
                        </p>
                        <p className="text-sm text-white/85 print:text-black">
                            Report card · {exam.name} · {exam.academic_year}
                            {exam.term ? ` · ${exam.term}` : ''}
                        </p>
                    </div>
                </header>

                <div className="space-y-6 p-6">
                    {!exam.published && (
                        <p className="rounded-xl bg-amber-100 px-4 py-2 text-sm text-amber-900 print:hidden">
                            Draft — results are not yet published to students
                            and parents.
                        </p>
                    )}

                    <dl className="grid gap-4 text-sm sm:grid-cols-4">
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Student
                            </dt>
                            <dd className="font-semibold">{student.name}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Admission no.
                            </dt>
                            <dd className="font-mono">
                                {student.admission_no}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Class
                            </dt>
                            <dd>{result?.section ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Date of birth
                            </dt>
                            <dd>
                                {format.date(
                                    `${student.date_of_birth}T12:00:00Z`,
                                )}
                            </dd>
                        </div>
                    </dl>

                    {result === null ? (
                        <p className="text-sm text-muted-foreground">
                            The student was not enrolled in this academic year.
                        </p>
                    ) : (
                        <>
                            <table className="w-full text-sm">
                                <caption className="sr-only">
                                    Subject results
                                </caption>
                                <thead className="border-b text-left text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="py-2 font-medium"
                                        >
                                            Subject
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 text-right font-medium"
                                        >
                                            Marks
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 text-right font-medium"
                                        >
                                            Out of
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 text-center font-medium"
                                        >
                                            Grade
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 font-medium"
                                        >
                                            Result
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {result.papers.map((paper) => {
                                        const subject =
                                            result.subjects[paper.id];

                                        return (
                                            <tr key={paper.id}>
                                                <td className="py-2">
                                                    {paper.subject}
                                                </td>
                                                <td className="py-2 text-right tabular-nums">
                                                    {subject?.is_absent
                                                        ? 'Absent'
                                                        : (subject?.marks ??
                                                          '—')}
                                                </td>
                                                <td className="py-2 text-right text-muted-foreground tabular-nums">
                                                    {paper.max_marks}
                                                </td>
                                                <td className="py-2 text-center font-semibold">
                                                    {subject?.grade ?? '—'}
                                                </td>
                                                <td className="py-2">
                                                    {subject ? (
                                                        subject.passed ? (
                                                            <TonePill tone="success">
                                                                Pass
                                                            </TonePill>
                                                        ) : (
                                                            <TonePill tone="danger">
                                                                Fail
                                                            </TonePill>
                                                        )
                                                    ) : (
                                                        '—'
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>

                            <div className="grid gap-3 sm:grid-cols-4">
                                {[
                                    {
                                        label: 'Total',
                                        value: `${result.total} / ${result.max_total}`,
                                        tone: 'from-indigo-500 to-violet-600',
                                    },
                                    {
                                        label: 'Percentage',
                                        value:
                                            result.percentage !== null
                                                ? `${result.percentage}%`
                                                : '—',
                                        tone: 'from-sky-500 to-cyan-600',
                                    },
                                    {
                                        label: 'Grade',
                                        value: result.grade ?? '—',
                                        tone: 'from-emerald-500 to-teal-600',
                                    },
                                    {
                                        label: 'Rank',
                                        value:
                                            result.rank !== null
                                                ? `${result.rank} of ${result.class_size}`
                                                : '—',
                                        tone: 'from-orange-500 to-pink-600',
                                    },
                                ].map((tile) => (
                                    <div
                                        key={tile.label}
                                        className={`rounded-2xl bg-gradient-to-br p-4 text-white ${tile.tone} print:bg-none print:text-black print:ring-1 print:ring-black/20`}
                                    >
                                        <p className="text-xs text-white/85 print:text-black">
                                            {tile.label}
                                        </p>
                                        <p className="text-xl font-bold">
                                            {tile.value}
                                        </p>
                                    </div>
                                ))}
                            </div>

                            <p className="text-sm">
                                <span className="font-semibold">Overall: </span>
                                {result.passed === null
                                    ? 'Incomplete'
                                    : result.passed
                                      ? 'Passed'
                                      : 'Not passed'}
                                {result.remark ? ` · ${result.remark}` : ''}
                            </p>
                        </>
                    )}

                    <section className="rounded-2xl bg-muted/60 p-4 text-sm">
                        <h2 className="mb-2 font-semibold">
                            Attendance this year
                        </h2>
                        <p>
                            {attendance.percentage !== null
                                ? `${attendance.percentage}%`
                                : 'No records'}{' '}
                            · present {attendance.present}, late{' '}
                            {attendance.late}, absent {attendance.absent},
                            excused {attendance.excused}
                        </p>
                    </section>

                    <footer className="text-xs text-muted-foreground">
                        Grading:{' '}
                        {bands
                            .map(
                                (band) =>
                                    `${band.grade} ≥ ${band.min_percentage}%`,
                            )
                            .join(' · ')}
                    </footer>
                </div>
            </article>
        </div>
    );
}
