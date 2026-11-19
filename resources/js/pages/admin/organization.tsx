import { router, useForm } from '@inertiajs/react';
import { CalendarRange, Mail, Pencil, Phone, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import PhotoUploader from '@/components/people/photo-uploader';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useFormat } from '@/hooks/use-format';
import { cn } from '@/lib/utils';
import { destroy, index, store, update } from '@/routes/admin/organization';

type Member = {
    id: number;
    name: string;
    position: string;
    body: string;
    email: string | null;
    phone: string | null;
    bio: string | null;
    term_starts_on: string | null;
    term_ends_on: string | null;
    sort_order: number;
    is_active: boolean;
    photo_url: string | null;
};

type Body = { value: string; label: string; members: Member[] };

type Props = {
    bodies: Body[];
    positions: string[];
    can: { create: boolean; edit: boolean; delete: boolean };
};

const tierTone: Record<string, string> = {
    trustees: 'from-navy-900 via-navy-800 to-royal-700',
    executive: 'from-royal-700 to-navy-600',
    advisory: 'from-emerald-700 to-teal-600',
};

function MemberCard({
    member,
    lead,
    onEdit,
    canEdit,
    canDelete,
}: {
    member: Member;
    lead: boolean;
    onEdit: () => void;
    canEdit: boolean;
    canDelete: boolean;
}) {
    const { date } = useFormat();
    const initials = member.name
        .split(/\s+/)
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

    return (
        <article
            className={cn(
                'relative flex flex-col items-center rounded-2xl border bg-card p-5 text-center shadow-sm transition hover:shadow-md',
                lead &&
                    'border-gold-300 ring-1 ring-gold-200 dark:border-gold-500/40 dark:ring-gold-500/20',
                !member.is_active && 'opacity-60',
            )}
        >
            {member.photo_url ? (
                <img
                    src={member.photo_url}
                    alt=""
                    className={cn(
                        'rounded-full object-cover ring-4 ring-gold-200 dark:ring-gold-500/30',
                        lead ? 'size-28' : 'size-20',
                    )}
                />
            ) : (
                <span
                    className={cn(
                        'flex items-center justify-center rounded-full bg-gradient-to-br from-navy-100 to-royal-100 font-display text-navy-800 ring-4 ring-gold-200 dark:from-navy-500/20 dark:to-royal-500/20 dark:text-navy-100',
                        lead ? 'size-28 text-3xl' : 'size-20 text-2xl',
                    )}
                >
                    {initials}
                </span>
            )}
            <p className="mt-3 text-xs font-semibold tracking-[0.15em] text-gold-700 uppercase dark:text-gold-300">
                {member.position}
            </p>
            <h3
                className={cn(
                    'font-display leading-tight',
                    lead ? 'text-2xl' : 'text-xl',
                )}
            >
                {member.name}
            </h3>
            {!member.is_active && <TonePill tone="neutral">Former</TonePill>}
            {(member.term_starts_on || member.term_ends_on) && (
                <p className="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground">
                    <CalendarRange className="size-3.5" aria-hidden />
                    {date(member.term_starts_on, '…')} –{' '}
                    {date(member.term_ends_on, 'present')}
                </p>
            )}
            {member.bio && (
                <p className="mt-2 line-clamp-3 text-sm text-muted-foreground">
                    {member.bio}
                </p>
            )}
            <div className="mt-3 flex flex-wrap justify-center gap-3 text-xs text-muted-foreground">
                {member.email && (
                    <a
                        href={`mailto:${member.email}`}
                        className="inline-flex items-center gap-1 hover:text-foreground"
                    >
                        <Mail className="size-3.5" aria-hidden /> Email
                    </a>
                )}
                {member.phone && (
                    <a
                        href={`tel:${member.phone.replace(/[^+\d]/g, '')}`}
                        className="inline-flex items-center gap-1 hover:text-foreground"
                    >
                        <Phone className="size-3.5" aria-hidden />{' '}
                        {member.phone}
                    </a>
                )}
            </div>
            {(canEdit || canDelete) && (
                <div className="absolute top-2 right-2 flex gap-1">
                    {canEdit && (
                        <Button
                            size="icon"
                            variant="ghost"
                            className="size-8"
                            aria-label={`Edit ${member.name}`}
                            onClick={onEdit}
                        >
                            <Pencil aria-hidden />
                        </Button>
                    )}
                    {canDelete && (
                        <Button
                            size="icon"
                            variant="ghost"
                            className="size-8 text-destructive"
                            aria-label={`Remove ${member.name}`}
                            onClick={() =>
                                window.confirm(`Remove ${member.name}?`) &&
                                router.delete(destroy.url(member.id), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            <Trash2 aria-hidden />
                        </Button>
                    )}
                </div>
            )}
        </article>
    );
}

function MemberDialog({
    member,
    bodies,
    positions,
    defaultBody,
    onClose,
}: {
    member: Member | null;
    bodies: Body[];
    positions: string[];
    defaultBody: string;
    onClose: () => void;
}) {
    const form = useForm({
        name: member?.name ?? '',
        position: member?.position ?? '',
        body: member?.body ?? defaultBody,
        email: member?.email ?? '',
        phone: member?.phone ?? '',
        bio: member?.bio ?? '',
        term_starts_on: member?.term_starts_on ?? '',
        term_ends_on: member?.term_ends_on ?? '',
        sort_order: member?.sort_order ?? 0,
        is_active: member?.is_active ?? true,
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            term_starts_on: data.term_starts_on || null,
            term_ends_on: data.term_ends_on || null,
        }));
        const options = { preserveScroll: true, onSuccess: onClose };

        if (member) {
            form.put(update.url(member.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogTitle>
                    {member ? `Edit ${member.name}` : 'Add a member'}
                </DialogTitle>
                <DialogDescription>
                    Members appear in order within their body; the lowest order
                    number is shown first and largest.
                </DialogDescription>
                {member && (
                    <PhotoUploader
                        kind="members"
                        id={member.id}
                        name={member.name}
                        photoUrl={member.photo_url}
                        canEdit
                    />
                )}
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="m-name"
                        label="Full name"
                        required
                        error={form.errors.name}
                    >
                        <Input
                            id="m-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="m-position"
                        label="Position"
                        required
                        error={form.errors.position}
                    >
                        <Input
                            id="m-position"
                            list="position-suggestions"
                            value={form.data.position}
                            onChange={(event) =>
                                form.setData('position', event.target.value)
                            }
                            placeholder="e.g. President"
                        />
                        <datalist id="position-suggestions">
                            {positions.map((position) => (
                                <option key={position} value={position} />
                            ))}
                        </datalist>
                    </FormField>
                    <FormField
                        id="m-body"
                        label="Body"
                        required
                        error={form.errors.body}
                    >
                        <NativeSelect
                            id="m-body"
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                        >
                            {bodies.map((body) => (
                                <option key={body.value} value={body.value}>
                                    {body.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField
                        id="m-order"
                        label="Display order"
                        error={form.errors.sort_order}
                        description="0 = first (e.g. Chairperson)."
                    >
                        <Input
                            id="m-order"
                            type="number"
                            min={0}
                            max={999}
                            value={form.data.sort_order}
                            onChange={(event) =>
                                form.setData(
                                    'sort_order',
                                    Number(event.target.value),
                                )
                            }
                        />
                    </FormField>
                    <FormField
                        id="m-email"
                        label="Email"
                        error={form.errors.email}
                    >
                        <Input
                            id="m-email"
                            type="email"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="m-phone"
                        label="Phone"
                        error={form.errors.phone}
                        description="Stored encrypted."
                    >
                        <Input
                            id="m-phone"
                            type="tel"
                            value={form.data.phone}
                            onChange={(event) =>
                                form.setData('phone', event.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="m-start"
                        label="Term starts"
                        error={form.errors.term_starts_on}
                    >
                        <Input
                            id="m-start"
                            type="date"
                            value={form.data.term_starts_on}
                            onChange={(event) =>
                                form.setData(
                                    'term_starts_on',
                                    event.target.value,
                                )
                            }
                        />
                    </FormField>
                    <FormField
                        id="m-end"
                        label="Term ends"
                        error={form.errors.term_ends_on}
                    >
                        <Input
                            id="m-end"
                            type="date"
                            value={form.data.term_ends_on}
                            onChange={(event) =>
                                form.setData('term_ends_on', event.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="m-bio"
                        label="Short profile"
                        error={form.errors.bio}
                        className="sm:col-span-2"
                    >
                        <Textarea
                            id="m-bio"
                            rows={3}
                            value={form.data.bio}
                            onChange={(event) =>
                                form.setData('bio', event.target.value)
                            }
                        />
                    </FormField>
                    <div className="flex items-center gap-2 sm:col-span-2">
                        <Checkbox
                            id="m-active"
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked === true)
                            }
                        />
                        <Label htmlFor="m-active" className="font-normal">
                            Currently serving
                        </Label>
                    </div>
                    <div className="flex gap-2 sm:col-span-2">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {member ? 'Save' : 'Add member'}
                        </Button>
                        <Button type="button" variant="ghost" onClick={onClose}>
                            Cancel
                        </Button>
                    </div>
                    {!member && (
                        <p className="text-xs text-muted-foreground sm:col-span-2">
                            Add the photo after saving.
                        </p>
                    )}
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * The school's governing body, shown as a hierarchy: trustees, then the executive
 * committee, then advisers.
 */
export default function Organization({ bodies, positions, can }: Props) {
    const [editing, setEditing] = useState<{
        member: Member | null;
        body: string;
    } | null>(null);
    const total = bodies.reduce((sum, body) => sum + body.members.length, 0);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Management body"
                description="The trustees, office bearers and advisers who govern the school."
                actions={
                    can.create && (
                        <Button
                            onClick={() =>
                                setEditing({
                                    member: null,
                                    body: bodies[0]?.value ?? 'trustees',
                                })
                            }
                        >
                            <Plus aria-hidden /> Add member
                        </Button>
                    )
                }
            />

            {total === 0 && (
                <p className="rounded-2xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                    Add your chairperson, president, secretary, treasurer and
                    trustees to build the management chart.
                </p>
            )}

            <div className="flex flex-col items-center">
                {bodies
                    .filter((body) => body.members.length > 0)
                    .map((body, tier) => {
                        const [lead, ...rest] = body.members;

                        return (
                            <section
                                key={body.value}
                                className="flex w-full flex-col items-center"
                                aria-labelledby={`body-${body.value}`}
                            >
                                {tier > 0 && (
                                    <span
                                        className="h-8 w-px bg-gradient-to-b from-gold-400 to-navy-300"
                                        aria-hidden
                                    />
                                )}
                                <h2
                                    id={`body-${body.value}`}
                                    className={cn(
                                        'rounded-full bg-gradient-to-r px-5 py-1.5 text-sm font-semibold tracking-wide text-white shadow',
                                        tierTone[body.value] ??
                                            tierTone.executive,
                                    )}
                                >
                                    {body.label}
                                </h2>
                                <span
                                    className="h-6 w-px bg-navy-300"
                                    aria-hidden
                                />
                                <div className="w-full max-w-xs">
                                    <MemberCard
                                        member={lead}
                                        lead
                                        onEdit={() =>
                                            setEditing({
                                                member: lead,
                                                body: body.value,
                                            })
                                        }
                                        canEdit={can.edit}
                                        canDelete={can.delete}
                                    />
                                </div>
                                {rest.length > 0 && (
                                    <>
                                        <span
                                            className="h-6 w-px bg-navy-300"
                                            aria-hidden
                                        />
                                        <div className="grid w-full gap-4 border-t-2 border-navy-200 pt-6 sm:grid-cols-2 lg:grid-cols-4 dark:border-white/10">
                                            {rest.map((member) => (
                                                <MemberCard
                                                    key={member.id}
                                                    member={member}
                                                    lead={false}
                                                    onEdit={() =>
                                                        setEditing({
                                                            member,
                                                            body: body.value,
                                                        })
                                                    }
                                                    canEdit={can.edit}
                                                    canDelete={can.delete}
                                                />
                                            ))}
                                        </div>
                                    </>
                                )}
                            </section>
                        );
                    })}
            </div>

            {editing && (
                <MemberDialog
                    member={
                        editing.member
                            ? (bodies
                                  .flatMap((body) => body.members)
                                  .find(
                                      (member) =>
                                          member.id === editing.member?.id,
                                  ) ?? editing.member)
                            : null
                    }
                    bodies={bodies}
                    positions={positions}
                    defaultBody={editing.body}
                    onClose={() => setEditing(null)}
                />
            )}
        </div>
    );
}

Organization.layout = {
    breadcrumbs: [{ title: 'Management body', href: index() }],
};
