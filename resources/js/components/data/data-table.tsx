import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type Column<T> = {
    key: string;
    header: string;
    cell: (row: T) => ReactNode;
    /** Query-string sort key; the column header becomes a sort toggle when set. */
    sortKey?: string;
    className?: string;
    headerClassName?: string;
};

export type SortState = {
    column?: string | null;
    direction?: 'asc' | 'desc' | null;
};

type DataTableProps<T> = {
    rows: T[];
    columns: Column<T>[];
    rowKey: (row: T) => string | number;
    caption: string;
    empty?: ReactNode;
    sort?: SortState;
    onSort?: (column: string, direction: 'asc' | 'desc') => void;
};

/**
 * Accessible, responsive data table. Horizontal overflow scrolls inside the
 * card so the page itself never scrolls sideways on phones.
 */
export function DataTable<T>({
    rows,
    columns,
    rowKey,
    caption,
    empty,
    sort,
    onSort,
}: DataTableProps<T>) {
    const toggle = (column: string): void => {
        const nextDirection =
            sort?.column === column && sort.direction === 'asc'
                ? 'desc'
                : 'asc';

        onSort?.(column, nextDirection);
    };

    return (
        <div className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <caption className="sr-only">{caption}</caption>
                    <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                        <tr>
                            {columns.map((column) => {
                                const active = sort?.column === column.sortKey;
                                const ariaSort = active
                                    ? sort?.direction === 'desc'
                                        ? 'descending'
                                        : 'ascending'
                                    : undefined;

                                return (
                                    <th
                                        key={column.key}
                                        scope="col"
                                        aria-sort={ariaSort}
                                        className={cn(
                                            'px-4 py-3 font-medium whitespace-nowrap',
                                            column.headerClassName,
                                        )}
                                    >
                                        {column.sortKey && onSort ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    toggle(column.sortKey!)
                                                }
                                                className="inline-flex items-center gap-1 uppercase hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
                                            >
                                                {column.header}
                                                {active ? (
                                                    sort?.direction ===
                                                    'desc' ? (
                                                        <ArrowDown
                                                            className="size-3"
                                                            aria-hidden
                                                        />
                                                    ) : (
                                                        <ArrowUp
                                                            className="size-3"
                                                            aria-hidden
                                                        />
                                                    )
                                                ) : (
                                                    <ArrowUpDown
                                                        className="size-3 opacity-50"
                                                        aria-hidden
                                                    />
                                                )}
                                            </button>
                                        ) : (
                                            column.header
                                        )}
                                    </th>
                                );
                            })}
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {rows.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    className="px-4 py-12 text-center text-muted-foreground"
                                >
                                    {empty ?? 'Nothing to show yet.'}
                                </td>
                            </tr>
                        ) : (
                            rows.map((row) => (
                                <tr
                                    key={rowKey(row)}
                                    className="transition-colors hover:bg-muted/30"
                                >
                                    {columns.map((column) => (
                                        <td
                                            key={column.key}
                                            className={cn(
                                                'px-4 py-3 align-middle',
                                                column.className,
                                            )}
                                        >
                                            {column.cell(row)}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
