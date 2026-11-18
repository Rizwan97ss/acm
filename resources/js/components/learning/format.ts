export function fileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ['KB', 'MB', 'GB'];
    let value = bytes / 1024;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }

    return `${value.toFixed(value >= 10 ? 0 : 1)} ${units[unit]}`;
}

/** "in 3 days", "2 hours ago", for upcoming classes and deadlines. */
export function relativeTime(iso: string, now = Date.now()): string {
    const diff = new Date(iso).getTime() - now;
    const abs = Math.abs(diff);
    const formatter = new Intl.RelativeTimeFormat(undefined, {
        numeric: 'auto',
    });
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['day', 86_400_000],
        ['hour', 3_600_000],
        ['minute', 60_000],
    ];

    for (const [unit, size] of units) {
        if (abs >= size || unit === 'minute') {
            return formatter.format(Math.round(diff / size), unit);
        }
    }

    return '';
}

/** ISO timestamp → value for an <input type="datetime-local"> in the viewer's time zone. */
export function isoToLocalInput(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());

    return date.toISOString().slice(0, 16);
}

/** datetime-local value → ISO timestamp with the viewer's offset, so the server stores the right moment. */
export function localInputToIso(value: string): string | null {
    return value === '' ? null : new Date(value).toISOString();
}
