import { router, useForm } from '@inertiajs/react';
import { Award, BadgeCheck, Ban, Pencil, Plus, Printer } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import ConfirmDelete from '@/components/confirm-delete';
import Pagination from '@/components/data/pagination';
import SearchInput from '@/components/data/search-input';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import type { StatusTone } from '@/components/status-badge';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { useFormat } from '@/hooks/use-format';
import {
    destroy,
    index,
    issue,
    print,
    revoke,
    store,
    update,
} from '@/routes/records/certificates';
import type { PaginatedList } from '@/types/communication';
import type { EnumOption } from '@/types/people';

type CertificateItem = {
    id: number;
    serial_no: string;
    type_label: string;
    title: string;
    body: string;
    status: 'draft' | 'issued' | 'revoked';
    status_label: string;
    student: string;
    admission_no: string;
    issued_on: string | null;
    revoke_reason: string | null;
};

type Props = {
    certificates: PaginatedList<CertificateItem>;
    filters: { search?: string; status?: string };
    types: EnumOption[];
    statuses: EnumOption[];
    can: { create: boolean; edit: boolean; approve: boolean; delete: boolean };
};

const tones: Record<CertificateItem['status'], StatusTone> = {
    draft: 'warning',
    issued: 'success',
    revoked: 'danger',
};

