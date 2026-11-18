import { Link, router } from '@inertiajs/react';
import {
    Bell,
    BookOpenText,
    CheckCheck,
    ClipboardCheck,
    ListChecks,
    Megaphone,
    MessagesSquare,
    Video,
} from 'lucide-react';
import Pagination from '@/components/data/pagination';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useFormat } from '@/hooks/use-format';
import { cn } from '@/lib/utils';
import { index, open, readAll } from '@/routes/notifications';
import type { NotificationItem, PaginatedList } from '@/types/communication';

type Props = { notifications: PaginatedList<NotificationItem> };

const icons = {
    announcement: { icon: Megaphone, className: 'from-gold-500 to-rose-500' },
    message: {
        icon: MessagesSquare,
        className: 'from-royal-500 to-navy-500',
    },
    general: { icon: Bell, className: 'from-sky-500 to-cyan-500' },
    lesson: { icon: BookOpenText, className: 'from-navy-600 to-royal-500' },
    live_class: { icon: Video, className: 'from-emerald-500 to-teal-500' },
    quiz: { icon: ListChecks, className: 'from-gold-500 to-orange-500' },
    assignment: {
        icon: ClipboardCheck,
        className: 'from-crimson-500 to-rose-500',
    },
};

export default function Notifications({ notifications }: Props) {
    const format = useFormat();
    const hasUnread = notifications.data.some((item) => !item.read);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Notifications"
                description="Announcements and messages addressed to you."
                actions={
                    hasUnread ? (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    readAll.url(),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <CheckCheck aria-hidden /> Mark all read
                        </Button>
                    ) : null
                }
            />
            <Card className="overflow-hidden p-0">
                {notifications.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 p-10 text-center text-sm text-muted-foreground">
                        <Bell className="size-8 text-primary" aria-hidden />
                        You're all caught up.
                    </div>
                ) : (
                    <ul className="divide-y">
                        {notifications.data.map((item) => {
                            const style =
                                icons[item.kind as keyof typeof icons] ??
                                icons.general;
                            const Icon = style.icon;

                            return (
                                <li key={item.id}>
                                    <Link
                                        href={open(item.id)}
                                        className={cn(
                                            'flex items-start gap-3 px-4 py-3 hover:bg-accent',
                                            !item.read && 'bg-primary/5',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'flex size-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-white',
                                                style.className,
                                            )}
                                            aria-hidden
                                        >
                                            <Icon className="size-4" />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span
                                                className={cn(
                                                    'block truncate text-sm',
                                                    !item.read &&
                                                        'font-semibold',
                                                )}
                                            >
                                                {item.title}
                                            </span>
                                            {item.body && (
                                                <span className="block truncate text-sm text-muted-foreground">
                                                    {item.body}
                                                </span>
                                            )}
                                            <span className="block text-xs text-muted-foreground">
                                                {format.dateTime(
                                                    item.created_at,
                                                )}
                                            </span>
                                        </span>
                                        {!item.read && (
                                            <span
                                                className="mt-1.5 size-2 shrink-0 rounded-full bg-rose-500"
                                                aria-label="Unread"
                                            />
                                        )}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </Card>
            <Pagination meta={notifications.meta} />
        </div>
    );
}

Notifications.layout = {
    breadcrumbs: [{ title: 'Notifications', href: index() }],
};
