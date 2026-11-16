import { Link } from '@inertiajs/react';
import { FileBadge, Trophy } from 'lucide-react';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index, reportCard, show } from '@/routes/academics/exams';
import type { ExamResultRow, ResultPaper } from '@/types';

type Props = {
    exam: { id: number; name: string; published: boolean };
    section: { id: number; name: string };
    papers: ResultPaper[];
    rows: ExamResultRow[];
};

export default function SectionResults({ exam, section, papers, rows }: Props) {
    const complete = rows.filter((row) => row.complete);
    const passed = complete.filter((row) => row.passed).length;
    const average =
        complete.length > 0
            ? complete.reduce((sum, row) => sum + (row.percentage ?? 0), 0) /
              complete.length
            : null;
    const top = complete.find((row) => row.rank === 1);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={`Results · ${section.name}`}
                description={exam.name}
                actions={
                    <Button variant="outline" asChild>
                        <Link href={show(exam.id)}>Back to exam</Link>
                    </Button>
                }
            />
            <section
                aria-label="Section summary"
                className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            >
                <StatCard
                    label="Students"
                    value={rows.length}
                    hint={`${complete.length} with all marks`}
                    tone="violet"
                />
                <StatCard
                    label="Passed"
                    value={passed}
                    hint={
                        complete.length > 0
                            ? `${Math.round((passed / complete.length) * 100)}% pass rate`
                            : null
                    }
                    tone="emerald"
                />
                <StatCard
                    label="Class average"
                    value={average !== null ? `${average.toFixed(1)}%` : '—'}
                    tone="sky"
                />
                <StatCard
                    label="Top of class"
                    value={top?.student.name ?? '—'}
                    hint={top ? `${top.percentage}%` : null}
                    icon={Trophy}
                    tone="orange"
                />
            </section>

            <div className="overflow-x-auto rounded-2xl border bg-card shadow-sm">
                <table className="w-full text-sm">
                    <caption className="sr-only">
                        Results for {section.name}
                    </caption>
                    <thead className="bg-gradient-to-r from-navy-50 to-crimson-50 text-left text-xs text-navy-950/70 uppercase dark:from-navy-500/10 dark:to-crimson-500/10 dark:text-navy-100/80">
                        <tr>
                            <th scope="col" className="px-3 py-3 font-medium">
                                Rank
                            </th>
                            <th scope="col" className="px-3 py-3 font-medium">
                                Student
                            </th>
                            {papers.map((paper) => (
                                <th
                                    key={paper.id}
                                    scope="col"
                                    className="px-3 py-3 text-center font-medium"
                                    title={paper.subject}
                                >
                                    {paper.code}
                                    <span className="block text-[10px] font-normal normal-case">
                                        /{paper.max_marks}
                                    </span>
                                </th>
                            ))}
                            <th
                                scope="col"
                                className="px-3 py-3 text-right font-medium"
                            >
                                Total
                            </th>
                            <th
                                scope="col"
                                className="px-3 py-3 text-right font-medium"
                            >
                                %
                            </th>
                            <th scope="col" className="px-3 py-3 font-medium">
                                Grade
                            </th>
                            <th scope="col" className="px-3 py-3 font-medium">
                                Result
                            </th>
                            <th scope="col" className="px-3 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {rows.map((row) => (
                            <tr
                                key={row.student.id}
                                className="hover:bg-navy-50/60 dark:hover:bg-navy-500/5"
                            >
                                <td className="px-3 py-2">
                                    {row.rank !== null ? (
                                        <span
                                            className={cn(
                                                'inline-flex size-7 items-center justify-center rounded-full text-xs font-bold',
                                                row.rank === 1 &&
                                                    'bg-gradient-to-br from-amber-300 to-orange-500 text-white',
                                                row.rank === 2 &&
                                                    'bg-gradient-to-br from-slate-200 to-slate-400 text-slate-900',
                                                row.rank === 3 &&
                                                    'bg-gradient-to-br from-orange-300 to-amber-700 text-white',
                                                row.rank > 3 && 'bg-muted',
                                            )}
                                        >
                                            {row.rank}
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            —
                                        </span>
                                    )}
                                </td>
                                <td className="px-3 py-2 whitespace-nowrap">
                                    <span className="font-medium">
                                        {row.student.name}
                                    </span>
                                    <span className="ml-1.5 text-xs text-muted-foreground">
                                        #{row.student.roll_number ?? '–'}
                                    </span>
                                </td>
                                {papers.map((paper) => {
                                    const subject = row.subjects[paper.id];

                                    return (
                                        <td
                                            key={paper.id}
                                            className={cn(
                                                'px-3 py-2 text-center tabular-nums',
                                                subject &&
                                                    !subject.passed &&
                                                    (subject.marks !== null ||
                                                        subject.is_absent) &&
                                                    'text-rose-600 dark:text-rose-400',
                                            )}
                                        >
                                            {subject?.is_absent
                                                ? 'AB'
                                                : (subject?.marks ?? '—')}
                                        </td>
                                    );
                                })}
                                <td className="px-3 py-2 text-right tabular-nums">
                                    {row.total}/{row.max_total}
                                </td>
                                <td className="px-3 py-2 text-right font-semibold tabular-nums">
                                    {row.percentage ?? '—'}
                                </td>
                                <td className="px-3 py-2 font-semibold">
                                    {row.grade ?? '—'}
                                </td>
                                <td className="px-3 py-2">
                                    {row.passed === null ? (
                                        <TonePill tone="neutral">
                                            Incomplete
                                        </TonePill>
                                    ) : row.passed ? (
                                        <TonePill tone="success">Pass</TonePill>
                                    ) : (
                                        <TonePill tone="danger">Fail</TonePill>
                                    )}
                                </td>
                                <td className="px-3 py-2 text-right">
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link
                                            href={reportCard([
                                                exam.id,
                                                row.student.id,
                                            ])}
                                            aria-label={`Report card for ${row.student.name}`}
                                        >
                                            <FileBadge aria-hidden />
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                        ))}
                        {rows.length === 0 && (
                            <tr>
                                <td
                                    colSpan={papers.length + 7}
                                    className="px-3 py-10 text-center text-muted-foreground"
                                >
                                    No students enrolled in this section.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

SectionResults.layout = {
    breadcrumbs: [{ title: 'Exams & results', href: index() }],
};
