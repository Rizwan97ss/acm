import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

/**
 * Renders Laravel paginator links. Labels from Laravel contain HTML entities
 * (&laquo;), which are decoded here instead of being injected as HTML.
 */
const decode = (label: string): string =>
    label.replace('&laquo;', '«').replace('&raquo;', '»');

export default function Pagination({
    meta,
}: {
    meta: Paginated<unknown>['meta'];
}) {
    if (meta.last_page <= 1) {
        return meta.total > 0 ? (
            <p className="text-sm text-muted-foreground">
                {meta.total} {meta.total === 1 ? 'result' : 'results'}
            </p>
        ) : null;
    }

    return (
        <nav
            aria-label="Pagination"
            className="flex flex-col items-center justify-between gap-3 sm:flex-row"
        >
            <p className="text-sm text-muted-foreground">
                Showing {meta.from}–{meta.to} of {meta.total}
            </p>
            <ul className="flex flex-wrap items-center gap-1">
                {meta.links.map((link, index) => (
                    <li key={`${link.label}-${index}`}>
                        {link.url ? (
                            <Link
                                href={link.url}
                                preserveScroll
                                preserveState
                                aria-current={link.active ? 'page' : undefined}
                                className={cn(
                                    'inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm transition-colors hover:bg-muted',
                                    link.active &&
                                        'border-primary bg-primary text-primary-foreground hover:bg-primary',
                                )}
                            >
                                {decode(link.label)}
                            </Link>
                        ) : (
                            <span className="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm text-muted-foreground/60">
                                {decode(link.label)}
                            </span>
                        )}
                    </li>
                ))}
            </ul>
        </nav>
    );
}
