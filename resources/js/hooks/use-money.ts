import { usePage } from '@inertiajs/react';

/**
 * Formats integer minor units (cents) in the institute's currency.
 */
export function useMoney() {
    const { preferences } = usePage().props;
    const currency = preferences.currency ?? 'USD';

    let formatter: Intl.NumberFormat;

    try {
        formatter = new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
        });
    } catch {
        formatter = new Intl.NumberFormat(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    return {
        currency,
        format: (cents: number | null | undefined): string =>
            cents === null || cents === undefined
                ? '—'
                : formatter.format(cents / 100),
        toMajor: (cents: number): string => (cents / 100).toFixed(2),
    };
}
