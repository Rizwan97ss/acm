import { Link, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    MapPin,
    Plus,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import AudienceFields from '@/components/audience-fields';
import ConfirmDelete from '@/components/confirm-delete';
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
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { destroy, index, store, update } from '@/routes/communication/events';
import type { Option } from '@/types';
import type { SchoolEventItem } from '@/types/communication';
import type { EnumOption } from '@/types/people';

type Props = {
    month: string;
    events: SchoolEventItem[];
    upcoming: SchoolEventItem[];
    audiences: EnumOption[];
    classes: Option[];
    can: { create: boolean; edit: boolean; delete: boolean };
};

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const chip: Record<string, string> = {
    everyone:
        'bg-royal-100 text-royal-800 dark:bg-royal-500/20 dark:text-royal-200',
    staff: 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200',
    students:
        'bg-amber-100 text-amber-900 dark:bg-amber-500/20 dark:text-amber-200',
    guardians:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200',
};

/** The server sends ISO strings in the school's time zone; dates are read as-is. */
const day = (iso: string): string => iso.slice(0, 10);
const time = (iso: string): string => iso.slice(11, 16);
const shiftMonth = (month: string, by: number): string => {
    const [year, m] = month.split('-').map(Number);
    const date = new Date(Date.UTC(year, m - 1 + by, 1));

    return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}`;
};
const monthLabel = (month: string): string =>
    new Date(`${month}-01T12:00:00Z`).toLocaleDateString(undefined, {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    });

function buildGrid(month: string): string[] {
    const [year, m] = month.split('-').map(Number);
    const first = new Date(Date.UTC(year, m - 1, 1));
    const offset = (first.getUTCDay() + 6) % 7;
    const days = new Date(Date.UTC(year, m, 0)).getUTCDate();
    const cells = Math.ceil((offset + days) / 7) * 7;

    return Array.from({ length: cells }, (_, i) => {
        const date = new Date(Date.UTC(year, m - 1, 1 - offset + i));

        return date.toISOString().slice(0, 10);
    });
}

function EventDialog({
    event,
    defaultDate,
    audiences,
    classes,
    onClose,
}: {
    event: SchoolEventItem | null;
    defaultDate: string;
    audiences: EnumOption[];
    classes: Option[];
    onClose: () => void;
}) {
    const form = useForm({
        title: event?.title ?? '',
        description: event?.description ?? '',
        location: event?.location ?? '',
        all_day: event?.all_day ?? false,
        starts_at: event
            ? event.starts_at.slice(0, 16)
            : `${defaultDate}T09:00`,
        ends_at: event ? event.ends_at.slice(0, 16) : `${defaultDate}T10:00`,
        audience: event?.audience ?? 'everyone',
        school_class_id: (event?.school_class_id ?? '') as number | '',
    });

    const submit = (e: FormEvent): void => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (event) {
            form.put(update.url(event.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-xl">
                <DialogTitle>{event ? 'Edit event' : 'New event'}</DialogTitle>
                <DialogDescription>
                    Events appear on the calendar of everyone in the audience.
                </DialogDescription>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="event-title"
                        label="Title"
                        error={form.errors.title}
                        required
                        className="sm:col-span-2"
                    >
                        <Input
                            id="event-title"
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                            maxLength={150}
                            required
                        />
                    </FormField>
                    <div className="flex items-center gap-2 sm:col-span-2">
                        <Checkbox
                            id="event-all-day"
                            checked={form.data.all_day}
                            onCheckedChange={(checked) =>
                                form.setData('all_day', checked === true)
                            }
                        />
                        <Label htmlFor="event-all-day">All day</Label>
                    </div>
                    <FormField
                        id="event-start"
                        label="Starts"
                        error={form.errors.starts_at}
                        required
                    >
                        <Input
                            id="event-start"
                            type={form.data.all_day ? 'date' : 'datetime-local'}
                            value={
                                form.data.all_day
                                    ? form.data.starts_at.slice(0, 10)
                                    : form.data.starts_at
                            }
                            onChange={(e) =>
                                form.setData('starts_at', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="event-end"
                        label="Ends"
                        error={form.errors.ends_at}
                        required
                    >
                        <Input
                            id="event-end"
                            type={form.data.all_day ? 'date' : 'datetime-local'}
                            value={
                                form.data.all_day
                                    ? form.data.ends_at.slice(0, 10)
                                    : form.data.ends_at
                            }
                            onChange={(e) =>
                                form.setData('ends_at', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="event-location"
                        label="Location"
                        error={form.errors.location}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="event-location"
                            value={form.data.location}
                            onChange={(e) =>
                                form.setData('location', e.target.value)
                            }
                            maxLength={150}
                        />
                    </FormField>
                    <AudienceFields
                        idPrefix="event"
                        audiences={audiences}
                        classes={classes}
                        audience={form.data.audience}
                        classId={form.data.school_class_id}
                        errors={form.errors}
                        onAudienceChange={(value) =>
                            form.setData('audience', value)
                        }
                        onClassChange={(value) =>
                            form.setData('school_class_id', value)
                        }
                    />
                    <FormField
                        id="event-description"
                        label="Details"
                        error={form.errors.description}
                        className="sm:col-span-2"
                    >
                        <Textarea
                            id="event-description"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                            rows={3}
                            maxLength={2000}
                        />
                    </FormField>
                    <div className="flex justify-end sm:col-span-2">
                        <Button type="submit" disabled={form.processing}>
                            Save event
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EventLine({
    event,
    can,
    onEdit,
}: {
    event: SchoolEventItem;
    can: Props['can'];
    onEdit: () => void;
}) {
    const sameDay = day(event.starts_at) === day(event.ends_at);

    return (
        <li className="space-y-1 rounded-lg border p-3">
            <div className="flex items-start justify-between gap-2">
                <p className="font-medium">{event.title}</p>
                <TonePill tone="info">
                    {event.audience_label}
                    {event.school_class && ` · ${event.school_class}`}
                </TonePill>
            </div>
            <p className="text-xs text-muted-foreground">
                {day(event.starts_at)}
                {!event.all_day && ` ${time(event.starts_at)}`}
                {(!sameDay || !event.all_day) && ' – '}
                {!sameDay && day(event.ends_at)}
                {!event.all_day && ` ${time(event.ends_at)}`}
                {event.all_day && sameDay && ' · all day'}
            </p>
            {event.location && (
                <p className="flex items-center gap-1 text-xs text-muted-foreground">
                    <MapPin className="size-3" aria-hidden /> {event.location}
                </p>
            )}
            {event.description && (
                <p className="text-sm whitespace-pre-line">
                    {event.description}
                </p>
            )}
            {(can.edit || can.delete) && (
                <div className="flex gap-2 pt-1">
                    {can.edit && (
                        <Button size="sm" variant="outline" onClick={onEdit}>
                            Edit
                        </Button>
                    )}
                    {can.delete && (
                        <ConfirmDelete
                            action={destroy(event.id)}
                            title="Delete event?"
                            description={`“${event.title}” will be removed from the calendar.`}
                        />
                    )}
                </div>
            )}
        </li>
    );
}

export default function Events({
    month,
    events,
    upcoming,
    audiences,
    classes,
    can,
}: Props) {
    const [editing, setEditing] = useState<SchoolEventItem | null | 'new'>(
        null,
    );
    const [selectedDay, setSelectedDay] = useState<string | null>(null);
    const today = new Date().toISOString().slice(0, 10);
    const grid = buildGrid(month);
    const on = (date: string): SchoolEventItem[] =>
        events.filter(
            (event) =>
                day(event.starts_at) <= date && day(event.ends_at) >= date,
        );
    const dayEvents = selectedDay ? on(selectedDay) : [];

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Calendar"
                description="Holidays, meetings and school events."
                actions={
                    can.create ? (
                        <Button onClick={() => setEditing('new')}>
                            <Plus aria-hidden /> New event
                        </Button>
                    ) : null
                }
            />
            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader className="flex flex-row items-center justify-between gap-2">
                        <CardTitle>{monthLabel(month)}</CardTitle>
                        <div className="flex gap-1">
                            <Button variant="outline" size="icon" asChild>
                                <Link
                                    href={index({
                                        query: { month: shiftMonth(month, -1) },
                                    })}
                                    aria-label="Previous month"
                                    preserveScroll
                                >
                                    <ChevronLeft aria-hidden />
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={index()} preserveScroll>
                                    Today
                                </Link>
                            </Button>
                            <Button variant="outline" size="icon" asChild>
                                <Link
                                    href={index({
                                        query: { month: shiftMonth(month, 1) },
                                    })}
                                    aria-label="Next month"
                                    preserveScroll
                                >
                                    <ChevronRight aria-hidden />
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-7 gap-px overflow-hidden rounded-lg border bg-border text-xs">
                            {WEEKDAYS.map((weekday) => (
                                <div
                                    key={weekday}
                                    className="bg-muted px-2 py-1.5 text-center font-medium text-muted-foreground"
                                >
                                    {weekday}
                                </div>
                            ))}
                            {grid.map((date) => {
                                const items = on(date);
                                const inMonth = date.startsWith(month);

                                return (
                                    <button
                                        key={date}
                                        type="button"
                                        onClick={() => setSelectedDay(date)}
                                        className={cn(
                                            'flex min-h-20 flex-col items-stretch gap-1 bg-card p-1.5 text-left transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                            !inMonth &&
                                                'bg-muted/40 text-muted-foreground',
                                            selectedDay === date &&
                                                'ring-2 ring-primary ring-inset',
                                        )}
                                        aria-label={`${date}, ${items.length} ${items.length === 1 ? 'event' : 'events'}`}
                                    >
                                        <span
                                            className={cn(
                                                'flex size-6 items-center justify-center self-end rounded-full',
                                                date === today &&
                                                    'bg-primary font-semibold text-primary-foreground',
                                            )}
                                        >
                                            {Number(date.slice(8))}
                                        </span>
                                        {items.slice(0, 2).map((event) => (
                                            <span
                                                key={event.id}
                                                className={cn(
                                                    'truncate rounded px-1 py-0.5 text-[11px] font-medium',
                                                    chip[event.audience] ??
                                                        chip.everyone,
                                                )}
                                            >
                                                {event.title}
                                            </span>
                                        ))}
                                        {items.length > 2 && (
                                            <span className="px-1 text-[11px] text-muted-foreground">
                                                +{items.length - 2} more
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>
                            {selectedDay ? selectedDay : 'Coming up'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {(selectedDay ? dayEvents : upcoming).length === 0 ? (
                            <div className="flex flex-col items-center gap-2 py-6 text-center text-sm text-muted-foreground">
                                <CalendarDays
                                    className="size-8 text-primary"
                                    aria-hidden
                                />
                                Nothing scheduled.
                            </div>
                        ) : (
                            <ul className="space-y-3">
                                {(selectedDay ? dayEvents : upcoming).map(
                                    (event) => (
                                        <EventLine
                                            key={event.id}
                                            event={event}
                                            can={can}
                                            onEdit={() => setEditing(event)}
                                        />
                                    ),
                                )}
                            </ul>
                        )}
                        {selectedDay && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="mt-3"
                                onClick={() => setSelectedDay(null)}
                            >
                                Show what's coming up
                            </Button>
                        )}
                    </CardContent>
                </Card>
            </div>
            {editing !== null && (
                <EventDialog
                    event={editing === 'new' ? null : editing}
                    defaultDate={selectedDay ?? today}
                    audiences={audiences}
                    classes={classes}
                    onClose={() => setEditing(null)}
                />
            )}
        </div>
    );
}

Events.layout = {
    breadcrumbs: [{ title: 'Calendar', href: index() }],
};