function DraftDialog({
    types,
    onClose,
}: {
    types: EnumOption[];
    onClose: () => void;
}) {
    const form = useForm({
        admission_no: '',
        type: types[0]?.value ?? 'bonafide',
    });

    const submit = (e: FormEvent): void => {
        e.preventDefault();
        form.post(store.url(), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle>New certificate</DialogTitle>
                <DialogDescription>
                    A draft is prefilled from the student's record. You can edit
                    the wording before it is issued.
                </DialogDescription>
                <form onSubmit={submit} className="grid gap-4">
                    <FormField
                        id="cert-admission"
                        label="Admission number"
                        error={form.errors.admission_no}
                        required
                    >
                        <Input
                            id="cert-admission"
                            value={form.data.admission_no}
                            onChange={(e) =>
                                form.setData('admission_no', e.target.value)
                            }
                            autoComplete="off"
                            required
                        />
                    </FormField>
                    <FormField
                        id="cert-type"
                        label="Type"
                        error={form.errors.type}
                        required
                    >
                        <NativeSelect
                            id="cert-type"
                            value={form.data.type}
                            onChange={(e) =>
                                form.setData('type', e.target.value)
                            }
                        >
                            {types.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            Create draft
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditDialog({
    certificate,
    onClose,
}: {
    certificate: CertificateItem;
    onClose: () => void;
}) {
    const form = useForm({ title: certificate.title, body: certificate.body });

    const submit = (e: FormEvent): void => {
        e.preventDefault();
        form.put(update.url(certificate.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-xl">
                <DialogTitle>Edit {certificate.serial_no}</DialogTitle>
                <DialogDescription>
                    The wording is fixed once the certificate is issued.
                </DialogDescription>
                <form onSubmit={submit} className="grid gap-4">
                    <FormField
                        id="cert-title"
                        label="Title"
                        error={form.errors.title}
                        required
                    >
                        <Input
                            id="cert-title"
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="cert-body"
                        label="Text"
                        error={form.errors.body}
                        required
                    >
                        <Textarea
                            id="cert-body"
                            rows={8}
                            value={form.data.body}
                            onChange={(e) =>
                                form.setData('body', e.target.value)
                            }
                            maxLength={3000}
                            required
                        />
                    </FormField>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            Save draft
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function RevokeDialog({
    certificate,
    onClose,
}: {
    certificate: CertificateItem;
    onClose: () => void;
}) {
    const form = useForm({ reason: '' });

    const submit = (e: FormEvent): void => {
        e.preventDefault();
        form.post(revoke.url(certificate.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle>Revoke {certificate.serial_no}?</DialogTitle>
                <DialogDescription>
                    Anyone checking the verification link will see that it was
                    revoked.
                </DialogDescription>
                <form onSubmit={submit} className="grid gap-4">
                    <FormField
                        id="revoke-reason"
                        label="Reason"
                        error={form.errors.reason}
                        required
                    >
                        <Input
                            id="revoke-reason"
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <div className="flex justify-end">
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing}
                        >
                            Revoke
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Certificates({
    certificates,
    filters,
    types,
    statuses,
    can,
}: Props) {
    const format = useFormat();
    const [search, setSearch] = useState(filters.search ?? '');
    const [drafting, setDrafting] = useState(false);
    const [editing, setEditing] = useState<CertificateItem | null>(null);
    const [revoking, setRevoking] = useState<CertificateItem | null>(null);
    const manages = can.create || can.approve;

    useEffect(() => {
        if (search === (filters.search ?? '')) {
            return;
        }

        const timeout = setTimeout(
            () =>
                router.get(
                    index.url(),
                    { ...filters, search: search || undefined },
                    { preserveState: true, replace: true },
                ),
            300,
        );

        return () => clearTimeout(timeout);
    }, [search, filters]);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Certificates"
                description={
                    manages
                        ? 'Draft, issue and verify student certificates.'
                        : 'Certificates issued to you.'
                }
                actions={
                    can.create ? (
                        <Button onClick={() => setDrafting(true)}>
                            <Plus aria-hidden /> New certificate
                        </Button>
                    ) : null
                }
            />
            {manages && (
                <div className="flex flex-col gap-3 sm:flex-row">
                    <SearchInput
                        value={search}
                        onChange={setSearch}
                        placeholder="Serial, student or admission no…"
                    />
                    <NativeSelect
                        aria-label="Status"
                        value={filters.status ?? ''}
                        onChange={(e) =>
                            router.get(
                                index.url(),
                                {
                                    search: search || undefined,
                                    status: e.target.value || undefined,
                                },
                                { preserveState: true, replace: true },
                            )
                        }
                        className="sm:w-44"
                    >
                        <option value="">All statuses</option>
                        {statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </NativeSelect>
                </div>
            )}
            {certificates.data.length === 0 ? (
                <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                    <Award className="size-8 text-primary" aria-hidden />
                    No certificates.
                </div>
            ) : (
                <div className="grid gap-4 lg:grid-cols-2">
                    {certificates.data.map((certificate) => (
                        <Card key={certificate.id} className="overflow-hidden">
                            <div
                                className="h-1.5 bg-gradient-to-r from-amber-400 via-orange-500 to-rose-500"
                                aria-hidden
                            />
                            <CardContent className="space-y-3">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p className="font-semibold">
                                            {certificate.title}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            <span className="font-mono">
                                                {certificate.serial_no}
                                            </span>{' '}
                                            · {certificate.student} (
                                            {certificate.admission_no})
                                        </p>
                                    </div>
                                    <TonePill tone={tones[certificate.status]}>
                                        {certificate.status_label}
                                    </TonePill>
                                </div>
                                <p className="line-clamp-3 text-sm text-muted-foreground">
                                    {certificate.body}
                                </p>
                                {certificate.issued_on && (
                                    <p className="text-xs">
                                        Issued{' '}
                                        {format.date(certificate.issued_on)}
                                    </p>
                                )}
                                {certificate.revoke_reason && (
                                    <p className="text-xs text-rose-700 dark:text-rose-400">
                                        Revoked: {certificate.revoke_reason}
                                    </p>
                                )}
                                <div className="flex flex-wrap gap-2 border-t pt-3">
                                    {certificate.status !== 'draft' ||
                                    manages ? (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            asChild
                                        >
                                            <a
                                                href={print.url(certificate.id)}
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                <Printer aria-hidden />{' '}
                                                {certificate.status === 'draft'
                                                    ? 'Preview'
                                                    : 'Print'}
                                            </a>
                                        </Button>
                                    ) : null}
                                    {certificate.status === 'draft' &&
                                        can.edit && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    setEditing(certificate)
                                                }
                                            >
                                                <Pencil aria-hidden /> Edit
                                            </Button>
                                        )}
                                    {certificate.status === 'draft' &&
                                        can.approve && (
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    router.post(
                                                        issue.url(
                                                            certificate.id,
                                                        ),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <BadgeCheck aria-hidden /> Issue
                                            </Button>
                                        )}
                                    {certificate.status === 'issued' &&
                                        can.approve && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    setRevoking(certificate)
                                                }
                                            >
                                                <Ban aria-hidden /> Revoke
                                            </Button>
                                        )}
                                    {certificate.status === 'draft' &&
                                        can.delete && (
                                            <ConfirmDelete
                                                action={destroy(certificate.id)}
                                                title="Delete draft?"
                                                description={`${certificate.serial_no} will be removed.`}
                                            />
                                        )}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}
            <Pagination meta={certificates.meta} />
            {drafting && (
                <DraftDialog types={types} onClose={() => setDrafting(false)} />
            )}
            {editing && (
                <EditDialog
                    certificate={editing}
                    onClose={() => setEditing(null)}
                />
            )}
            {revoking && (
                <RevokeDialog
                    certificate={revoking}
                    onClose={() => setRevoking(null)}
                />
            )}
        </div>
    );
}

Certificates.layout = {
    breadcrumbs: [{ title: 'Certificates', href: index() }],
};
