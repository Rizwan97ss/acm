import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

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
        <Badge
            variant="outline"
            className={cn(
                'gap-1.5',
                active ? 'text-foreground' : 'text-muted-foreground',
            )}
        >
            <span
                className={cn(
                    'size-1.5 rounded-full',
                    active ? 'bg-emerald-500' : 'bg-muted-foreground/50',
                )}
                aria-hidden
            />
            {active ? activeLabel : inactiveLabel}
        </Badge>
    );
}
