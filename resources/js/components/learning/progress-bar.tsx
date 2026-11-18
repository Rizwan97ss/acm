import { cn } from '@/lib/utils';

export default function ProgressBar({
    percent,
    label,
    className,
}: {
    percent: number;
    label?: string;
    className?: string;
}) {
    const value = Math.max(0, Math.min(100, Math.round(percent)));

    return (
        <div className={cn('space-y-1', className)}>
            {label && (
                <div className="flex justify-between text-xs text-muted-foreground">
                    <span>{label}</span>
                    <span className="font-medium text-foreground tabular-nums">
                        {value}%
                    </span>
                </div>
            )}
            <div
                className="h-2 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                aria-valuenow={value}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-label={label ?? 'Progress'}
            >
                <div
                    className={cn(
                        'h-full rounded-full transition-all',
                        value >= 100
                            ? 'bg-emerald-500'
                            : 'bg-gradient-to-r from-navy-600 to-royal-400',
                    )}
                    style={{ width: `${value}%` }}
                />
            </div>
        </div>
    );
}
