import { router, useForm } from '@inertiajs/react';
import {
    ArrowRightLeft,
    BedDouble,
    LogOut,
    Pencil,
    Plus,
    Receipt,
    Trash2,
    UserPlus,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import FormField from '@/components/form-field';
import type { HostelValues } from '@/components/operations/hostel-form';
import HostelForm from '@/components/operations/hostel-form';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useFormat } from '@/hooks/use-format';
import { useMoney } from '@/hooks/use-money';
import { cn } from '@/lib/utils';
import {
    destroy as destroyRoom,
    allocate,
} from '@/routes/operations/hostel-rooms';
import { move, vacate } from '@/routes/operations/hostel-residents';
import { bill, destroy, index } from '@/routes/operations/hostels';
import { store as storeRoom } from '@/routes/operations/hostels/rooms';

type Resident = {
    id: number;
    student: string;
    admission_no: string;
    bed_label: string | null;
    starts_on: string;
};
type Room = {
    id: number;
    name: string;
    floor: string | null;
    capacity: number;
    notes: string | null;
    residents: Resident[];
};

type Props = {
    hostel: HostelValues & { type_label: string; warden: string | null };
    rooms: Room[];
    types: { value: string; label: string }[];
    wardens: { id: number; name: string }[];
    can: { edit: boolean; delete: boolean; bill: boolean };
};

function AllocateForm({ room }: { room: Room }) {
    const form = useForm({ admission_no: '', bed_label: '' });
    const errors = form.errors as Partial<Record<string, string>>;
    const problem =
        errors.admission_no ??
        errors.student_id ??
        errors.hostel_room_id ??
        errors.bed_label;

    return (
        <form
            onSubmit={(event: FormEvent) => {
                event.preventDefault();
                form.post(allocate.url(room.id), {
                    preserveScroll: true,
                    onSuccess: () => form.reset(),
                });
            }}
            className="grid gap-2 border-t p-3 sm:grid-cols-[1fr_5rem_auto]"
        >
            <Input
                aria-label={`Admission number for room ${room.name}`}
                placeholder="Admission no."
                value={form.data.admission_no}
                onChange={(event) =>
                    form.setData('admission_no', event.target.value)
                }
                className="h-9"
            />
            <Input
                aria-label="Bed"
                placeholder="Bed"
                value={form.data.bed_label}
                onChange={(event) =>
                    form.setData('bed_label', event.target.value)
                }
                className="h-9"
            />
            <Button
                type="submit"
                size="sm"
                variant="outline"
                disabled={
                    form.processing || form.data.admission_no.trim() === ''
                }
            >
                <UserPlus aria-hidden /> Add
            </Button>
            {problem && (
                <p className="text-xs text-destructive sm:col-span-3">
                    {problem}
                </p>
            )}
        </form>
    );
}

