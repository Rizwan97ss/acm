import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/operations/hostels';

export type HostelValues = {
    id: number;
    name: string;
    type: string;
    warden_id: number | null;
    phone: string | null;
    address: string | null;
    monthly_fee: number;
    is_active: boolean;
};

export default function HostelForm({
    hostel,
    types,
    wardens,
    onDone,
}: {
    hostel: HostelValues | null;
    types: { value: string; label: string }[];
    wardens: { id: number; name: string }[];
    onDone: () => void;
}) {
    const form = useForm({
        name: hostel?.name ?? '',
        type: hostel?.type ?? 'boys',
        warden_id: hostel?.warden_id ?? ('' as number | ''),
        phone: hostel?.phone ?? '',
        address: hostel?.address ?? '',
        monthly_fee: hostel?.monthly_fee ?? 0,
        is_active: hostel?.is_active ?? true,
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            warden_id: data.warden_id === '' ? null : data.warden_id,
        }));

        if (hostel) {
            form.put(update.url(hostel.id), {
                preserveScroll: true,
                onSuccess: onDone,
            });
        } else {
            form.post(store.url());
        }
    };

    return (
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
            <FormField
                id="h-name"
                label="Name"
                required
                error={form.errors.name}
            >
                <Input
                    id="h-name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    placeholder="e.g. Iqbal House"
                />
            </FormField>
            <FormField
                id="h-type"
                label="For"
                required
                error={form.errors.type}
            >
                <NativeSelect
                    id="h-type"
                    value={form.data.type}
                    onChange={(event) =>
                        form.setData('type', event.target.value)
                    }
                >
                    {types.map((type) => (
                        <option key={type.value} value={type.value}>
                            {type.label}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <FormField
                id="h-warden"
                label="Warden"
                error={form.errors.warden_id}
            >
                <NativeSelect
                    id="h-warden"
                    value={form.data.warden_id}
                    onChange={(event) =>
                        form.setData(
                            'warden_id',
                            event.target.value === ''
                                ? ''
                                : Number(event.target.value),
                        )
                    }
                >
                    <option value="">Not assigned</option>
                    {wardens.map((warden) => (
                        <option key={warden.id} value={warden.id}>
                            {warden.name}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <FormField
                id="h-fee"
                label="Monthly fee"
                required
                error={form.errors.monthly_fee}
            >
                <Input
                    id="h-fee"
                    type="number"
                    min={0}
                    step="0.01"
                    value={form.data.monthly_fee}
                    onChange={(event) =>
                        form.setData('monthly_fee', Number(event.target.value))
                    }
                />
            </FormField>
            <FormField id="h-phone" label="Phone" error={form.errors.phone}>
                <Input
                    id="h-phone"
                    value={form.data.phone}
                    onChange={(event) =>
                        form.setData('phone', event.target.value)
                    }
                />
            </FormField>
            <div className="flex items-center gap-2 self-end pb-2">
                <Checkbox
                    id="h-active"
                    checked={form.data.is_active}
                    onCheckedChange={(checked) =>
                        form.setData('is_active', checked === true)
                    }
                />
                <Label htmlFor="h-active" className="font-normal">
                    Open for new residents
                </Label>
            </div>
            <FormField
                id="h-address"
                label="Address or block"
                error={form.errors.address}
                className="sm:col-span-2"
            >
                <Textarea
                    id="h-address"
                    rows={2}
                    value={form.data.address}
                    onChange={(event) =>
                        form.setData('address', event.target.value)
                    }
                />
            </FormField>
            <div className="flex gap-2 sm:col-span-2">
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    {hostel ? 'Save hostel' : 'Add hostel'}
                </Button>
                <Button type="button" variant="ghost" onClick={onDone}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}
