import { Link, useForm } from '@inertiajs/react';
import {
    Bus,
    Clock,
    MapPin,
    Pencil,
    Phone,
    Plus,
    Trash2,
    Users,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { index, show, store, update } from '@/routes/operations/transport';

export type Stop = {
    id: number;
    name: string;
    pickup_time: string | null;
    drop_time: string | null;
};

export type TransportRouteItem = {
    id: number;
    name: string;
    vehicle_no: string | null;
    driver_name: string | null;
    driver_phone: string | null;
    capacity: number;
    is_active: boolean;
    riders?: number;
    stops: Stop[];
};

type Seat = {
    student: string;
    route: string;
    vehicle_no: string | null;
    driver_name: string | null;
    driver_phone: string | null;
    stop: Stop | null;
};

type Props = {
    routes: TransportRouteItem[];
    mine: Seat[];
    can: { create: boolean; edit: boolean; delete: boolean };
};

type StopDraft = {
    id?: number;
    name: string;
    pickup_time: string;
    drop_time: string;
};

export function RouteDialog({
    route,
    onClose,
}: {
    route: TransportRouteItem | null;
    onClose: () => void;
}) {
    const form = useForm({
        name: route?.name ?? '',
        vehicle_no: route?.vehicle_no ?? '',
        driver_name: route?.driver_name ?? '',
        driver_phone: route?.driver_phone ?? '',
        capacity: route?.capacity ?? 30,
        is_active: route?.is_active ?? true,
        stops: (route?.stops ?? []).map((stop): StopDraft => ({
            id: stop.id,
            name: stop.name,
            pickup_time: stop.pickup_time ?? '',
            drop_time: stop.drop_time ?? '',
        })),
    });

    const setStop = (i: number, changes: Partial<StopDraft>): void =>
        form.setData(
            'stops',
            form.data.stops.map((stop, index) =>
                index === i ? { ...stop, ...changes } : stop,
            ),
        );

    const submit = (e: FormEvent): void => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            stops: data.stops.map((stop) => ({
                ...stop,
                pickup_time: stop.pickup_time || null,
                drop_time: stop.drop_time || null,
            })),
        }));
        const options = { preserveScroll: true, onSuccess: onClose };

        if (route) {
            form.put(update.url(route.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    const errors = form.errors as Record<string, string | undefined>;

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogTitle>{route ? 'Edit route' : 'New route'}</DialogTitle>
                <DialogDescription>
                    Stops are listed in the order the vehicle reaches them.
                </DialogDescription>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="route-name"
                        label="Route name"
                        error={form.errors.name}
                        required
                    >
                        <Input
                            id="route-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="route-vehicle"
                        label="Vehicle number"
                        error={form.errors.vehicle_no}
                    >
                        <Input
                            id="route-vehicle"
                            value={form.data.vehicle_no}
                            onChange={(e) =>
                                form.setData('vehicle_no', e.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="route-driver"
                        label="Driver"
                        error={form.errors.driver_name}
                    >
                        <Input
                            id="route-driver"
                            value={form.data.driver_name}
                            onChange={(e) =>
                                form.setData('driver_name', e.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="route-phone"
                        label="Driver phone"
                        error={form.errors.driver_phone}
                    >
                        <Input
                            id="route-phone"
                            type="tel"
                            value={form.data.driver_phone}
                            onChange={(e) =>
                                form.setData('driver_phone', e.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="route-capacity"
                        label="Seats"
                        error={form.errors.capacity}
                        required
                    >
                        <Input
                            id="route-capacity"
                            type="number"
                            min={1}
                            max={200}
                            value={form.data.capacity}
                            onChange={(e) =>
                                form.setData('capacity', Number(e.target.value))
                            }
                            required
                        />
                    </FormField>
                    <div className="flex items-center gap-2 self-end pb-2">
                        <Checkbox
                            id="route-active"
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked === true)
                            }
                        />
                        <Label htmlFor="route-active">Running</Label>
                    </div>
                    <fieldset className="space-y-2 sm:col-span-2">
                        <legend className="mb-2 text-sm font-medium">
                            Stops
                        </legend>
                        {form.data.stops.map((stop, i) => (
                            <div
                                key={stop.id ?? `new-${i}`}
                                className="grid grid-cols-[1fr_7rem_7rem_auto] items-start gap-2"
                            >
                                <div>
                                    <Input
                                        aria-label={`Stop ${i + 1} name`}
                                        value={stop.name}
                                        onChange={(e) =>
                                            setStop(i, { name: e.target.value })
                                        }
                                        placeholder="Stop name"
                                        required
                                    />
                                    {errors[`stops.${i}.name`] && (
                                        <p className="mt-1 text-xs text-destructive">
                                            {errors[`stops.${i}.name`]}
                                        </p>
                                    )}
                                </div>
                                <Input
                                    aria-label={`Stop ${i + 1} pick-up time`}
                                    type="time"
                                    value={stop.pickup_time}
                                    onChange={(e) =>
                                        setStop(i, {
                                            pickup_time: e.target.value,
                                        })
                                    }
                                />
                                <Input
                                    aria-label={`Stop ${i + 1} drop-off time`}
                                    type="time"
                                    value={stop.drop_time}
                                    onChange={(e) =>
                                        setStop(i, {
                                            drop_time: e.target.value,
                                        })
                                    }
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Remove stop ${i + 1}`}
                                    onClick={() =>
                                        form.setData(
                                            'stops',
                                            form.data.stops.filter(
                                                (_, index) => index !== i,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 aria-hidden />
                                </Button>
                            </div>
                        ))}
                        {form.data.stops.length > 0 && (
                            <p className="text-xs text-muted-foreground">
                                Columns: name · pick-up · drop-off
                            </p>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                form.setData('stops', [
                                    ...form.data.stops,
                                    {
                                        name: '',
                                        pickup_time: '',
                                        drop_time: '',
                                    },
                                ])
                            }
                        >
                            <Plus aria-hidden /> Add stop
                        </Button>
                    </fieldset>
                    <div className="flex justify-end sm:col-span-2">
                        <Button type="submit" disabled={form.processing}>
                            Save route
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function MySeats({ mine }: { mine: Seat[] }) {
    if (mine.length === 0) {
        return (
            <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                <Bus className="size-8 text-primary" aria-hidden />
                No school transport is booked.
            </div>
        );
    }

    return (
        <div className="grid gap-4 md:grid-cols-2">
            {mine.map((seat) => (
                <Card key={seat.student} className="overflow-hidden">
                    <div
                        className="h-1.5 bg-gradient-to-r from-amber-500 to-orange-500"
                        aria-hidden
                    />
                    <CardHeader>
                        <CardTitle>{seat.student}</CardTitle>
                        <CardDescription>
                            {seat.route}
                            {seat.vehicle_no && ` · ${seat.vehicle_no}`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        {seat.stop && (
                            <p className="flex items-center gap-2">
                                <MapPin
                                    className="size-4 text-primary"
                                    aria-hidden
                                />{' '}
                                {seat.stop.name}
                            </p>
                        )}
                        {seat.stop?.pickup_time && (
                            <p className="flex items-center gap-2">
                                <Clock
                                    className="size-4 text-primary"
                                    aria-hidden
                                />{' '}
                                Pick-up {seat.stop.pickup_time}
                                {seat.stop.drop_time &&
                                    ` · drop-off ${seat.stop.drop_time}`}
                            </p>
                        )}
                        {seat.driver_name && (
                            <p className="flex items-center gap-2">
                                <Phone
                                    className="size-4 text-primary"
                                    aria-hidden
                                />{' '}
                                {seat.driver_name}
                                {seat.driver_phone && ` · ${seat.driver_phone}`}
                            </p>
                        )}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

export default function Transport({ routes, mine, can }: Props) {
    const [editing, setEditing] = useState<TransportRouteItem | null | 'new'>(
        null,
    );
    const isPortal = routes.length === 0 && mine.length > 0;

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Transport"
                description={
                    isPortal
                        ? 'Your school transport details.'
                        : 'Routes, stops and riders.'
                }
                actions={
                    can.create ? (
                        <Button onClick={() => setEditing('new')}>
                            <Plus aria-hidden /> New route
                        </Button>
                    ) : null
                }
            />
            {mine.length > 0 || (!can.create && routes.length === 0) ? (
                <MySeats mine={mine} />
            ) : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {routes.map((route) => {
                        const full =
                            route.riders !== undefined &&
                            route.riders >= route.capacity;

                        return (
                            <Card
                                key={route.id}
                                className={cn(
                                    'overflow-hidden',
                                    !route.is_active && 'opacity-70',
                                )}
                            >
                                <div
                                    className="h-1.5 bg-gradient-to-r from-amber-500 to-orange-500"
                                    aria-hidden
                                />
                                <CardHeader className="flex flex-row items-start justify-between gap-2">
                                    <div>
                                        <CardTitle className="flex items-center gap-2">
                                            <Bus
                                                className="size-4 text-amber-600"
                                                aria-hidden
                                            />{' '}
                                            {route.name}
                                        </CardTitle>
                                        <CardDescription>
                                            {[
                                                route.vehicle_no,
                                                route.driver_name,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ') ||
                                                'No vehicle details'}
                                        </CardDescription>
                                    </div>
                                    {!route.is_active && (
                                        <TonePill tone="neutral">
                                            Not running
                                        </TonePill>
                                    )}
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div>
                                        <div className="mb-1 flex justify-between text-xs text-muted-foreground">
                                            <span className="flex items-center gap-1">
                                                <Users
                                                    className="size-3"
                                                    aria-hidden
                                                />{' '}
                                                {route.riders} of{' '}
                                                {route.capacity} seats
                                            </span>
                                            {full && (
                                                <span className="font-medium text-rose-600">
                                                    Full
                                                </span>
                                            )}
                                        </div>
                                        <div
                                            className="h-2 rounded-full bg-muted"
                                            aria-hidden
                                        >
                                            <div
                                                className={cn(
                                                    'h-2 rounded-full',
                                                    full
                                                        ? 'bg-rose-500'
                                                        : 'bg-amber-500',
                                                )}
                                                style={{
                                                    width: `${Math.min(100, ((route.riders ?? 0) / route.capacity) * 100)}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                    <ol className="space-y-1 text-sm">
                                        {route.stops.map((stop) => (
                                            <li
                                                key={stop.id}
                                                className="flex justify-between gap-2"
                                            >
                                                <span className="truncate">
                                                    {stop.name}
                                                </span>
                                                <span className="text-muted-foreground tabular-nums">
                                                    {stop.pickup_time ?? ''}
                                                </span>
                                            </li>
                                        ))}
                                        {route.stops.length === 0 && (
                                            <li className="text-muted-foreground">
                                                No stops yet
                                            </li>
                                        )}
                                    </ol>
                                    <div className="flex gap-2 border-t pt-3">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link href={show(route.id)}>
                                                Riders
                                            </Link>
                                        </Button>
                                        {can.edit && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    setEditing(route)
                                                }
                                            >
                                                <Pencil aria-hidden /> Edit
                                            </Button>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                    {routes.length === 0 && (
                        <div className="col-span-full flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                            <Bus className="size-8 text-primary" aria-hidden />
                            No routes yet.
                        </div>
                    )}
                </div>
            )}
            {editing !== null && (
                <RouteDialog
                    route={editing === 'new' ? null : editing}
                    onClose={() => setEditing(null)}
                />
            )}
        </div>
    );
}

Transport.layout = {
    breadcrumbs: [{ title: 'Transport', href: index() }],
};