export default function HostelShow({
    hostel,
    rooms,
    types,
    wardens,
    can,
}: Props) {
    const { date } = useFormat();
    const money = useMoney();
    const [editing, setEditing] = useState(false);
    const [moving, setMoving] = useState<Resident | null>(null);
    const roomForm = useForm({ name: '', floor: '', capacity: 4 });
    const billForm = useForm({ month: new Date().toISOString().slice(0, 7) });
    const moveForm = useForm({
        hostel_room_id: '' as number | '',
        bed_label: '',
    });
    const beds = rooms.reduce((sum, room) => sum + room.capacity, 0);
    const occupied = rooms.reduce(
        (sum, room) => sum + room.residents.length,
        0,
    );

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={hostel.name}
                description={`${hostel.type_label} hostel · ${hostel.warden ? `Warden: ${hostel.warden}` : 'No warden'} · ${money.format(Math.round(hostel.monthly_fee * 100))} per month`}
                actions={
                    <>
                        {can.edit && (
                            <Button
                                variant="outline"
                                onClick={() => setEditing(true)}
                            >
                                <Pencil aria-hidden /> Edit
                            </Button>
                        )}
                        {can.delete && (
                            <Button
                                variant="ghost"
                                className="text-destructive"
                                onClick={() =>
                                    window.confirm(`Delete ${hostel.name}?`) &&
                                    router.delete(destroy.url(hostel.id))
                                }
                            >
                                <Trash2 aria-hidden /> Delete
                            </Button>
                        )}
                    </>
                }
            />

            <div className="grid gap-4 md:grid-cols-[1fr_auto]">
                <div className="flex flex-wrap items-center gap-2 rounded-2xl border bg-card p-4 text-sm shadow-sm">
                    <TonePill tone="info">{rooms.length} rooms</TonePill>
                    <TonePill
                        tone={
                            occupied >= beds && beds > 0 ? 'warning' : 'success'
                        }
                    >
                        {occupied} / {beds} beds occupied
                    </TonePill>
                    {!hostel.is_active && (
                        <TonePill tone="neutral">
                            Closed to new residents
                        </TonePill>
                    )}
                    {hostel.address && (
                        <span className="text-muted-foreground">
                            {hostel.address}
                        </span>
                    )}
                </div>
                {can.bill && (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            billForm.post(bill.url(hostel.id), {
                                preserveScroll: true,
                            });
                        }}
                        className="flex items-end gap-2 rounded-2xl border bg-card p-4 shadow-sm"
                    >
                        <FormField
                            id="bill-month"
                            label="Bill hostel fees for"
                            error={billForm.errors.month}
                        >
                            <Input
                                id="bill-month"
                                type="month"
                                value={billForm.data.month}
                                onChange={(event) =>
                                    billForm.setData(
                                        'month',
                                        event.target.value,
                                    )
                                }
                                className="h-9"
                            />
                        </FormField>
                        <Button type="submit" disabled={billForm.processing}>
                            <Receipt aria-hidden /> Bill residents
                        </Button>
                    </form>
                )}
            </div>

            <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {rooms.map((room) => {
                    const free = room.capacity - room.residents.length;

                    return (
                        <li
                            key={room.id}
                            className="flex flex-col overflow-hidden rounded-2xl border bg-card shadow-sm"
                        >
                            <header className="flex items-center justify-between gap-2 border-b bg-muted/40 px-4 py-3">
                                <div>
                                    <h2 className="font-semibold">
                                        Room {room.name}
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        {room.floor ? `${room.floor} · ` : ''}
                                        {room.capacity} beds
                                    </p>
                                </div>
                                <div className="flex items-center gap-1">
                                    <TonePill
                                        tone={
                                            free === 0 ? 'warning' : 'success'
                                        }
                                    >
                                        {free === 0 ? 'Full' : `${free} free`}
                                    </TonePill>
                                    {can.edit &&
                                        room.residents.length === 0 && (
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                className="size-8 text-destructive"
                                                aria-label={`Delete room ${room.name}`}
                                                onClick={() =>
                                                    router.delete(
                                                        destroyRoom.url(
                                                            room.id,
                                                        ),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <Trash2 aria-hidden />
                                            </Button>
                                        )}
                                </div>
                            </header>
                            <div className="flex gap-1 px-4 pt-3" aria-hidden>
                                {Array.from(
                                    { length: room.capacity },
                                    (_, bed) => (
                                        <BedDouble
                                            key={bed}
                                            className={cn(
                                                'size-5',
                                                bed < room.residents.length
                                                    ? 'text-navy-700 dark:text-navy-200'
                                                    : 'text-muted-foreground/40',
                                            )}
                                        />
                                    ),
                                )}
                            </div>
                            <ul className="flex-1 divide-y px-4 py-2">
                                {room.residents.length === 0 && (
                                    <li className="py-2 text-sm text-muted-foreground">
                                        Empty
                                    </li>
                                )}
                                {room.residents.map((resident) => (
                                    <li
                                        key={resident.id}
                                        className="flex items-center gap-2 py-2 text-sm"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-medium">
                                                {resident.student}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {resident.admission_no}
                                                {resident.bed_label &&
                                                    ` · bed ${resident.bed_label}`}{' '}
                                                · since{' '}
                                                {date(resident.starts_on)}
                                            </p>
                                        </div>
                                        {can.edit && (
                                            <>
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    className="size-8"
                                                    aria-label={`Move ${resident.student}`}
                                                    onClick={() =>
                                                        setMoving(resident)
                                                    }
                                                >
                                                    <ArrowRightLeft
                                                        aria-hidden
                                                    />
                                                </Button>
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    className="size-8"
                                                    aria-label={`Vacate ${resident.student}`}
                                                    onClick={() =>
                                                        window.confirm(
                                                            `${resident.student} leaves the hostel today?`,
                                                        ) &&
                                                        router.delete(
                                                            vacate.url(
                                                                resident.id,
                                                            ),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <LogOut aria-hidden />
                                                </Button>
                                            </>
                                        )}
                                    </li>
                                ))}
                            </ul>
                            {can.edit && free > 0 && hostel.is_active && (
                                <AllocateForm room={room} />
                            )}
                        </li>
                    );
                })}
            </ul>

            {can.edit && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        roomForm.post(storeRoom.url(hostel.id), {
                            preserveScroll: true,
                            onSuccess: () => roomForm.reset(),
                        });
                    }}
                    className="grid max-w-2xl gap-3 rounded-2xl border bg-card p-4 shadow-sm sm:grid-cols-[1fr_1fr_6rem_auto] sm:items-end"
                >
                    <FormField
                        id="room-name"
                        label="Room"
                        error={roomForm.errors.name}
                    >
                        <Input
                            id="room-name"
                            value={roomForm.data.name}
                            onChange={(event) =>
                                roomForm.setData('name', event.target.value)
                            }
                            placeholder="e.g. 101"
                        />
                    </FormField>
                    <FormField
                        id="room-floor"
                        label="Floor"
                        error={roomForm.errors.floor}
                    >
                        <Input
                            id="room-floor"
                            value={roomForm.data.floor}
                            onChange={(event) =>
                                roomForm.setData('floor', event.target.value)
                            }
                            placeholder="Ground"
                        />
                    </FormField>
                    <FormField
                        id="room-capacity"
                        label="Beds"
                        error={roomForm.errors.capacity}
                    >
                        <Input
                            id="room-capacity"
                            type="number"
                            min={1}
                            max={30}
                            value={roomForm.data.capacity}
                            onChange={(event) =>
                                roomForm.setData(
                                    'capacity',
                                    Number(event.target.value),
                                )
                            }
                        />
                    </FormField>
                    <Button
                        type="submit"
                        disabled={
                            roomForm.processing ||
                            roomForm.data.name.trim() === ''
                        }
                    >
                        <Plus aria-hidden /> Add room
                    </Button>
                </form>
            )}

            <Dialog open={editing} onOpenChange={setEditing}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogTitle>Edit {hostel.name}</DialogTitle>
                    <DialogDescription>
                        Changes apply straight away. The fee is used for the
                        next billing run.
                    </DialogDescription>
                    <HostelForm
                        hostel={hostel}
                        types={types}
                        wardens={wardens}
                        onDone={() => setEditing(false)}
                    />
                </DialogContent>
            </Dialog>

            <Dialog
                open={moving !== null}
                onOpenChange={(open) => !open && setMoving(null)}
            >
                <DialogContent>
                    <DialogTitle>Move {moving?.student}</DialogTitle>
                    <DialogDescription>
                        Their current stay ends today and a new one starts in
                        the chosen room.
                    </DialogDescription>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();

                            if (moving) {
                                moveForm.put(move.url(moving.id), {
                                    preserveScroll: true,
                                    onSuccess: () => setMoving(null),
                                });
                            }
                        }}
                        className="grid gap-3"
                    >
                        <FormField
                            id="move-room"
                            label="New room"
                            error={moveForm.errors.hostel_room_id}
                        >
                            <NativeSelect
                                id="move-room"
                                value={moveForm.data.hostel_room_id}
                                onChange={(event) =>
                                    moveForm.setData(
                                        'hostel_room_id',
                                        event.target.value === ''
                                            ? ''
                                            : Number(event.target.value),
                                    )
                                }
                            >
                                <option value="">Choose…</option>
                                {rooms
                                    .filter(
                                        (room) =>
                                            room.residents.length <
                                            room.capacity,
                                    )
                                    .map((room) => (
                                        <option key={room.id} value={room.id}>
                                            Room {room.name} (
                                            {room.capacity -
                                                room.residents.length}{' '}
                                            free)
                                        </option>
                                    ))}
                            </NativeSelect>
                        </FormField>
                        <FormField
                            id="move-bed"
                            label="Bed (optional)"
                            error={moveForm.errors.bed_label}
                        >
                            <Input
                                id="move-bed"
                                value={moveForm.data.bed_label}
                                onChange={(event) =>
                                    moveForm.setData(
                                        'bed_label',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <Button
                            type="submit"
                            disabled={
                                moveForm.processing ||
                                moveForm.data.hostel_room_id === ''
                            }
                        >
                            Move
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

HostelShow.layout = {
    breadcrumbs: [{ title: 'Hostels', href: index() }],
};
