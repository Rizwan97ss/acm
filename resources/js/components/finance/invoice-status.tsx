import { TonePill } from '@/components/status-badge';
import type { StatusTone } from '@/components/status-badge';

const tones: Record<string, StatusTone> = {
    paid: 'success',
    partial: 'info',
    unpaid: 'warning',
    void: 'neutral',
};

export default function InvoiceStatus({
    status,
    label,
    overdue,
}: {
    status: string;
    label: string;
    overdue?: boolean;
}) {
    if (overdue) {
        return <TonePill tone="danger">Overdue</TonePill>;
    }

    return <TonePill tone={tones[status] ?? 'neutral'}>{label}</TonePill>;
}
