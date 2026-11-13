import { useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { edit, update } from '@/routes/academics/periods';
import type { PeriodData } from '@/types';

type Props = {
    periods: PeriodData[];
    canEdit: boolean;
};

const addMinutes = (time: string, minutes: number): string => {
    const [hours, mins] = time.split(':').map(Number);
    const total = Math.min(hours * 60 + mins + minutes, 23 * 60 + 59);

    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
};

export default function PeriodsEdit({ periods, canEdit }: Props) {
    const form = useForm<{ periods: PeriodData[] }>({ periods });
    const errors = form.errors as Record<string, string | undefined>;
    const rows = form.data.periods;

    const updateRow = (i: number, changes: Partial<PeriodData>): void =>
        form.setData(
            'periods',
            rows.map((row, index) =>
                index === i ? { ...row, ...changes } : row,
            ),
        );

    const move = (i: number, offset: number): void => {
        const next = [...rows];
        [next[i], next[i + offset]] = [next[i + offset], next[i]];
        form.setData('periods', next);
    };

    const addRow = (): void => {
        const last = rows.at(-1);
        const startsAt = last ? last.ends_at : '08:00';
        const lessonCount = rows.filter((row) => !row.is_break).length;

        form.setData('periods', [
            ...rows,
            {
                id: null,
                name: `Period ${lessonCount + 1}`,
                starts_at: startsAt,
                ends_at: addMinutes(startsAt, 45),
                is_break: false,
            },
        ]);
    };

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.put(update.url(), { preserveScroll: true });
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Bell schedule"
                description="The daily periods every timetable is built on."
            />
            <form onSubmit={submit} className="max-w-3xl">
                <Card>
                    <CardHeader>
                        <CardTitle>Periods</CardTitle>
                        <CardDescription>
                            List periods in time order. Breaks appear on
                            timetables but cannot hold lessons.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {rows.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No periods defined yet.
                            </p>
                        )}
                        {rows.map((row, i) => (
                            <fieldset
                                key={row.id ?? `new-${i}`}
                                disabled={!canEdit}
                                className="grid items-center gap-2 rounded-lg border p-3 sm:grid-cols-[auto_1fr_7rem_7rem_auto_auto]"
                            >
                                <legend className="sr-only">{row.name}</legend>
                                <div className="flex sm:flex-col">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        disabled={i === 0}
                                        aria-label={`Move ${row.name} up`}
                                        onClick={() => move(i, -1)}
                                    >
                                        <ArrowUp aria-hidden />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        disabled={i === rows.length - 1}
                                        aria-label={`Move ${row.name} down`}
                                        onClick={() => move(i, 1)}
                                    >
                                        <ArrowDown aria-hidden />
                                    </Button>
                                </div>
                                <div>
                                    <Input
                                        aria-label="Name"
                                        value={row.name}
                                        onChange={(e) =>
                                            updateRow(i, {
                                                name: e.target.value,
                                            })
                                        }
                                        required
                                    />
                                    <InputError
                                        message={errors[`periods.${i}.name`]}
                                    />
                                </div>
                                <div>
                                    <Input
                                        type="time"
                                        aria-label="Starts at"
                                        value={row.starts_at}
                                        onChange={(e) =>
                                            updateRow(i, {
                                                starts_at: e.target.value,
                                            })
                                        }
                                        required
                                    />
                                    <InputError
                                        message={
                                            errors[`periods.${i}.starts_at`]
                                        }
                                    />
                                </div>
                                <div>
                                    <Input
                                        type="time"
                                        aria-label="Ends at"
                                        value={row.ends_at}
                                        onChange={(e) =>
                                            updateRow(i, {
                                                ends_at: e.target.value,
                                            })
                                        }
                                        required
                                    />
                                    <InputError
                                        message={errors[`periods.${i}.ends_at`]}
                                    />
                                </div>
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={row.is_break}
                                        onCheckedChange={(checked) =>
                                            updateRow(i, {
                                                is_break: checked === true,
                                            })
                                        }
                                    />
                                    Break
                                </label>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Remove ${row.name}`}
                                    onClick={() =>
                                        form.setData(
                                            'periods',
                                            rows.filter(
                                                (_, index) => index !== i,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 aria-hidden />
                                </Button>
                            </fieldset>
                        ))}
                        <InputError message={errors.periods} />
                        {canEdit && (
                            <div className="flex flex-wrap gap-2 pt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addRow}
                                    disabled={rows.length >= 20}
                                >
                                    <Plus aria-hidden /> Add period
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Save schedule
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </form>
        </div>
    );
}

PeriodsEdit.layout = {
    breadcrumbs: [{ title: 'Bell schedule', href: edit() }],
};
