import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { useFormat } from '@/hooks/use-format';
import { leaveTones } from '@/lib/status-tones';
import { cancel, mine, store } from '@/routes/hr/leave';
import type { LeaveRow } from '@/types';

type Balance = {
    id: number;
    name: string;
    is_paid: boolean;
    allowance: number;
    used: number;
    pending: number;
    remaining: number;
};

const ringColors = [
    'from-emerald-500 to-teal-500',
    'from-sky-500 to-indigo-500',
    'from-orange-500 to-pink-500',
    'from-violet-500 to-fuchsia-500',
];

export default function MyLeave({
    employee,
    balances,
    requests,
    canRequest,
}: {
    employee: { id: number } | null;
    balances: Balance[];
    requests: LeaveRow[];
    canRequest: boolean;
}) {
    const format = useFormat();
    const day = (value: string): string => format.date(`${value}T12:00:00Z`);
    const form = useForm({
        leave_type_id: balances[0]?.id ?? '',
        starts_on: '',
        ends_on: '',
        reason: '',
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset('starts_on', 'ends_on', 'reason'),
        });
    };

    if (employee === null) {
        return (
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="My leave" />
                <Card>
                    <CardHeader>
                        <CardTitle>No staff record</CardTitle>
                        <CardDescription>
                            Ask HR to link your account to your staff record to
                            apply for leave.
                        </CardDescription>
                    </CardHeader>
                </Card>
            </div>
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="My leave"
                description={`Your allowances for ${new Date().getFullYear()}.`}
            />
            <section
                aria-label="Balances"
                className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            >
                {balances.map((balance, i) => (
                    <div
                        key={balance.id}
                        className={`rounded-2xl bg-gradient-to-br p-5 text-white shadow-lg ${ringColors[i % ringColors.length]}`}
                    >
                        <p className="text-sm text-white/85">{balance.name}</p>
                        <p className="mt-1 text-3xl font-bold tabular-nums">
                            {balance.remaining}
                            <span className="text-base font-medium text-white/80">
                                {' '}
                                / {balance.allowance} days left
                            </span>
                        </p>
                        <p className="mt-1 text-xs text-white/85">
                            {balance.used} taken · {balance.pending} pending
                            {balance.is_paid ? '' : ' · unpaid'}
                        </p>
                    </div>
                ))}
            </section>
            <div className="grid gap-4 lg:grid-cols-5">
                {canRequest && (
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Apply for leave</CardTitle>
                            <CardDescription>
                                Days are counted on school days only.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-4">
                                <FormField
                                    id="leave-type"
                                    label="Type"
                                    error={form.errors.leave_type_id}
                                    required
                                >
                                    <NativeSelect
                                        id="leave-type"
                                        value={form.data.leave_type_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'leave_type_id',
                                                Number(e.target.value),
                                            )
                                        }
                                    >
                                        {balances.map((balance) => (
                                            <option
                                                key={balance.id}
                                                value={balance.id}
                                            >
                                                {balance.name} (
                                                {balance.remaining} left)
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        id="leave-from"
                                        label="From"
                                        error={form.errors.starts_on}
                                        required
                                    >
                                        <Input
                                            id="leave-from"
                                            type="date"
                                            value={form.data.starts_on}
                                            onChange={(e) =>
                                                form.setData(
                                                    'starts_on',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        id="leave-to"
                                        label="To"
                                        error={form.errors.ends_on}
                                        required
                                    >
                                        <Input
                                            id="leave-to"
                                            type="date"
                                            value={form.data.ends_on}
                                            onChange={(e) =>
                                                form.setData(
                                                    'ends_on',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                        />
                                    </FormField>
                                </div>
                                <FormField
                                    id="leave-reason"
                                    label="Reason"
                                    error={form.errors.reason}
                                    required
                                >
                                    <Textarea
                                        id="leave-reason"
                                        value={form.data.reason}
                                        onChange={(e) =>
                                            form.setData(
                                                'reason',
                                                e.target.value,
                                            )
                                        }
                                        rows={3}
                                        required
                                    />
                                </FormField>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    className="bg-gradient-to-r from-indigo-600 to-violet-600 text-white"
                                >
                                    Submit request
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}
                <Card
                    className={canRequest ? 'lg:col-span-3' : 'lg:col-span-5'}
                >
                    <CardHeader>
                        <CardTitle>My requests</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="divide-y">
                            {requests.map((leave) => (
                                <li
                                    key={leave.id}
                                    className="flex flex-wrap items-center justify-between gap-2 py-3 text-sm"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {leave.type} · {leave.days}{' '}
                                            {leave.days === 1 ? 'day' : 'days'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {day(leave.starts_on)} –{' '}
                                            {day(leave.ends_on)}
                                            {leave.review_note
                                                ? ` · “${leave.review_note}”`
                                                : ''}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <TonePill
                                            tone={
                                                leaveTones[leave.status] ??
                                                'neutral'
                                            }
                                        >
                                            {leave.status_label}
                                        </TonePill>
                                        {leave.cancellable && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    router.post(
                                                        cancel.url(leave.id),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Cancel
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            ))}
                            {requests.length === 0 && (
                                <li className="py-6 text-sm text-muted-foreground">
                                    No leave requests yet.
                                </li>
                            )}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

MyLeave.layout = {
    breadcrumbs: [{ title: 'My leave', href: mine() }],
};
