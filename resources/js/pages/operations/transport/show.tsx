import { router, useForm } from '@inertiajs/react';
import { UserMinus, UserPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import ConfirmDelete from '@/components/confirm-delete';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
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
import {
    assign,
    destroy,
    index,
    unassign,
} from '@/routes/operations/transport';
import type { TransportRouteItem } from './index';

type Rider = {
    id: number;
    student: string;
    admission_no: string;
    class: string | null;
    stop: string | null;
};

type Props = {
    route: TransportRouteItem;
    riders: Rider[];
    can: { edit: boolean };
};

export default function TransportRouteShow({ route, riders, can }: Props) {
    const form = useForm({
        admission_no: '',
        transport_stop_id: '' as number | '',
    });

    const submit = (e: FormEvent): void => {
        e.preventDefault();
        form.post(assign.url(route.id), {
            preserveScroll: true,
            onSuccess: () => form.reset('admission_no'),
        });
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={route.name}
                description={`${riders.length} of ${route.capacity} seats taken${route.vehicle_no ? ` · ${route.vehicle_no}` : ''}`}
                actions={
                    can.edit && riders.length === 0 ? (
                        <ConfirmDelete
                            action={destroy(route.id)}
                            title="Delete route?"
                            description="The route and its stops will be removed."
                            label="Delete route"
                        />
                    ) : null
                }
            />
            {can.edit && (
                <Card>
                    <CardHeader>
                        <CardTitle>Add a rider</CardTitle>
                        <CardDescription>
                            A student on another route is moved to this one.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
                        >
                            <FormField
                                id="rider-admission"
                                label="Admission number"
                                error={
                                    form.errors.admission_no ??
                                    (form.errors as Record<string, string>)
                                        .student
                                }
                                required
                            >
                                <Input
                                    id="rider-admission"
                                    value={form.data.admission_no}
                                    onChange={(e) =>
                                        form.setData(
                                            'admission_no',
                                            e.target.value,
                                        )
                                    }
                                    autoComplete="off"
                                    required
                                />
                            </FormField>
                            <FormField
                                id="rider-stop"
                                label="Stop"
                                error={form.errors.transport_stop_id}
                            >
                                <NativeSelect
                                    id="rider-stop"
                                    value={form.data.transport_stop_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'transport_stop_id',
                                            e.target.value === ''
                                                ? ''
                                                : Number(e.target.value),
                                        )
                                    }
                                >
                                    <option value="">No stop yet</option>
                                    {route.stops.map((stop) => (
                                        <option key={stop.id} value={stop.id}>
                                            {stop.name}
                                            {stop.pickup_time
                                                ? ` (${stop.pickup_time})`
                                                : ''}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                            <Button type="submit" disabled={form.processing}>
                                <UserPlus aria-hidden /> Add
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            )}
            <Card className="overflow-x-auto p-0">
                <table className="w-full text-sm">
                    <caption className="sr-only">Riders</caption>
                    <thead className="border-b text-left text-xs text-muted-foreground uppercase">
                        <tr>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Student
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Class
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Stop
                            </th>
                            {can.edit && (
                                <th scope="col" className="px-4 py-3">
                                    <span className="sr-only">Actions</span>
                                </th>
                            )}
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {riders.map((rider) => (
                            <tr key={rider.id}>
                                <td className="px-4 py-3">
                                    <p className="font-medium">
                                        {rider.student}
                                    </p>
                                    <p className="font-mono text-xs text-muted-foreground">
                                        {rider.admission_no}
                                    </p>
                                </td>
                                <td className="px-4 py-3">
                                    {rider.class ?? '—'}
                                </td>
                                <td className="px-4 py-3">
                                    {rider.stop ?? '—'}
                                </td>
                                {can.edit && (
                                    <td className="px-4 py-3 text-right">
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                router.delete(
                                                    unassign.url(rider.id),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <UserMinus aria-hidden /> Remove
                                        </Button>
                                    </td>
                                )}
                            </tr>
                        ))}
                        {riders.length === 0 && (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="px-4 py-8 text-center text-muted-foreground"
                                >
                                    No riders yet.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </Card>
        </div>
    );
}

TransportRouteShow.layout = {
    breadcrumbs: [{ title: 'Transport', href: index() }],
};
