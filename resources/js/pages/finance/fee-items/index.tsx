import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import ConfirmDelete from '@/components/confirm-delete';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { useMoney } from '@/hooks/use-money';
import { destroy, index, store, update } from '@/routes/finance/fee-items';
import type { EnumOption, Option, YearOption } from '@/types';

type FeeItem = {
    id: number;
    name: string;
    amount: number;
    frequency: string;
    frequency_label: string;
    school_class_id: number | null;
    school_class: string | null;
    is_active: boolean;
};

type Props = {
    years: YearOption[];
    classes: Option[];
    frequencies: EnumOption[];
    selectedYearId: number | null;
    items: FeeItem[];
    can: { create: boolean; update: boolean; delete: boolean };
};

function FeeDialog({
    item,
    yearId,
    classes,
    frequencies,
    onClose,
}: {
    item: FeeItem | null;
    yearId: number;
    classes: Option[];
    frequencies: EnumOption[];
    onClose: () => void;
}) {
    const money = useMoney();
    const form = useForm({
        academic_year_id: yearId,
        school_class_id: item?.school_class_id ?? ('' as number | ''),
        name: item?.name ?? '',
        amount: item ? money.toMajor(item.amount) : '',
        frequency: item?.frequency ?? 'monthly',
        is_active: item?.is_active ?? true,
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (item) {
            form.put(update.url(item.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle>{item ? 'Edit fee' : 'Add fee'}</DialogTitle>
                <DialogDescription>
                    Amounts are in {money.currency}.
                </DialogDescription>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="fee-name"
                        label="Name"
                        error={form.errors.name}
                        required
                        className="sm:col-span-2"
                    >
                        <Input
                            id="fee-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="Tuition fee"
                            required
                        />
                    </FormField>
                    <FormField
                        id="fee-amount"
                        label="Amount"
                        error={form.errors.amount}
                        required
                    >
                        <Input
                            id="fee-amount"
                            type="number"
                            min={0}
                            step="0.01"
                            value={form.data.amount}
                            onChange={(e) =>
                                form.setData('amount', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="fee-frequency"
                        label="Frequency"
                        error={form.errors.frequency}
                        required
                    >
                        <NativeSelect
                            id="fee-frequency"
                            value={form.data.frequency}
                            onChange={(e) =>
                                form.setData('frequency', e.target.value)
                            }
                        >
                            {frequencies.map((frequency) => (
                                <option
                                    key={frequency.value}
                                    value={frequency.value}
                                >
                                    {frequency.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField
                        id="fee-class"
                        label="Applies to"
                        error={form.errors.school_class_id}
                        className="sm:col-span-2"
                    >
                        <NativeSelect
                            id="fee-class"
                            value={form.data.school_class_id}
                            onChange={(e) =>
                                form.setData(
                                    'school_class_id',
                                    e.target.value === ''
                                        ? ''
                                        : Number(e.target.value),
                                )
                            }
                        >
                            <option value="">Every class</option>
                            {classes.map((schoolClass) => (
                                <option
                                    key={schoolClass.id}
                                    value={schoolClass.id}
                                >
                                    {schoolClass.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="fee-active"
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked === true)
                            }
                        />
                        <Label htmlFor="fee-active">Active</Label>
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            Save fee
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function FeeItems({
    years,
    classes,
    frequencies,
    selectedYearId,
    items,
    can,
}: Props) {
    const money = useMoney();
    const [editing, setEditing] = useState<FeeItem | 'new' | null>(null);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Fee structure"
                description="What each class pays this year. Invoices are generated from these fees."
                actions={
                    can.create &&
                    selectedYearId && (
                        <Button onClick={() => setEditing('new')}>
                            <Plus aria-hidden /> Add fee
                        </Button>
                    )
                }
            />
            <NativeSelect
                aria-label="Academic year"
                value={selectedYearId ?? ''}
                onChange={(e) =>
                    router.get(
                        index.url(),
                        { academic_year_id: e.target.value },
                        { preserveScroll: true },
                    )
                }
                className="sm:w-56"
            >
                {years.map((year) => (
                    <option key={year.id} value={year.id}>
                        {year.name}
                    </option>
                ))}
            </NativeSelect>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {items.map((item, i) => (
                    <div
                        key={item.id}
                        className="relative overflow-hidden rounded-2xl border bg-card p-5 shadow-sm"
                    >
                        <div
                            className={`absolute inset-y-0 left-0 w-1.5 bg-gradient-to-b ${['from-emerald-500 to-teal-500', 'from-navy-500 to-royal-500', 'from-orange-500 to-gold-500', 'from-sky-500 to-cyan-500'][i % 4]}`}
                            aria-hidden
                        />
                        <div className="flex items-start justify-between gap-2">
                            <div>
                                <p className="font-semibold">{item.name}</p>
                                <p className="text-sm text-muted-foreground">
                                    {item.school_class ?? 'Every class'}
                                </p>
                            </div>
                            <div className="flex">
                                {can.update && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Edit ${item.name}`}
                                        onClick={() => setEditing(item)}
                                    >
                                        <Pencil aria-hidden />
                                    </Button>
                                )}
                                {can.delete && (
                                    <ConfirmDelete
                                        action={destroy(item.id)}
                                        title={`Delete ${item.name}?`}
                                        description="Fees that have been invoiced are deactivated instead."
                                    />
                                )}
                            </div>
                        </div>
                        <p className="mt-3 text-2xl font-bold tabular-nums">
                            {money.format(item.amount)}
                        </p>
                        <div className="mt-2 flex gap-2">
                            <TonePill tone="info">
                                {item.frequency_label}
                            </TonePill>
                            {!item.is_active && (
                                <TonePill tone="neutral">Inactive</TonePill>
                            )}
                        </div>
                    </div>
                ))}
                {items.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No fees defined for this year yet.
                    </p>
                )}
            </div>
            {editing && selectedYearId && (
                <FeeDialog
                    key={editing === 'new' ? 'new' : editing.id}
                    item={editing === 'new' ? null : editing}
                    yearId={selectedYearId}
                    classes={classes}
                    frequencies={frequencies}
                    onClose={() => setEditing(null)}
                />
            )}
        </div>
    );
}

FeeItems.layout = {
    breadcrumbs: [{ title: 'Fee structure', href: index() }],
};
