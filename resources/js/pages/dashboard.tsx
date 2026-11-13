import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    Building2,
    ShieldCheck,
    UserCheck,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import BarChart from '@/components/charts/bar-chart';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useFormat } from '@/hooks/use-format';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import { index as auditLogs } from '@/routes/admin/audit-logs';
import type { AuditLog } from '@/types';

type Stat = { key: string; label: string; value: number; hint: string | null };

type DashboardProps = {
    stats?: Stat[];
    loginActivity?: { date: string; logins: number; failed: number }[];
    roleDistribution?: { name: string; count: number }[];
    recentActivity?: AuditLog[];
    todaysLessons?: {
        period: string;
        starts_at: string;
        ends_at: string;
        subject: string;
        color: string | null;
        section: string;
        room: string | null;
    }[];
};

const statIcons: Record<string, LucideIcon> = {
    users: Users,
    active_week: UserCheck,
    roles: ShieldCheck,
    schools: Building2,
};

function greeting(): string {
    const hour = new Date().getHours();

    return hour < 12
        ? 'Good morning'
        : hour < 18
          ? 'Good afternoon'
          : 'Good evening';
}

function WidgetSkeleton() {
    return (
        <div className="space-y-3" aria-busy="true" aria-label="Loading">
            <Skeleton className="h-44 w-full" />
            <Skeleton className="h-3 w-1/3" />
        </div>
    );
}

