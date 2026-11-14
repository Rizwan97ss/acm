import { Deferred } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarCheck,
    Download,
    TrendingDown,
    TrendingUp,
    WifiOff,
} from 'lucide-react';
import type { ReactNode } from 'react';
import BarChart from '@/components/charts/bar-chart';
import LineChart from '@/components/charts/line-chart';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { TonePill } from '@/components/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useMoney } from '@/hooks/use-money';
import { insights, report } from '@/routes/analytics';
import type {
    AtRisk,
    AttendanceTrends,
    FinanceInsight,
    Insight,
    Mover,
    Performance,
} from '@/types/analytics';

type Props = {
    configured: boolean;
    sections: { attendance: boolean; performance: boolean; finance: boolean };
    can: { export: boolean };
    attendance?: Insight<AttendanceTrends> | null;
    atRisk?: Insight<AtRisk> | null;
    performance?: Insight<Performance> | null;
    finance?: Insight<FinanceInsight> | null;
};

const percent = (value: number): string => `${value.toFixed(1)}%`;
const shortDate = (date: string): string =>
    new Date(`${date}T12:00:00Z`).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
    });
const shortMonth = (month: string): string =>
    new Date(`${month}-01T12:00:00Z`).toLocaleDateString(undefined, {
        month: 'short',
        year: '2-digit',
    });

function SectionSkeleton() {
    return (
        <div className="grid gap-4 lg:grid-cols-3" aria-busy>
            <Skeleton className="h-28 animate-pulse rounded-2xl" />
            <Skeleton className="h-28 animate-pulse rounded-2xl" />
            <Skeleton className="h-28 animate-pulse rounded-2xl" />
            <Skeleton className="h-64 animate-pulse rounded-xl lg:col-span-3" />
        </div>
    );
}

function Offline() {
    return (
        <Alert>
            <WifiOff aria-hidden />
            <AlertTitle>Analytics service unavailable</AlertTitle>
            <AlertDescription>
                This section could not be calculated right now. The rest of the
                system is unaffected; refresh in a minute.
            </AlertDescription>
        </Alert>
    );
}

function Empty({ children }: { children: ReactNode }) {
    return (
        <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
            {children}
        </p>
    );
}

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-4" aria-label={title}>
            <div>
                <h2 className="text-lg font-semibold">{title}</h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            {children}
        </section>
    );
}

function AttendanceSection({
    attendance,
    atRisk,
}: {
    attendance?: Insight<AttendanceTrends> | null;
    atRisk?: Insight<AtRisk> | null;
}) {
    if (!attendance?.available) {
        return <Offline />;
    }

    const trends = attendance.data;

    if (!trends?.summary) {
        return (
            <Empty>No attendance has been recorded in the last 120 days.</Empty>
        );
    }

    const summary = trends.summary;
    const direction = summary.trend_direction;

    return (
        <div className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Attendance rate"
                    value={
                        summary.overall_rate === null
                            ? '—'
                            : percent(summary.overall_rate)
                    }
                    hint={`${summary.school_days} school days`}
                    icon={CalendarCheck}
                    tone="emerald"
                />
                <StatCard
                    label="Weekly trend"
                    value={
                        summary.weekly_trend === null
                            ? '—'
                            : `${summary.weekly_trend > 0 ? '+' : ''}${summary.weekly_trend.toFixed(2)} pts`
                    }
                    hint={direction ?? 'Not enough weeks yet'}
                    icon={direction === 'declining' ? TrendingDown : TrendingUp}
                    tone={direction === 'declining' ? 'orange' : 'sky'}
                />
                <StatCard
                    label="Students at risk"
                    value={atRisk?.data?.summary.at_risk ?? '—'}
                    hint={
                        atRisk?.data
                            ? `of ${atRisk.data.summary.total} with records`
                            : null
                    }
                    icon={AlertTriangle}
                    tone="pink"
                />
                <StatCard
                    label="Lowest day"
                    value={
                        summary.worst_day
                            ? percent(summary.worst_day.rate)
                            : '—'
                    }
                    hint={
                        summary.worst_day
                            ? shortDate(summary.worst_day.date)
                            : null
                    }
                    tone="violet"
                />
            </div>
            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Daily attendance</CardTitle>
                        <CardDescription>
                            Present and late count as attended; excused days are
                            left out.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <LineChart
                            title="Daily attendance rate and 7-day average"
                            labels={trends.daily.map((d) => shortDate(d.date))}
                            series={[
                                {
                                    name: 'Daily rate',
                                    values: trends.daily.map((d) => d.rate),
                                    colorClass: 'text-muted-foreground',
                                    variant: 'dots',
                                },
                                {
                                    name: '7-day average',
                                    values: trends.daily.map(
                                        (d) => d.moving_average,
                                    ),
                                    colorClass: 'text-primary',
                                },
                            ]}
                            format={percent}
                            domain={[0, 100]}
                            labelEvery={Math.ceil(trends.daily.length / 8)}
                        />
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>By weekday</CardTitle>
                        <CardDescription>
                            Attendance rate per day of the week
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <BarChart
                            title="Attendance rate by weekday"
                            valueLabel="%"
                            data={trends.weekdays.map((d) => ({
                                label: d.weekday.slice(0, 3),
                                value: d.rate ?? 0,
                            }))}
                        />
                    </CardContent>
                </Card>
            </div>
            <AtRiskTable atRisk={atRisk} />
        </div>
    );
}

