import type { StatusTone } from '@/components/status-badge';

export const leaveTones: Record<string, StatusTone> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
    cancelled: 'neutral',
};

export const payrollTones: Record<string, StatusTone> = {
    draft: 'warning',
    approved: 'info',
    paid: 'success',
};
