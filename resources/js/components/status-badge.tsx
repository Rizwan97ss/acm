import { cn } from '@/lib/utils';

export type StatusTone = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

const tones: Record<StatusTone, string> = {
    success:
        'bg-emerald-100 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-500/15 dark:text-emerald-300',
    warning:
        'bg-amber-100 text-amber-900 ring-amber-600/20 dark:bg-amber-500/15 dark:text-amber-300',
    danger: 'bg-rose-100 text-rose-800 ring-rose-600/20 dark:bg-rose-500/15 dark:text-rose-300',
    info: 'bg-sky-100 text-sky-800 ring-sky-600/20 dark:bg-sky-500/15 dark:text-sky-300',
    neutral:
        'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-slate-500/15 dark:text-slate-300',
};

const dots: Record<StatusTone, string> = {
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    danger: 'bg-rose-500',
    info: 'bg-sky-500',
    neutral: 'bg-slate-400',
};

/**
 * Coloured pill with a dot and a text label, so status is never conveyed by colour alone.
 */
export function TonePill({
    tone,
    children,
}: {
    tone: StatusTone;
    children: React.ReactNode;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset',
                tones[tone],
            )}
        >
            <span
                className={cn('size-1.5 rounded-full', dots[tone])}
                aria-hidden
            />
            {children}
        </span>
    );
}

export default function StatusBadge({
    active,
    activeLabel = 'Active',
    inactiveLabel = 'Inactive',
}: {
    active: boolean;
    activeLabel?: string;
    inactiveLabel?: string;
}) {
    return (
        <TonePill tone={active ? 'success' : 'neutral'}>
            {active ? activeLabel : inactiveLabel}
        </TonePill>
    );
}
