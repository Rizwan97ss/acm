import { Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowUpDown,
    Award,
    Download,
    Percent,
    Users,
} from 'lucide-react';
import BarChart from '@/components/charts/bar-chart';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { exportMethod, index, show } from '@/routes/academics/online-exams';
import type { ExamSummary, OnlineExam, Standing } from '@/types/exams';
import { mark } from '@/types/exams';

type QuestionStat = {
    id: number;
    number: number;
    prompt: string;
    type: string;
    marks: number;
    answered: number;
    correct: number;
    correct_rate: number | null;
    average_marks: number | null;
    average_rate: number | null;
};

type ItemAnalysis = {
    question_id: number;
    number: number;
    difficulty: number | null;
    discrimination: number | null;
    point_biserial: number | null;
    flags: string[];
};

type Props = {
    exam: OnlineExam;
    report: {
        summary: ExamSummary;
        distribution: { label: string; students: number }[];
        grades: { grade: string; students: number }[];
        sections: {
            section: string;
            students: number;
            average: number | null;
            pass_rate: number;
        }[];
        questions: QuestionStat[];
        standings: Standing[];
        analysis: {
            summary: {
                students: number;
                mean: number | null;
                median: number | null;
                std: number | null;
                q1: number | null;
                q3: number | null;
                skewness: number | null;
                pass_rate: number | null;
            } | null;
            items: ItemAnalysis[];
        } | null;
    };
    can: { export: boolean };
};

const flagText: Record<string, string> = {
    too_easy: 'Too easy',
    too_hard: 'Too hard',
    poor_discrimination: 'Weak at separating students',
    negative_discrimination: 'Weaker students did better: check the key',
};

function Meter({ value }: { value: number | null }) {
    if (value === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span className="flex items-center gap-2">
            <span
                className="h-1.5 w-20 overflow-hidden rounded-full bg-muted"
                aria-hidden
            >
                <span
                    className="block h-full rounded-full bg-primary"
                    style={{ width: `${Math.max(0, Math.min(100, value))}%` }}
                />
            </span>
            <span className="tabular-nums">{mark(value)}%</span>
        </span>
    );
}

