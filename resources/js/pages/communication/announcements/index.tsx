import { router, useForm } from '@inertiajs/react';
import { EyeOff, Megaphone, Pencil, Pin, Plus, Send } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import AudienceFields from '@/components/audience-fields';
import ConfirmDelete from '@/components/confirm-delete';
import Pagination from '@/components/data/pagination';
import SearchInput from '@/components/data/search-input';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { useFormat } from '@/hooks/use-format';
import { cn } from '@/lib/utils';
import {
    destroy,
    index,
    publish,
    store,
    unpublish,
    update,
} from '@/routes/communication/announcements';
import type { Option } from '@/types';
import type { AnnouncementItem, PaginatedList } from '@/types/communication';
import type { EnumOption } from '@/types/people';

type Props = {
    announcements: PaginatedList<AnnouncementItem>;
    filters: { search?: string; highlight?: number };
    audiences: EnumOption[];
    classes: Option[];
    can: { create: boolean; edit: boolean; delete: boolean; approve: boolean };
};

const accents: Record<string, string> = {
    everyone: 'from-violet-500 to-indigo-500',
    staff: 'from-sky-500 to-cyan-500',
    students: 'from-amber-500 to-orange-500',
    guardians: 'from-emerald-500 to-teal-500',
};

function AnnouncementDialog({
    announcement,
    audiences,
    classes,
    canPublish,
    onClose,
}: {
    announcement: AnnouncementItem | null;
    audiences: EnumOption[];
    classes: Option[];
    canPublish: boolean;
    onClose: () => void;
}) {
    const form = useForm({
        title: announcement?.title ?? '',
        body: announcement?.body ?? '',
        audience: announcement?.audience ?? 'everyone',
        school_class_id: (announcement?.school_class_id ?? '') as number | '',
        is_pinned: announcement?.is_pinned ?? false,
        expires_on: announcement?.expires_on ?? '',
        publish: false,
    });

    const submit = (event: FormEvent, publishNow: boolean): void => {
        event.preventDefault();
        form.transform((data) => ({ ...data, publish: publishNow }));
        const options = { preserveScroll: true, onSuccess: onClose };

        if (announcement) {
            form.put(update.url(announcement.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    const alreadyPublished = announcement?.published_at != null;

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-xl">
                <DialogTitle>
                    {announcement ? 'Edit announcement' : 'New announcement'}
                </DialogTitle>
                <DialogDescription>
                    Publishing notifies everyone in the audience. Editing a
                    published announcement does not notify again.
                </DialogDescription>
                <form
                    onSubmit={(e) => submit(e, false)}
                    className="grid gap-4 sm:grid-cols-2"
                >
                    <FormField
                        id="announcement-title"
                        label="Title"
                        error={form.errors.title}
                        required
                        className="sm:col-span-2"
                    >
                        <Input
                            id="announcement-title"
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                            maxLength={150}
                            required
                        />
                    </FormField>
                    <FormField
                        id="announcement-body"
                        label="Message"
                        error={form.errors.body}
                        required
                        className="sm:col-span-2"
                    >
                        <Textarea
                            id="announcement-body"
                            value={form.data.body}
                            onChange={(e) =>
                                form.setData('body', e.target.value)
                            }
                            rows={6}
                            maxLength={5000}
                            required
                        />
                    </FormField>
                    <AudienceFields
                        idPrefix="announcement"
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
                        id="announcement-expires"
                        label="Hide after"
                        error={form.errors.expires_on}
                    >
                        <Input
                            id="announcement-expires"
                            type="date"
                            value={form.data.expires_on}
                            onChange={(e) =>
                                form.setData('expires_on', e.target.value)
                            }
                        />
                    </FormField>
                    <div className="flex items-center gap-2 self-end pb-2">
                        <Checkbox
                            id="announcement-pinned"
                            checked={form.data.is_pinned}
                            onCheckedChange={(checked) =>
                                form.setData('is_pinned', checked === true)
                            }
                        />
                        <Label htmlFor="announcement-pinned">Pin to top</Label>
                    </div>
                    <div className="flex flex-wrap justify-end gap-2 sm:col-span-2">
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={form.processing}
                        >
                            {alreadyPublished ? 'Save changes' : 'Save draft'}
                        </Button>
                        {canPublish && !alreadyPublished && (
                            <Button
                                type="button"
                                disabled={form.processing}
                                onClick={(e) => submit(e, true)}
                            >
                                <Send aria-hidden /> Publish
                            </Button>
                        )}
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Announcements({
    announcements,
    filters,
    audiences,
    classes,
    can,
}: Props) {
    const format = useFormat();
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState<AnnouncementItem | null | 'new'>(
        null,
    );

    useEffect(() => {
        if (search === (filters.search ?? '')) {
            return;
        }

        const timeout = setTimeout(
            () =>
                router.get(index.url(), search ? { search } : {}, {
                    preserveState: true,
                    replace: true,
                }),
            300,
        );

        return () => clearTimeout(timeout);
    }, [search, filters.search]);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Announcements"
                description="News and notices for the school community."
                actions={
                    can.create ? (
                        <Button onClick={() => setEditing('new')}>
                            <Plus aria-hidden /> New announcement
                        </Button>
                    ) : null
                }
            />
            <SearchInput
                value={search}
                onChange={setSearch}
                placeholder="Search announcements…"
            />
            {announcements.data.length === 0 ? (
                <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                    <Megaphone className="size-8 text-primary" aria-hidden />
                    No announcements yet.
                </div>
            ) : (
                <div className="grid gap-4">
                    {announcements.data.map((announcement) => (
                        <Card
                            key={announcement.id}
                            className={cn(
                                'overflow-hidden',
                                filters.highlight === announcement.id &&
                                    'ring-2 ring-primary',
                            )}
                        >
                            <div
                                className={cn(
                                    'h-1.5 bg-gradient-to-r',
                                    accents[announcement.audience] ??
                                        accents.everyone,
                                )}
                                aria-hidden
                            />
                            <CardContent className="space-y-3">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="space-y-1">
                                        <h2 className="flex items-center gap-2 text-lg font-semibold">
                                            {announcement.is_pinned && (
                                                <Pin
                                                    className="size-4 text-rose-500"
                                                    aria-label="Pinned"
                                                />
                                            )}
                                            {announcement.title}
                                        </h2>
                                        <p className="text-xs text-muted-foreground">
                                            {announcement.published_at
                                                ? format.dateTime(
                                                      announcement.published_at,
                                                  )
                                                : 'Not published'}
                                            {announcement.author &&
                                                ` · ${announcement.author}`}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-1.5">
                                        {!announcement.published_at && (
                                            <TonePill tone="warning">
                                                Draft
                                            </TonePill>
                                        )}
                                        <TonePill tone="info">
                                            {announcement.audience_label}
                                            {announcement.school_class &&
                                                ` · ${announcement.school_class}`}
                                        </TonePill>
                                        {announcement.expires_on && (
                                            <TonePill tone="neutral">
                                                Until{' '}
                                                {format.date(
                                                    announcement.expires_on,
                                                )}
                                            </TonePill>
                                        )}
                                    </div>
                                </div>
                                <p className="text-sm whitespace-pre-line">
                                    {announcement.body}
                                </p>
                                {(can.edit || can.delete || can.approve) && (
                                    <div className="flex flex-wrap gap-2 border-t pt-3">
                                        {can.approve &&
                                            !announcement.published_at && (
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        router.post(
                                                            publish.url(
                                                                announcement.id,
                                                            ),
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Send aria-hidden /> Publish
                                                </Button>
                                            )}
                                        {can.approve &&
                                            announcement.published_at && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(
                                                            unpublish.url(
                                                                announcement.id,
                                                            ),
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <EyeOff aria-hidden />{' '}
                                                    Unpublish
                                                </Button>
                                            )}
                                        {can.edit && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setEditing(announcement)
                                                }
                                            >
                                                <Pencil aria-hidden /> Edit
                                            </Button>
                                        )}
                                        {can.delete && (
                                            <ConfirmDelete
                                                action={destroy(
                                                    announcement.id,
                                                )}
                                                title="Delete announcement?"
                                                description={`“${announcement.title}” will be removed for everyone.`}
                                            />
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}
            <Pagination meta={announcements.meta} />
            {editing !== null && (
                <AnnouncementDialog
                    announcement={editing === 'new' ? null : editing}
                    audiences={audiences}
                    classes={classes}
                    canPublish={can.approve}
                    onClose={() => setEditing(null)}
                />
            )}
        </div>
    );
}

Announcements.layout = {
    breadcrumbs: [{ title: 'Announcements', href: index() }],
};
