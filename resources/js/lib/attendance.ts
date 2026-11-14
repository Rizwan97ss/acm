/**
 * Colours and one-letter codes for attendance statuses. Every status also carries a
 * text label wherever it is shown, so colour is never the only signal.
 */
export const statusStyles: Record<
    string,
    { active: string; idle: string; short: string }
> = {
    present: {
        active: 'bg-emerald-600 text-white ring-emerald-600',
        idle: 'text-emerald-700 ring-emerald-600/30 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-500/10',
        short: 'P',
    },
    absent: {
        active: 'bg-rose-600 text-white ring-rose-600',
        idle: 'text-rose-700 ring-rose-600/30 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10',
        short: 'A',
    },
    late: {
        active: 'bg-amber-500 text-amber-950 ring-amber-500',
        idle: 'text-amber-700 ring-amber-500/40 hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-amber-500/10',
        short: 'L',
    },
    excused: {
        active: 'bg-sky-600 text-white ring-sky-600',
        idle: 'text-sky-700 ring-sky-600/30 hover:bg-sky-50 dark:text-sky-300 dark:hover:bg-sky-500/10',
        short: 'E',
    },
};