export default function OnlineExamReport({ exam, report, can }: Props) {
    const { summary, analysis } = report;
    const items = new Map(
        (analysis?.items ?? []).map((item) => [item.question_id, item]),
    );

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={`Report: ${exam.title}`}
                description={`${exam.subject} · ${exam.audience} · out of ${mark(exam.total_marks)}, pass mark ${mark(exam.passing_marks)}`}
                actions={
                    <>
                        <Button asChild variant="outline">
                            <Link href={show(exam.id)}>
                                <ArrowLeft aria-hidden /> Back to exam
                            </Link>
                        </Button>
                        {can.export && (
                            <>
                                <Button asChild variant="outline">
                                    <a
                                        href={exportMethod.url(exam.id, {
                                            query: { format: 'csv' },
                                        })}
                                    >
                                        <Download aria-hidden /> CSV
                                    </a>
                                </Button>
                                <Button asChild>
                                    <a
                                        href={exportMethod.url(exam.id, {
                                            query: { format: 'xlsx' },
                                        })}
                                    >
                                        <Download aria-hidden /> Excel
                                    </a>
                                </Button>
                            </>
                        )}
                    </>
                }
            />

            {exam.results_status === 'pending' && summary.graded > 0 && (
                <p className="rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                    Results are not approved yet, so these figures may still
                    change while marking continues.
                </p>
            )}

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Handed in"
                    value={`${summary.sat} / ${summary.eligible}`}
                    hint={
                        summary.absent > 0
                            ? `${summary.absent} did not sit`
                            : 'Everyone sat it'
                    }
                    icon={Users}
                    tone="sky"
                />
                <StatCard
                    label="Average"
                    value={
                        summary.average !== null ? `${summary.average}%` : '—'
                    }
                    hint={
                        summary.median !== null
                            ? `Median ${summary.median}%`
                            : null
                    }
                    icon={Percent}
                    tone="violet"
                />
                <StatCard
                    label="Pass rate"
                    value={
                        summary.pass_rate !== null
                            ? `${summary.pass_rate}%`
                            : '—'
                    }
                    hint={`${summary.passed} of ${summary.graded} marked`}
                    icon={Award}
                    tone="emerald"
                />
                <StatCard
                    label="Range"
                    value={
                        summary.highest !== null
                            ? `${summary.lowest}–${summary.highest}%`
                            : '—'
                    }
                    hint={
                        summary.to_mark > 0
                            ? `${summary.to_mark} still to mark`
                            : 'Lowest to highest'
                    }
                    icon={ArrowUpDown}
                    tone="orange"
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <section className="rounded-2xl border bg-card p-5 shadow-sm">
                    <h2 className="font-semibold">Score distribution</h2>
                    <p className="mb-4 text-sm text-muted-foreground">
                        Students per percentage band.
                    </p>
                    <BarChart
                        title="Students per percentage band"
                        valueLabel="Students"
                        data={report.distribution.map((bin) => ({
                            label: `${bin.label}%`,
                            value: bin.students,
                        }))}
                        labelEvery={2}
                    />
                </section>
                <section className="rounded-2xl border bg-card p-5 shadow-sm">
                    <h2 className="font-semibold">Grades</h2>
                    <p className="mb-4 text-sm text-muted-foreground">
                        From the school’s default grade scale.
                    </p>
                    {report.grades.length === 0 ? (
                        <p className="py-10 text-center text-sm text-muted-foreground">
                            No marked attempts yet.
                        </p>
                    ) : (
                        <BarChart
                            title="Students per grade"
                            valueLabel="Students"
                            data={report.grades.map((grade) => ({
                                label: grade.grade,
                                value: grade.students,
                            }))}
                        />
                    )}
                </section>
            </div>

            {analysis?.summary && (
                <section className="rounded-2xl border bg-card p-5 shadow-sm">
                    <h2 className="font-semibold">Spread of marks</h2>
                    <p className="mb-3 text-sm text-muted-foreground">
                        From the analytics service.
                    </p>
                    <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                        {[
                            ['Standard deviation', analysis.summary.std, '%'],
                            ['Lower quartile', analysis.summary.q1, '%'],
                            ['Upper quartile', analysis.summary.q3, '%'],
                            ['Skew', analysis.summary.skewness, ''],
                        ].map(([label, value, unit]) => (
                            <div
                                key={label as string}
                                className="rounded-xl bg-muted/50 p-3"
                            >
                                <dt className="text-xs text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="text-lg font-semibold tabular-nums">
                                    {value === null ? '—' : `${value}${unit}`}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </section>
            )}

            {report.sections.length > 1 && (
                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">By section</h2>
                    <div className="overflow-x-auto rounded-2xl border bg-card shadow-sm">
                        <table className="w-full text-sm">
                            <caption className="sr-only">
                                Results by section
                            </caption>
                            <thead className="bg-muted text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th scope="col" className="px-4 py-2">
                                        Section
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-2 text-right"
                                    >
                                        Students
                                    </th>
                                    <th scope="col" className="px-4 py-2">
                                        Average
                                    </th>
                                    <th scope="col" className="px-4 py-2">
                                        Pass rate
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {report.sections.map((section) => (
                                    <tr key={section.section}>
                                        <td className="px-4 py-2 font-medium">
                                            {section.section}
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {section.students}
                                        </td>
                                        <td className="px-4 py-2">
                                            <Meter value={section.average} />
                                        </td>
                                        <td className="px-4 py-2">
                                            <Meter value={section.pass_rate} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            <section className="space-y-3">
                <div>
                    <h2 className="text-lg font-semibold">Question analysis</h2>
                    <p className="text-sm text-muted-foreground">
                        How each question went.
                        {analysis
                            ? ' Discrimination compares the top and bottom 27% of students: 0.3 or more is good; a negative value usually means a wrong answer key or an unclear question.'
                            : ' Difficulty and discrimination appear here when the analytics service is running and at least two attempts are marked.'}
                    </p>
                </div>
                <div className="overflow-x-auto rounded-2xl border bg-card shadow-sm">
                    <table className="w-full text-sm">
                        <caption className="sr-only">Question analysis</caption>
                        <thead className="bg-muted text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th scope="col" className="px-4 py-2">
                                    #
                                </th>
                                <th scope="col" className="px-4 py-2">
                                    Question
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-2 text-right"
                                >
                                    Answered
                                </th>
                                <th scope="col" className="px-4 py-2">
                                    Fully correct
                                </th>
                                <th scope="col" className="px-4 py-2">
                                    Average mark
                                </th>
                                {analysis && (
                                    <th scope="col" className="px-4 py-2">
                                        Discrimination
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {report.questions.map((question) => {
                                const item = items.get(question.id);

                                return (
                                    <tr key={question.id}>
                                        <td className="px-4 py-2 tabular-nums">
                                            {question.number}
                                        </td>
                                        <td className="max-w-md px-4 py-2">
                                            <p className="break-words">
                                                {question.prompt}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {question.type} ·{' '}
                                                {mark(question.marks)}{' '}
                                                {question.marks === 1
                                                    ? 'mark'
                                                    : 'marks'}
                                            </p>
                                            {item && item.flags.length > 0 && (
                                                <div className="mt-1 flex flex-wrap gap-1">
                                                    {item.flags.map((flag) => (
                                                        <TonePill
                                                            key={flag}
                                                            tone={
                                                                flag ===
                                                                'negative_discrimination'
                                                                    ? 'danger'
                                                                    : 'warning'
                                                            }
                                                        >
                                                            {flagText[flag] ??
                                                                flag}
                                                        </TonePill>
                                                    ))}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {question.answered}
                                        </td>
                                        <td className="px-4 py-2">
                                            <Meter
                                                value={question.correct_rate}
                                            />
                                        </td>
                                        <td className="px-4 py-2">
                                            <Meter
                                                value={question.average_rate}
                                            />
                                        </td>
                                        {analysis && (
                                            <td
                                                className={cn(
                                                    'px-4 py-2 tabular-nums',
                                                    item?.discrimination !==
                                                        null &&
                                                        item?.discrimination !==
                                                            undefined &&
                                                        item.discrimination <
                                                            0 &&
                                                        'font-semibold text-rose-700 dark:text-rose-300',
                                                )}
                                            >
                                                {item?.discrimination ?? '—'}
                                            </td>
                                        )}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    );
}

OnlineExamReport.layout = {
    breadcrumbs: [{ title: 'Online exams', href: index() }],
};
