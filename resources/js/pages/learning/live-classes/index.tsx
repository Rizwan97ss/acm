import { Link, router } from '@inertiajs/react';
import { Ban, CalendarClock, Pencil, Plus, Video } from 'lucide-react';
import ConfirmDelete from '@/components/confirm-delete';
import Pagination from '@/components/data/pagination';
import { relativeTime } from '@/components/learning/format';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/hooks/use-format';
import { cn } from '@/lib/utils';
import {
    cancel,
    create,
    destroy,
    edit,
    index,
} from '@/routes/learning/live-classes';
import type { PaginationMeta } from '@/types/learning';

type LiveClassRow = {
    id: number;
    title: string;
    description: string | null;
    provider_label: string;
    starts_at: string;
    ends_at: string;
    duration_minutes: number;
    status: 'scheduled' | 'cancelled';
    subject: string | null;
    audience: string;
    author: string | null;
    can_manage: boolean;
};

type Props = {
    liveClasses: { data: LiveClassRow[]; meta: PaginationMeta };
    when: 'upcoming' | 'past';
};

export default function LiveClassesIndex({ liveClasses, when }: Props) {
    const { dateTime } = useFormat();

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Live classes"
                description="Online lessons on Zoom, Google Meet, Teams or any meeting tool. Students join from their learning page."
                actions={
                    <Button asChild>
                        <Link href={create()}>
                            <Plus aria-hidden /> Schedule class
                        </Link>
                    </Button>
                }
            />
            <div
                className="grid w-64 grid-cols-2 gap-1 rounded-xl bg-muted p-1"
                role="tablist"
                aria-label="When"
            >
                {(['upcoming', 'past'] as const).map((option) => (
                    <button
                        key={option}
                        type="button"
                        role="tab"
                        aria-selected={when === option}
                        onClick={() =>
                            router.get(
                                index.url(),
                                { when: option },
                                { preserveState: true, replace: true },
                            )
                        }
                        className={cn(
                            'rounded-lg px-3 py-1.5 text-sm capitalize',
                            when === option &&
                                'bg-background font-medium shadow-sm',
                        )}
                    >
                        {option}
                    </button>
                ))}
            </div>

            {liveClasses.data.length === 0 ? (
                <div className="rounded-2xl border border-dashed p-10 text-center">
                    <Video
                        className="mx-auto size-8 text-muted-foreground"
                        aria-hidden
                    />
                    <p className="mt-3 font-medium">
                        {when === 'upcoming'
                            ? 'No live classes scheduled'
                            : 'No past live classes'}
                    </p>
                </div>
            ) : (
                <ul className="grid gap-3">
                    {liveClasses.data.map((liveClass) => (
                        <li
                            key={liveClass.id}
                            className={cn(
                                'flex flex-wrap items-center gap-4 rounded-2xl border bg-card p-4 shadow-sm',
                                liveClass.status === 'cancelled' &&
                                    'opacity-60',
                            )}
                        >
                            <span className="flex size-12 shrink-0 flex-col items-center justify-center rounded-xl bg-navy-50 text-navy-800 dark:bg-navy-500/15 dark:text-navy-100">
                                <CalendarClock className="size-5" aria-hidden />
                            </span>
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h2 className="font-semibold">
                                        {liveClass.title}
                                    </h2>
                                    {liveClass.status === 'cancelled' && (
                                        <TonePill tone="danger">
                                            Cancelled
                                        </TonePill>
                                    )}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {liveClass.subject} · {liveClass.audience} ·{' '}
                                    {liveClass.provider_label}
                                </p>
                                <p className="text-sm">
                                    {dateTime(liveClass.starts_at)} ·{' '}
                                    {liveClass.duration_minutes} min
                                    {when === 'upcoming' && (
                                        <span className="text-muted-foreground">
                                            {' '}
                                            ({relativeTime(liveClass.starts_at)}
                                            )
                                        </span>
                                    )}
                                </p>
                            </div>
                            {liveClass.can_manage && (
                                <div className="flex gap-1">
                                    <Button asChild size="sm" variant="ghost">
                                        <Link href={edit(liveClass.id)}>
                                            <Pencil aria-hidden /> Edit
                                        </Link>
                                    </Button>
                                    {liveClass.status === 'scheduled' &&
                                        when === 'upcoming' && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    router.post(
                                                        cancel.url(
                                                            liveClass.id,
                                                        ),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <Ban aria-hidden /> Cancel
                                            </Button>
                                        )}
                                    <ConfirmDelete
                                        action={destroy(liveClass.id)}
                                        title="Delete this live class?"
                                        description="It will disappear from students’ learning pages."
                                    />
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            <Pagination meta={liveClasses.meta} />
        </div>
    );
}

LiveClassesIndex.layout = {
    breadcrumbs: [{ title: 'Live classes', href: index() }],
};
