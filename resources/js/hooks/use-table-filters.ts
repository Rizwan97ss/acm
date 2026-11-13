import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

type FilterValue = string | number | null | undefined;

/**
 * Keeps list filters in the query string. Text filters are debounced; every
 * change resets to page 1 and preserves scroll and component state.
 */
export function useTableFilters<T extends Record<string, FilterValue>>(
    url: string,
    initial: T,
    debounceMs = 300,
) {
    const [values, setValues] = useState<T>(initial);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(
        () => () => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
        },
        [],
    );

    const visit = (next: T): void => {
        const query = Object.fromEntries(
            Object.entries(next).filter(
                ([, value]) =>
                    value !== '' && value !== null && value !== undefined,
            ),
        );

        router.get(url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const update = (
        changes: Partial<T>,
        options: { debounce?: boolean } = {},
    ): void => {
        const next = { ...values, ...changes };
        setValues(next);

        if (timer.current) {
            clearTimeout(timer.current);
        }

        if (options.debounce) {
            timer.current = setTimeout(() => visit(next), debounceMs);
        } else {
            visit(next);
        }
    };

    return { values, update };
}
