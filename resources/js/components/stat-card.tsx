import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export type StatTone = 'violet' | 'emerald' | 'orange' | 'sky' | 'pink';

const tones: Record<StatTone, string> = {
    violet: 'from-violet-600 to-indigo-600 shadow-indigo-500/25',
    emerald: 'from-emerald-600 to-teal-600 shadow-emerald-500/25',
    orange: 'from-orange-600 to-rose-600 shadow-orange-500/25',
    sky: 'from-sky-600 to-cyan-700 shadow-sky-500/25',
    pink: 'from-pink-600 to-fuchsia-600 shadow-pink-500/25',
};

type StatCardProps = {
    label: string;
    value: number | string;
    hint?: string | null;
    icon?: LucideIcon;
    tone?: StatTone;
};

/**
 * A headline figure on a coloured gradient tile. Text stays white on the
 * darker end of each gradient for contrast.
 */
export default function StatCard({
    label,
    value,
    hint,
    icon: Icon,
    tone = 'violet',
}: StatCardProps) {
    return (
        <div
            className={cn(
                'relative overflow-hidden rounded-2xl bg-gradient-to-br p-5 text-white shadow-lg',
                tones[tone],
            )}
        >
            <div
                className="pointer-events-none absolute -top-8 -right-8 size-28 rounded-full bg-white/10"
                aria-hidden
            />
            <div
                className="pointer-events-none absolute -right-2 -bottom-10 size-20 rounded-full bg-white/10"
                aria-hidden
            />
            <div className="relative flex items-start justify-between gap-4">
                <p className="text-sm font-medium text-white/85">{label}</p>
                {Icon && (
                    <span className="rounded-lg bg-white/20 p-2 backdrop-blur-sm">
                        <Icon className="size-4" aria-hidden />
                    </span>
                )}
            </div>
            <p className="relative mt-2 text-3xl font-bold tracking-tight tabular-nums">
                {typeof value === 'number' ? value.toLocaleString() : value}
            </p>
            {hint && (
                <p className="relative mt-1 text-xs text-white/80">{hint}</p>
            )}
        </div>
    );
}
