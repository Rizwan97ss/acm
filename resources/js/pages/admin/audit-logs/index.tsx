import { ChevronRight } from 'lucide-react';
import type { Column } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import Pagination from '@/components/data/pagination';
import SearchInput from '@/components/data/search-input';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useFormat } from '@/hooks/use-format';
import { useTableFilters } from '@/hooks/use-table-filters';
import { index } from '@/routes/admin/audit-logs';
import type { AuditLog, Paginated } from '@/types';

type Props = {
    logs: Paginated<AuditLog>;
    filters: { search?: string; event?: string; from?: string; to?: string };
    events: string[];
};

function Changes({ log }: { log: AuditLog }) {
    const keys = [
        ...new Set([
            ...Object.keys(log.old_values ?? {}),
            ...Object.keys(log.new_values ?? {}),
        ]),
    ];

    if (keys.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    const render = (value: unknown): string => {
        if (value === null || value === undefined) {
            return '∅';
        }

        return typeof value === 'string' ? value : JSON.stringify(value);
    };

    return (
        <details className="group min-w-56">
            <summary className="flex cursor-pointer list-none items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
                <ChevronRight
                    className="size-3 transition-transform group-open:rotate-90"
                    aria-hidden
                />
                {keys.length} {keys.length === 1 ? 'field' : 'fields'}
            </summary>
            <dl className="mt-2 grid gap-1 text-xs">
                {keys.map((key) => (
                    <div
                        key={key}
                        className="grid grid-cols-[minmax(0,8rem)_1fr] gap-2"
                    >
                        <dt className="truncate font-medium">{key}</dt>
                        <dd className="break-all">
                            {log.old_values && key in log.old_values && (
                                <span className="text-muted-foreground line-through">
                                    {render(log.old_values[key])}
                                </span>
                            )}{' '}
                            {log.new_values && key in log.new_values && (
                                <span>{render(log.new_values[key])}</span>
                            )}
                        </dd>
                    </div>
                ))}
            </dl>
        </details>
    );
}

export default function AuditLogsIndex({ logs, filters, events }: Props) {
    const format = useFormat();
    const { values, update } = useTableFilters(index.url(), {
        search: filters.search ?? '',
        event: filters.event ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
    });

    const columns: Column<AuditLog>[] = [
        {
            key: 'when',
            header: 'When',
            className: 'whitespace-nowrap text-muted-foreground',
            cell: (log) => format.dateTime(log.created_at),
        },
        {
            key: 'who',
            header: 'Who',
            cell: (log) =>
                log.user ? (
                    <span title={log.user.email}>{log.user.name}</span>
                ) : (
                    <span className="text-muted-foreground">
                        System / guest
                    </span>
                ),
        },
        {
            key: 'event',
            header: 'Event',
            cell: (log) => (
                <Badge variant="outline" className="font-mono">
                    {log.event}
                </Badge>
            ),
        },
        {
            key: 'subject',
            header: 'Subject',
            cell: (log) =>
                log.auditable_type
                    ? `${log.auditable_type} #${log.auditable_id}`
                    : '—',
        },
        {
            key: 'changes',
            header: 'Changes',
            cell: (log) => <Changes log={log} />,
        },
        {
            key: 'ip',
            header: 'IP',
            className: 'font-mono text-xs text-muted-foreground',
            cell: (log) => log.ip_address ?? '—',
        },
    ];

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Audit log"
                description="Every sign-in, permission change and record change. Sensitive values are masked."
            />
            <div className="flex flex-col gap-2 md:flex-row md:items-center">
                <SearchInput
                    value={values.search}
                    onChange={(search) =>
                        update({ search }, { debounce: true })
                    }
                    placeholder="User name or email"
                />
                <NativeSelect
                    aria-label="Filter by event"
                    value={values.event}
                    onChange={(event) => update({ event: event.target.value })}
                    className="md:w-56"
                >
                    <option value="">All events</option>
                    {events.map((event) => (
                        <option key={event} value={event}>
                            {event}
                        </option>
                    ))}
                </NativeSelect>
                <Input
                    type="date"
                    aria-label="From date"
                    value={values.from}
                    onChange={(event) => update({ from: event.target.value })}
                    className="md:w-40"
                />
                <Input
                    type="date"
                    aria-label="To date"
                    value={values.to}
                    onChange={(event) => update({ to: event.target.value })}
                    className="md:w-40"
                />
            </div>
            <DataTable
                caption="Audit log"
                rows={logs.data}
                columns={columns}
                rowKey={(log) => log.id}
                empty="No activity matches these filters."
            />
            <Pagination meta={logs.meta} />
        </div>
    );
}

AuditLogsIndex.layout = {
    breadcrumbs: [{ title: 'Audit log', href: index() }],
};
