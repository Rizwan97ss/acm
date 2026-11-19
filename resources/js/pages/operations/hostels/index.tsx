import { Link } from '@inertiajs/react';
import { BedDouble, Building, Plus, UserRound } from 'lucide-react';
import { useState } from 'react';
import ProgressBar from '@/components/learning/progress-bar';
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
import { useMoney } from '@/hooks/use-money';
import { index, show } from '@/routes/operations/hostels';

type HostelRow = HostelValues & {
    type_label: string;
    warden: string | null;
    rooms: number;
    beds: number;
    occupied: number;
};

type Props = {
    hostels: HostelRow[];
    types: { value: string; label: string }[];
    wardens: { id: number; name: string }[];
    can: { create: boolean };
};

export default function HostelsIndex({ hostels, types, wardens, can }: Props) {
    const [adding, setAdding] = useState(false);
    const money = useMoney();
    const beds = hostels.reduce((sum, hostel) => sum + hostel.beds, 0);
    const occupied = hostels.reduce((sum, hostel) => sum + hostel.occupied, 0);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Hostels"
                description={`${occupied} of ${beds} beds occupied across ${hostels.length} hostels.`}
                actions={
                    can.create && (
                        <Button onClick={() => setAdding(true)}>
                            <Plus aria-hidden /> Add hostel
                        </Button>
                    )
                }
            />

            {hostels.length === 0 ? (
                <div className="rounded-2xl border border-dashed p-10 text-center">
                    <Building
                        className="mx-auto size-8 text-muted-foreground"
                        aria-hidden
                    />
                    <p className="mt-3 font-medium">No hostels yet</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Add a hostel, then its rooms, then move students in.
                    </p>
                </div>
            ) : (
                <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {hostels.map((hostel) => (
                        <li key={hostel.id}>
                            <Link
                                href={show(hostel.id)}
                                className="group flex h-full flex-col rounded-2xl border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <span className="rounded-xl bg-gradient-to-br from-navy-700 to-royal-500 p-2.5 text-white shadow">
                                        <Building
                                            className="size-5"
                                            aria-hidden
                                        />
                                    </span>
                                    <div className="flex gap-1">
                                        <TonePill tone="info">
                                            {hostel.type_label}
                                        </TonePill>
                                        {!hostel.is_active && (
                                            <TonePill tone="neutral">
                                                Closed
                                            </TonePill>
                                        )}
                                    </div>
                                </div>
                                <h2 className="mt-3 text-lg font-semibold group-hover:text-primary">
                                    {hostel.name}
                                </h2>
                                <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                    <UserRound
                                        className="size-3.5"
                                        aria-hidden
                                    />
                                    {hostel.warden ?? 'No warden assigned'}
                                </p>
                                <div className="mt-auto pt-4">
                                    <ProgressBar
                                        percent={
                                            hostel.beds > 0
                                                ? (hostel.occupied /
                                                      hostel.beds) *
                                                  100
                                                : 0
                                        }
                                        label={`${hostel.occupied} / ${hostel.beds} beds`}
                                    />
                                    <p className="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                                        <span className="inline-flex items-center gap-1">
                                            <BedDouble
                                                className="size-3.5"
                                                aria-hidden
                                            />{' '}
                                            {hostel.rooms} rooms
                                        </span>
                                        <span>
                                            {money.format(
                                                Math.round(
                                                    hostel.monthly_fee * 100,
                                                ),
                                            )}{' '}
                                            / month
                                        </span>
                                    </p>
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            <Dialog open={adding} onOpenChange={setAdding}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogTitle>Add a hostel</DialogTitle>
                    <DialogDescription>
                        You can add rooms on the next screen.
                    </DialogDescription>
                    <HostelForm
                        hostel={null}
                        types={types}
                        wardens={wardens}
                        onDone={() => setAdding(false)}
                    />
                </DialogContent>
            </Dialog>
        </div>
    );
}

HostelsIndex.layout = {
    breadcrumbs: [{ title: 'Hostels', href: index() }],
};