function AtRiskTable({ atRisk }: { atRisk?: Insight<AtRisk> | null }) {
    if (!atRisk?.available) {
        return <Offline />;
    }

    if (!atRisk.data) {
        return (
            <Empty>Set a current academic year to see students at risk.</Empty>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Early warning</CardTitle>
                <CardDescription>
                    Students with low attendance, a run of recent absences or
                    frequent lateness this academic year, highest risk first.
                </CardDescription>
            </CardHeader>
            <CardContent className="overflow-x-auto">
                {atRisk.data.students.length === 0 ? (
                    <Empty>No students are flagged. 🎉</Empty>
                ) : (
                    <table className="w-full text-sm">
                        <caption className="sr-only">Students at risk</caption>
                        <thead className="text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th scope="col" className="py-2 font-medium">
                                    Student
                                </th>
                                <th scope="col" className="py-2 font-medium">
                                    Class
                                </th>
                                <th
                                    scope="col"
                                    className="py-2 text-right font-medium"
                                >
                                    Rate
                                </th>
                                <th
                                    scope="col"
                                    className="py-2 pl-4 font-medium"
                                >
                                    Why
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {atRisk.data.students.map((student) => (
                                <tr key={student.student_id}>
                                    <td className="py-2">
                                        <span className="font-medium">
                                            {student.name}
                                        </span>{' '}
                                        <span className="font-mono text-xs text-muted-foreground">
                                            {student.admission_no}
                                        </span>
                                    </td>
                                    <td className="py-2">
                                        {student.section ?? '—'}
                                    </td>
                                    <td className="py-2 text-right tabular-nums">
                                        {student.rate === null
                                            ? '—'
                                            : percent(student.rate)}
                                    </td>
                                    <td className="py-2 pl-4">
                                        <div className="flex flex-wrap gap-1">
                                            {student.reasons.map((reason) => (
                                                <TonePill
                                                    key={reason}
                                                    tone={
                                                        reason.startsWith(
                                                            'Attendance',
                                                        )
                                                            ? 'danger'
                                                            : 'warning'
                                                    }
                                                >
                                                    {reason}
                                                </TonePill>
                                            ))}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </CardContent>
        </Card>
    );
}

function MoverList({
    title,
    movers,
    tone,
}: {
    title: string;
    movers: Mover[];
    tone: 'success' | 'danger';
}) {
    return (
        <div>
            <h3 className="mb-2 text-sm font-medium">{title}</h3>
            {movers.length === 0 ? (
                <p className="text-sm text-muted-foreground">None</p>
            ) : (
                <ul className="space-y-1.5 text-sm">
                    {movers.map((mover) => (
                        <li
                            key={mover.student_id}
                            className="flex items-center justify-between gap-2"
                        >
                            <span className="truncate">{mover.name}</span>
                            <TonePill tone={tone}>
                                {mover.change > 0 ? '+' : ''}
                                {mover.change.toFixed(1)} pts
                            </TonePill>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function PerformanceSection({
    performance,
}: {
    performance?: Insight<Performance> | null;
}) {
    if (!performance?.available) {
        return <Offline />;
    }

    const data = performance.data;

    if (!data || data.subjects.length === 0) {
        return (
            <Empty>
                Publish an exam with marks to see performance analytics.
            </Empty>
        );
    }

    const latest = data.exams[data.exams.length - 1];

    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <Card className="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Subjects — {latest?.exam}</CardTitle>
                    <CardDescription>
                        Average, middle score, spread and pass rate per subject.
                        {data.correlation !== null &&
                            ` Attendance–score correlation: ${data.correlation.toFixed(2)}.`}
                    </CardDescription>
                </CardHeader>
                <CardContent className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <caption className="sr-only">
                            Subject statistics
                        </caption>
                        <thead className="text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th scope="col" className="py-2 font-medium">
                                    Subject
                                </th>
                                <th scope="col" className="py-2 font-medium">
                                    Average
                                </th>
                                <th
                                    scope="col"
                                    className="py-2 text-right font-medium"
                                >
                                    Median
                                </th>
                                <th
                                    scope="col"
                                    className="py-2 text-right font-medium"
                                >
                                    Std dev
                                </th>
                                <th
                                    scope="col"
                                    className="py-2 text-right font-medium"
                                >
                                    Pass rate
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {data.subjects.map((subject) => (
                                <tr key={subject.subject}>
                                    <td className="py-2 font-medium">
                                        {subject.subject}
                                    </td>
                                    <td className="py-2">
                                        <div className="flex items-center gap-2">
                                            <div
                                                className="h-2 w-24 rounded-full bg-muted"
                                                aria-hidden
                                            >
                                                <div
                                                    className="h-2 rounded-full bg-primary"
                                                    style={{
                                                        width: `${subject.mean}%`,
                                                    }}
                                                />
                                            </div>
                                            <span className="tabular-nums">
                                                {percent(subject.mean)}
                                            </span>
                                        </div>
                                    </td>
                                    <td className="py-2 text-right tabular-nums">
                                        {percent(subject.median)}
                                    </td>
                                    <td className="py-2 text-right tabular-nums">
                                        {subject.std.toFixed(1)}
                                    </td>
                                    <td className="py-2 text-right">
                                        <TonePill
                                            tone={
                                                subject.pass_rate >= 80
                                                    ? 'success'
                                                    : subject.pass_rate >= 60
                                                      ? 'warning'
                                                      : 'danger'
                                            }
                                        >
                                            {percent(subject.pass_rate)}
                                        </TonePill>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Score distribution</CardTitle>
                    <CardDescription>
                        Students by average score (%)
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <BarChart
                        title="Students by average score band"
                        valueLabel="students"
                        data={data.distribution.map((band) => ({
                            label: band.band,
                            value: band.students,
                        }))}
                    />
                </CardContent>
            </Card>
            {data.exams.length > 1 && (
                <Card className="lg:col-span-3">
                    <CardHeader>
                        <CardTitle>
                            Biggest changes since {data.exams[0].exam}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-6 sm:grid-cols-2">
                        <MoverList
                            title="Improved"
                            movers={data.movers.improved}
                            tone="success"
                        />
                        <MoverList
                            title="Declined"
                            movers={data.movers.declined}
                            tone="danger"
                        />
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

function FinanceSection({
    finance,
}: {
    finance?: Insight<FinanceInsight> | null;
}) {
    const money = useMoney();

    if (!finance?.available) {
        return <Offline />;
    }

    const data = finance.data;

    if (!data) {
        return <Empty>No finance data yet.</Empty>;
    }

    const projected = data.forecast.collected;
    const labels = [
        ...data.history.map((m) => m.month),
        ...projected.map((p) => p.month),
    ];
    const pad = <T,>(
        values: T[],
        before: number,
        after: number,
    ): (T | null)[] => [
        ...Array<null>(before).fill(null),
        ...values,
        ...Array<null>(after).fill(null),
    ];
    const lastActual = data.history[data.history.length - 1];
    const forecastValues = projected.length
        ? [
              ...pad([], data.history.length - 1, 0),
              lastActual?.collected ?? null,
              ...projected.map((p) => p.value),
          ]
        : [];
    const totalOutstanding = data.aging.reduce(
        (sum, bucket) => sum + bucket.amount,
        0,
    );
    const maxBucket = Math.max(1, ...data.aging.map((b) => b.amount));
    const recent = data.history
        .filter((m) => m.collection_rate !== null)
        .slice(-3);
    const recentRate = recent.length
        ? (recent.reduce((sum, m) => sum + m.collected, 0) /
              Math.max(
                  1,
                  recent.reduce((sum, m) => sum + m.billed, 0),
              )) *
          100
        : null;

    return (
        <div className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-3">
                <StatCard
                    label="Collection rate (3 months)"
                    value={recentRate === null ? '—' : percent(recentRate)}
                    tone="emerald"
                />
                <StatCard
                    label="Next month (forecast)"
                    value={
                        projected[0] ? money.format(projected[0].value) : '—'
                    }
                    hint={
                        projected[0]
                            ? `80% range ${money.format(projected[0].low)} – ${money.format(projected[0].high)}`
                            : 'Needs 3 months of history'
                    }
                    tone="sky"
                />
                <StatCard
                    label="Outstanding fees"
                    value={money.format(totalOutstanding)}
                    hint={`${data.aging.reduce((sum, b) => sum + b.invoices, 0)} open invoices`}
                    tone="pink"
                />
            </div>
            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Fee collections and forecast</CardTitle>
                        <CardDescription>
                            The forecast uses exponential smoothing on past
                            collections; the shaded band is an 80% range, a
                            planning aid rather than a promise.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <LineChart
                            title="Monthly fee collections with forecast"
                            labels={labels.map(shortMonth)}
                            series={[
                                {
                                    name: 'Collected',
                                    values: pad(
                                        data.history.map((m) => m.collected),
                                        0,
                                        projected.length,
                                    ),
                                    colorClass:
                                        'text-emerald-600 dark:text-emerald-400',
                                },
                                ...(projected.length
                                    ? [
                                          {
                                              name: 'Forecast',
                                              values: forecastValues,
                                              colorClass: 'text-primary',
                                              variant: 'dashed' as const,
                                              band: {
                                                  low: pad(
                                                      projected.map(
                                                          (p) => p.low,
                                                      ),
                                                      data.history.length,
                                                      0,
                                                  ),
                                                  high: pad(
                                                      projected.map(
                                                          (p) => p.high,
                                                      ),
                                                      data.history.length,
                                                      0,
                                                  ),
                                              },
                                          },
                                      ]
                                    : []),
                            ]}
                            format={(v) => money.format(v)}
                            labelEvery={2}
                        />
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Receivables ageing</CardTitle>
                        <CardDescription>
                            Unpaid balances by days past due
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="space-y-3 text-sm">
                            {data.aging.map((bucket, index) => (
                                <li key={bucket.bucket}>
                                    <div className="mb-1 flex justify-between gap-2">
                                        <span>{bucket.bucket}</span>
                                        <span className="tabular-nums">
                                            {money.format(bucket.amount)}{' '}
                                            <span className="text-muted-foreground">
                                                ({bucket.invoices})
                                            </span>
                                        </span>
                                    </div>
                                    <div
                                        className="h-2 rounded-full bg-muted"
                                        aria-hidden
                                    >
                                        <div
                                            className={
                                                index === 0
                                                    ? 'h-2 rounded-full bg-sky-500'
                                                    : index >= 3
                                                      ? 'h-2 rounded-full bg-rose-500'
                                                      : 'h-2 rounded-full bg-amber-500'
                                            }
                                            style={{
                                                width: `${(bucket.amount / maxBucket) * 100}%`,
                                            }}
                                        />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

export default function Insights({
    configured,
    sections,
    can,
    attendance,
    atRisk,
    performance,
    finance,
}: Props) {
    return (
        <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
            <PageHeader
                title="Insights"
                description="Trends, early warnings and forecasts, refreshed every few minutes."
                actions={
                    can.export && configured ? (
                        <Button variant="outline" asChild>
                            <a href={report.url()}>
                                <Download aria-hidden /> Interactive report
                            </a>
                        </Button>
                    ) : null
                }
            />
            {!configured && (
                <Alert>
                    <WifiOff aria-hidden />
                    <AlertTitle>Analytics isn't set up yet</AlertTitle>
                    <AlertDescription>
                        Set ANALYTICS_URL and ANALYTICS_SHARED_SECRET in .env
                        and start the analytics service (see
                        docs/ARCHITECTURE.md).
                    </AlertDescription>
                </Alert>
            )}
            {sections.attendance && (
                <Section
                    title="Attendance"
                    description="The last 120 days across the whole school."
                >
                    <Deferred
                        data={['attendance', 'atRisk']}
                        fallback={<SectionSkeleton />}
                    >
                        <AttendanceSection
                            attendance={attendance}
                            atRisk={atRisk}
                        />
                    </Deferred>
                </Section>
            )}
            {sections.performance && (
                <Section
                    title="Exam performance"
                    description="The latest published exam, compared with the one before it."
                >
                    <Deferred data="performance" fallback={<SectionSkeleton />}>
                        <PerformanceSection performance={performance} />
                    </Deferred>
                </Section>
            )}
            {sections.finance && (
                <Section
                    title="Finance"
                    description="Twelve months of collections with a three-month outlook."
                >
                    <Deferred data="finance" fallback={<SectionSkeleton />}>
                        <FinanceSection finance={finance} />
                    </Deferred>
                </Section>
            )}
        </div>
    );
}

Insights.layout = {
    breadcrumbs: [{ title: 'Insights', href: insights() }],
};
