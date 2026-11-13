import type { LucideIcon } from 'lucide-react';

type StatCardProps = {
    label: string;
    value: number | string;
    hint?: string | null;
    icon?: LucideIcon;
};

export default function StatCard({
    label,
    value,
    hint,
    icon: Icon,
}: StatCardProps) {
    return (
        <div className="rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
            <div className="flex items-start justify-between gap-4">
                <p className="text-sm text-muted-foreground">{label}</p>
                {Icon && (
                    <span className="rounded-md bg-primary/10 p-1.5 text-primary">
                        <Icon className="size-4" aria-hidden />
                    </span>
                )}
            </div>
            <p className="mt-2 text-3xl font-semibold tracking-tight tabular-nums">
                {typeof value === 'number' ? value.toLocaleString() : value}
            </p>
            {hint && (
                <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
            )}
        </div>
    );
}
