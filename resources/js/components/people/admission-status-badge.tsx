import {
    CheckCircle2,
    CircleDashed,
    GraduationCap,
    XCircle,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { AdmissionApplication } from '@/types';

const icons = {
    pending: CircleDashed,
    approved: CheckCircle2,
    rejected: XCircle,
    enrolled: GraduationCap,
} as const;

/**
 * Status shown as icon + label, never colour alone.
 */
export default function AdmissionStatusBadge({
    status,
    label,
}: {
    status: AdmissionApplication['status'];
    label: string;
}) {
    const Icon = icons[status];

    return (
        <Badge
            variant={status === 'rejected' ? 'outline' : 'secondary'}
            className="gap-1"
        >
            <Icon aria-hidden />
            {label}
        </Badge>
    );
}
