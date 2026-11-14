import type { Column } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import SearchInput from '@/components/data/search-input';
import PageHeader from '@/components/page-header';
import type { StatusTone } from '@/components/status-badge';
import { TonePill } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { NativeSelect } from '@/components/ui/native-select';
import { useTableFilters } from '@/hooks/use-table-filters';
import { index } from '@/routes/admin/activity-logs';

type Entry = {
    time: string;
    level: 'info' | 'warning' | 'error';
    method: string;
    route: string;
    action: string | null;
    user_id: number | null;
    user: { id: number; name: string; email: string } | null;
    parameters: Record<string, string | number | null>;
    fields: string[];
    status: number | null;
    duration_ms: number | null;
    ip: string | null;
};

type Row = Entry & { key: number };

type Props = {
    entries: Entry[];
    dates: string[];
    filters: { date: string; level?: string; search?: string };
};

const levelTones: Record<Entry['level'], StatusTone> = {
    info: 'success',
    warning: 'warning',
    error: 'danger',
};

export default function ActivityLogs({ entries, dates, filters }: Props) {
    const { values, update } = useTableFilters(index.url(), {
        date: filters.date,
        level: filters.level ?? '',
        search: filters.search ?? '',
    });

    const rows: Row[] = entries.map((entry, key) => ({ ...entry, key }));

    const columns: Column<Row>[] = [
        {
            key: 'time',
            header: 'Time',
            className:
                'whitespace-nowrap font-mono text-xs text-muted-foreground',
            cell: (entry) => entry.time.slice(11),
        },
        {
            key: 'who',
            header: 'Who',
            cell: (entry) =>
                entry.user ? (
                    <span title={entry.user.email}>{entry.user.name}</span>
                ) : (
                    <span className="text-muted-foreground">Guest</span>
                ),
        },
        {
            key: 'request',
            header: 'Request',
            cell: (entry) => (
                <div className="min-w-48 space-y-1">
                    <div className="flex items-center gap-2">
                        <Badge variant="outline" className="font-mono">
                            {entry.method}
                        </Badge>
                        <span className="font-mono text-xs break-all">
                            {entry.route}
                        </span>
                    </div>
                    {entry.action && (
                        <p className="text-xs break-all text-muted-foreground">
                            {entry.action}
                        </p>
                    )}
                </div>
            ),
        },
        {
            key: 'details',
            header: 'Records / fields',
            cell: (entry) => {
                const parameters = Object.entries(entry.parameters).map(
                    ([name, value]) => `${name} #${value ?? '?'}`,
                );

                if (parameters.length === 0 && entry.fields.length === 0) {
                    return <span className="text-muted-foreground">—</span>;
                }

                return (
                    <div className="space-y-1 text-xs">
                        {parameters.length > 0 && (
                            <p>{parameters.join(', ')}</p>
                        )}
                        {entry.fields.length > 0 && (
                            <p className="text-muted-foreground">
                                Fields: {entry.fields.join(', ')}
                            </p>
                        )}
                    </div>
                );
            },
        },
        {
            key: 'status',
            header: 'Result',
            cell: (entry) => (
                <TonePill tone={levelTones[entry.level]}>
                    {entry.status ?? '—'}
                    {entry.duration_ms !== null && ` · ${entry.duration_ms} ms`}
                </TonePill>
            ),
        },
        {
            key: 'ip',
            header: 'IP',
            className: 'font-mono text-xs text-muted-foreground',
            cell: (entry) => entry.ip ?? '—',
        },
    ];

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Activity log"
                description="Every page visit and action, newest first. Submitted values are never recorded, only field names."
            />
            <div className="flex flex-col gap-2 md:flex-row md:items-center">
                <SearchInput
                    value={values.search}
                    onChange={(search) =>
                        update({ search }, { debounce: true })
                    }
                    placeholder="Route or controller"
                />
                <NativeSelect
                    aria-label="Day"
                    value={values.date}
                    onChange={(event) => update({ date: event.target.value })}
                    className="md:w-44"
                >
                    {(dates.includes(values.date)
                        ? dates
                        : [values.date, ...dates]
                    ).map((date) => (
                        <option key={date} value={date}>
                            {date}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect
                    aria-label="Filter by result"
                    value={values.level}
                    onChange={(event) => update({ level: event.target.value })}
                    className="md:w-44"
                >
                    <option value="">All results</option>
                    <option value="info">Succeeded</option>
                    <option value="warning">Denied or invalid</option>
                    <option value="error">Server errors</option>
                </NativeSelect>
            </div>
            <DataTable
                caption="Activity log"
                rows={rows}
                columns={columns}
                rowKey={(row) => row.key}
                empty="No activity for this day and filter. The 200 most recent matching entries are shown."
            />
        </div>
    );
}

ActivityLogs.layout = {
    breadcrumbs: [{ title: 'Activity log', href: index() }],
};
