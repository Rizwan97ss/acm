import { router, useForm } from '@inertiajs/react';
import { Check, Plus, X } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFormat } from '@/hooks/use-format';
import { leaveTones } from '@/lib/status-tones';
import { cn } from '@/lib/utils';
import { requests as requestsRoute, review } from '@/routes/hr/leave';
import {
    store as storeType,
    update as updateType,
} from '@/routes/hr/leave/types';
import type { EnumOption, LeaveRow } from '@/types';

type LeaveType = {
    id: number;
    name: string;
    days_per_year: number;
    is_paid: boolean;
    is_active: boolean;
};

type Props = {
    requests: (LeaveRow & {
        employee: string;
        designation: string;
        own: boolean;
    })[];
    status: string;
    statuses: EnumOption[];
    types: LeaveType[];
};

function TypeDialog({
    type,
    onClose,
}: {
    type: LeaveType | null;
    onClose: () => void;
}) {
    const form = useForm({
        name: type?.name ?? '',
        days_per_year: type?.days_per_year ?? 10,
        is_paid: type?.is_paid ?? true,
        is_active: type?.is_active ?? true,
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (type) {
            form.put(updateType.url(type.id), options);
        } else {
            form.post(storeType.url(), options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle>
                    {type ? 'Edit leave type' : 'Add leave type'}
                </DialogTitle>
                <DialogDescription>
                    Allowance per calendar year.
                </DialogDescription>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="lt-name"
                        label="Name"
                        error={form.errors.name}
                        required
                    >
                        <Input
                            id="lt-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="lt-days"
                        label="Days per year"
                        error={form.errors.days_per_year}
                        required
                    >
                        <Input
                            id="lt-days"
                            type="number"
                            min={0}
                            max={366}
                            value={form.data.days_per_year}
                            onChange={(e) =>
                                form.setData(
                                    'days_per_year',
                                    Number(e.target.value),
                                )
                            }
                            required
                        />
                    </FormField>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="lt-paid"
                            checked={form.data.is_paid}
                            onCheckedChange={(checked) =>
                                form.setData('is_paid', checked === true)
                            }
                        />
                        <Label htmlFor="lt-paid">Paid leave</Label>
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="lt-active"
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked === true)
                            }
                        />
                        <Label htmlFor="lt-active">Active</Label>
                    </div>
                    <div className="flex justify-end sm:col-span-2">
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function LeaveRequests({
    requests,
    status,
    statuses,
    types,
}: Props) {
    const format = useFormat();
    const day = (value: string): string => format.date(`${value}T12:00:00Z`);
    const [editing, setEditing] = useState<LeaveType | 'new' | null>(null);

    const decide = (id: number, decision: 'approved' | 'rejected'): void => {
        const note =
            decision === 'rejected'
                ? window.prompt('Reason for rejecting (optional)')
                : null;
        router.post(
            review.url(id),
            { decision, note },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Leave requests"
                description="Review staff leave. Unpaid leave is deducted automatically in payroll."
            />
            <nav aria-label="Status" className="flex gap-1 overflow-x-auto">
                {statuses.map((option) => (
                    <button
                        key={option.value}
                        type="button"
                        aria-pressed={status === option.value}
                        onClick={() =>
                            router.get(
                                requestsRoute.url(),
                                { status: option.value },
                                { preserveScroll: true, replace: true },
                            )
                        }
                        className={cn(
                            'rounded-lg px-3 py-1.5 text-sm hover:bg-muted',
                            status === option.value && 'bg-muted font-medium',
                        )}
                    >
                        {option.label}
                    </button>
                ))}
            </nav>
            <div className="grid gap-4 lg:grid-cols-3">
                <ul className="space-y-3 lg:col-span-2">
                    {requests.map((leave) => (
                        <li
                            key={leave.id}
                            className="rounded-2xl border bg-card p-4 shadow-sm"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p className="font-semibold">
                                        {leave.employee}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {leave.designation}
                                    </p>
                                </div>
                                <TonePill
                                    tone={leaveTones[leave.status] ?? 'neutral'}
                                >
                                    {leave.status_label}
                                </TonePill>
                            </div>
                            <p className="mt-2 text-sm">
                                <span className="font-medium">
                                    {leave.type}
                                </span>{' '}
                                · {day(leave.starts_on)} – {day(leave.ends_on)}{' '}
                                · {leave.days}{' '}
                                {leave.days === 1 ? 'day' : 'days'}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {leave.reason}
                            </p>
                            {leave.status === 'pending' && !leave.own && (
                                <div className="mt-3 flex gap-2">
                                    <Button
                                        size="sm"
                                        className="bg-emerald-600 text-white hover:bg-emerald-700"
                                        onClick={() =>
                                            decide(leave.id, 'approved')
                                        }
                                    >
                                        <Check aria-hidden /> Approve
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            decide(leave.id, 'rejected')
                                        }
                                    >
                                        <X aria-hidden /> Reject
                                    </Button>
                                </div>
                            )}
                            {leave.own && leave.status === 'pending' && (
                                <p className="mt-2 text-xs text-muted-foreground">
                                    Your own request — someone else must review
                                    it.
                                </p>
                            )}
                        </li>
                    ))}
                    {requests.length === 0 && (
                        <li className="rounded-2xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                            Nothing here.
                        </li>
                    )}
                </ul>
                <Card className="h-fit">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Leave types</CardTitle>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setEditing('new')}
                        >
                            <Plus aria-hidden /> Add
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <ul className="space-y-2 text-sm">
                            {types.map((type) => (
                                <li key={type.id}>
                                    <button
                                        type="button"
                                        onClick={() => setEditing(type)}
                                        className="flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left hover:bg-muted"
                                    >
                                        <span
                                            className={cn(
                                                !type.is_active &&
                                                    'text-muted-foreground line-through',
                                            )}
                                        >
                                            {type.name}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {type.days_per_year} days ·{' '}
                                            {type.is_paid ? 'paid' : 'unpaid'}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </div>
            {editing && (
                <TypeDialog
                    key={editing === 'new' ? 'new' : editing.id}
                    type={editing === 'new' ? null : editing}
                    onClose={() => setEditing(null)}
                />
            )}
        </div>
    );
}

LeaveRequests.layout = {
    breadcrumbs: [{ title: 'Leave requests', href: requestsRoute() }],
};