export default function Dashboard({
    stats,
    loginActivity,
    roleDistribution,
    recentActivity,
    todaysLessons,
}: DashboardProps) {
    const { auth, tenant } = usePage().props;
    const { can } = usePermissions();
    const format = useFormat();
    const showsActivity = can('audit_logs.view');
    const showsRoles = can('roles.view') && can('users.view');

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Dashboard"
                description={`${greeting()}, ${auth.user.name.split(' ')[0]}. ${
                    tenant.current
                        ? `You are working in ${tenant.current.name}.`
                        : auth.roles.join(', ') || 'Welcome back.'
                }`}
            />

            {stats && stats.length > 0 && (
                <section
                    aria-label="Key figures"
                    className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                >
                    {stats.map((stat) => (
                        <StatCard
                            key={stat.key}
                            label={stat.label}
                            value={stat.value}
                            hint={stat.hint}
                            icon={statIcons[stat.key]}
                        />
                    ))}
                </section>
            )}

            {todaysLessons && (
                <Card>
                    <CardHeader>
                        <CardTitle>Today's lessons</CardTitle>
                        <CardDescription>
                            {todaysLessons.length === 0
                                ? 'No lessons scheduled for you today.'
                                : `${todaysLessons.length} lessons today`}
                        </CardDescription>
                    </CardHeader>
                    {todaysLessons.length > 0 && (
                        <CardContent>
                            <ol className="divide-y">
                                {todaysLessons.map((lesson) => (
                                    <li
                                        key={`${lesson.period}-${lesson.section}`}
                                        className="flex items-center gap-3 py-2.5 text-sm"
                                    >
                                        <span className="w-24 shrink-0 text-xs text-muted-foreground tabular-nums">
                                            {lesson.starts_at}–{lesson.ends_at}
                                        </span>
                                        <span
                                            className="h-8 w-1 shrink-0 rounded-full"
                                            style={{
                                                backgroundColor:
                                                    lesson.color ??
                                                    'var(--primary)',
                                            }}
                                            aria-hidden
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate font-medium">
                                                {lesson.subject}
                                            </span>
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {lesson.section}
                                                {lesson.room
                                                    ? ` · ${lesson.room}`
                                                    : ''}
                                            </span>
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        </CardContent>
                    )}
                </Card>
            )}

            {(showsActivity || showsRoles) && (
                <section className="grid gap-4 lg:grid-cols-3">
                    {showsActivity && (
                        <Card className="lg:col-span-2">
                            <CardHeader>
                                <CardTitle>Sign-ins</CardTitle>
                                <CardDescription>
                                    Successful sign-ins per day, last 14 days
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {loginActivity ? (
                                    <BarChart
                                        title="Successful sign-ins per day"
                                        valueLabel="sign-ins"
                                        labelEvery={2}
                                        data={loginActivity.map((day) => ({
                                            label: format.date(
                                                `${day.date}T12:00:00Z`,
                                            ),
                                            value: day.logins,
                                            detail:
                                                day.failed > 0
                                                    ? `${day.failed} failed attempts`
                                                    : undefined,
                                        }))}
                                    />
                                ) : (
                                    <WidgetSkeleton />
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {showsRoles && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Users by role</CardTitle>
                                <CardDescription>
                                    Most-assigned roles
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {roleDistribution ? (
                                    roleDistribution.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No roles assigned yet.
                                        </p>
                                    ) : (
                                        <ul className="space-y-3">
                                            {roleDistribution.map((role) => {
                                                const max =
                                                    roleDistribution[0].count;

                                                return (
                                                    <li
                                                        key={role.name}
                                                        className="space-y-1"
                                                    >
                                                        <div className="flex justify-between text-sm">
                                                            <span className="truncate">
                                                                {role.name}
                                                            </span>
                                                            <span className="text-muted-foreground tabular-nums">
                                                                {role.count}
                                                            </span>
                                                        </div>
                                                        <div className="h-2 rounded-full bg-muted">
                                                            <div
                                                                className="h-2 rounded-full bg-primary"
                                                                style={{
                                                                    width: `${(role.count / max) * 100}%`,
                                                                }}
                                                            />
                                                        </div>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    )
                                ) : (
                                    <WidgetSkeleton />
                                )}
                            </CardContent>
                        </Card>
                    )}
                </section>
            )}

            {showsActivity && (
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4">
                        <div className="space-y-1.5">
                            <CardTitle>Recent activity</CardTitle>
                            <CardDescription>
                                Latest entries from the audit log
                            </CardDescription>
                        </div>
                        <Link
                            href={auditLogs()}
                            className="text-sm font-medium text-primary hover:underline"
                        >
                            View all
                        </Link>
                    </CardHeader>
                    <CardContent>
                        {recentActivity ? (
                            <ul className="divide-y">
                                {recentActivity.map((log) => (
                                    <li
                                        key={log.id}
                                        className="flex items-center gap-3 py-2.5 text-sm"
                                    >
                                        <Activity
                                            className="size-4 shrink-0 text-muted-foreground"
                                            aria-hidden
                                        />
                                        <span className="min-w-0 flex-1 truncate">
                                            <span className="font-medium">
                                                {log.user?.name ?? 'System'}
                                            </span>{' '}
                                            <span className="text-muted-foreground">
                                                {log.event}
                                            </span>
                                        </span>
                                        <time
                                            className="shrink-0 text-xs text-muted-foreground"
                                            dateTime={
                                                log.created_at ?? undefined
                                            }
                                        >
                                            {format.dateTime(log.created_at)}
                                        </time>
                                    </li>
                                ))}
                                {recentActivity.length === 0 && (
                                    <li className="py-6 text-sm text-muted-foreground">
                                        No activity yet.
                                    </li>
                                )}
                            </ul>
                        ) : (
                            <div
                                className="space-y-2"
                                aria-busy="true"
                                aria-label="Loading"
                            >
                                {Array.from({ length: 5 }, (_, index) => (
                                    <Skeleton
                                        key={index}
                                        className="h-6 w-full"
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

            {!stats && !showsActivity && !todaysLessons && (
                <Card>
                    <CardHeader>
                        <CardTitle>Welcome</CardTitle>
                        <CardDescription>
                            Your personal dashboard will show timetables,
                            attendance, results and fees here as those modules
                            are enabled for your role.
                        </CardDescription>
                    </CardHeader>
                </Card>
            )}
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
