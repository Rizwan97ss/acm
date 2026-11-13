import { usePage } from '@inertiajs/react';

type Parts = Record<string, string>;

const partsFor = (date: Date, timeZone: string): Parts => {
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(date);

    const result: Parts = {};

    for (const part of parts) {
        result[part.type] = part.value;
    }

    result.monthShort = new Intl.DateTimeFormat('en-GB', {
        timeZone,
        month: 'short',
    }).format(date);

    return result;
};

/**
 * Render a date using the PHP-style tokens offered in the localization settings
 * (Y, m, d, M, H, h, i, A).
 */
export function formatWithPattern(
    date: Date,
    pattern: string,
    timeZone: string,
): string {
    const p = partsFor(date, timeZone);
    const hour = Number(p.hour);

    const tokens: Record<string, string> = {
        Y: p.year,
        m: p.month,
        d: p.day,
        M: p.monthShort,
        H: p.hour,
        h: String(hour % 12 || 12).padStart(2, '0'),
        i: p.minute,
        A: hour < 12 ? 'AM' : 'PM',
    };

    return pattern.replace(/[YmdMHhiA]/g, (token) => tokens[token] ?? token);
}

/**
 * Date formatting that honours the user's resolved localization preferences.
 */
export function useFormat() {
    const { preferences } = usePage().props;
    const timeZone = preferences.timezone ?? 'UTC';
    const dateFormat = preferences.dateFormat ?? 'Y-m-d';
    const timeFormat = preferences.timeFormat ?? 'H:i';

    const parse = (value: string | null | undefined): Date | null => {
        if (!value) {
            return null;
        }

        const date = new Date(value);

        return Number.isNaN(date.getTime()) ? null : date;
    };

    return {
        date: (value: string | null | undefined, fallback = '—'): string => {
            const date = parse(value);

            return date
                ? formatWithPattern(date, dateFormat, timeZone)
                : fallback;
        },
        dateTime: (
            value: string | null | undefined,
            fallback = '—',
        ): string => {
            const date = parse(value);

            return date
                ? formatWithPattern(
                      date,
                      `${dateFormat} ${timeFormat}`,
                      timeZone,
                  )
                : fallback;
        },
    };
}
