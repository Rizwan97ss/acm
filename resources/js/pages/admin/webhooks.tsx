import { router, useForm } from '@inertiajs/react';
import { Copy, KeyRound, Pencil, Plus, Send, Webhook } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import ConfirmDelete from '@/components/confirm-delete';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import type { StatusTone } from '@/components/status-badge';
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
import { useFormat } from '@/hooks/use-format';
import {
    destroy,
    index,
    rotate,
    store,
    test,
    update,
} from '@/routes/admin/webhooks';
import type { EnumOption } from '@/types/people';

type Endpoint = {
    id: number;
    url: string;
    description: string | null;
    events: string[];
    is_active: boolean;
    consecutive_failures: number;
    created_at: string | null;
};

type Delivery = {
    id: number;
    uuid: string;
    event: string;
    url: string;
    status: 'pending' | 'succeeded' | 'failed';
    attempts: number;
    response_status: number | null;
    error: string | null;
    created_at: string | null;
};

type Props = {
    endpoints: Endpoint[];
    deliveries: Delivery[];
    events: EnumOption[];
    can: { create: boolean; edit: boolean; delete: boolean };
};

const deliveryTones: Record<Delivery['status'], StatusTone> = {
    pending: 'info',
    succeeded: 'success',
    failed: 'danger',
};

function EndpointDialog({
    endpoint,
    events,
    onClose,
}: {
    endpoint: Endpoint | null;
    events: EnumOption[];
    onClose: () => void;
}) {
    const form = useForm({
        url: endpoint?.url ?? 'https://',
        description: endpoint?.description ?? '',
        events: endpoint?.events ?? events.map((event) => event.value),
        is_active: endpoint?.is_active ?? true,
    });

    const submit = (e: FormEvent): void => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (endpoint) {
            form.put(update.url(endpoint.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle>
                    {endpoint ? 'Edit webhook' : 'Add webhook'}
                </DialogTitle>
                <DialogDescription>
                    Events are POSTed as signed JSON to an https:// URL on a
                    public address.
                </DialogDescription>
                <form onSubmit={submit} className="grid gap-4">
                    <FormField
                        id="webhook-url"
                        label="Endpoint URL"
                        error={form.errors.url}
                        required
                    >
                        <Input
                            id="webhook-url"
                            type="url"
                            value={form.data.url}
                            onChange={(e) =>
                                form.setData('url', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="webhook-description"
                        label="Description"
                        error={form.errors.description}
                    >
                        <Input
                            id="webhook-description"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                            placeholder="e.g. Accounting sync"
                        />
                    </FormField>
                    <fieldset className="space-y-2">
                        <legend className="mb-1 text-sm font-medium">
                            Events
                        </legend>
                        {events.map((event) => (
                            <div
                                key={event.value}
                                className="flex items-center gap-2"
                            >
                                <Checkbox
                                    id={`event-${event.value}`}
                                    checked={form.data.events.includes(
                                        event.value,
                                    )}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'events',
                                            checked === true
                                                ? [
                                                      ...form.data.events,
                                                      event.value,
                                                  ]
                                                : form.data.events.filter(
                                                      (value) =>
                                                          value !== event.value,
                                                  ),
                                        )
                                    }
                                />
                                <Label htmlFor={`event-${event.value}`}>
                                    {event.label}{' '}
                                    <code className="text-xs text-muted-foreground">
                                        {event.value}
                                    </code>
                                </Label>
                            </div>
                        ))}
                        {form.errors.events && (
                            <p className="text-sm text-destructive">
                                {form.errors.events}
                            </p>
                        )}
                    </fieldset>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="webhook-active"
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked === true)
                            }
                        />
                        <Label htmlFor="webhook-active">Active</Label>
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            Save webhook
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function SecretDialog({
    secret,
    onClose,
}: {
    secret: string;
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle>Signing secret</DialogTitle>
                <DialogDescription>
                    Copy it now. It will not be shown again. Use it to verify
                    the X-Webhook-Signature header.
                </DialogDescription>
                <div className="flex gap-2">
                    <Input
                        readOnly
                        value={secret}
                        className="font-mono"
                        aria-label="Signing secret"
                        onFocus={(e) => e.target.select()}
                    />
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label="Copy secret"
                        onClick={() =>
                            void navigator.clipboard
                                .writeText(secret)
                                .then(() => toast.success('Copied'))
                        }
                    >
                        <Copy aria-hidden />
                    </Button>
                </div>
                <pre className="overflow-x-auto rounded-lg bg-muted p-3 text-xs">
                    {`expected = "sha256=" + hex(hmac_sha256(secret,
    X-Webhook-Timestamp + "." + raw_body))
compare expected with X-Webhook-Signature (constant time)
reject if the timestamp is more than 5 minutes old`}
                </pre>
            </DialogContent>
        </Dialog>
    );
}

export default function Webhooks({
    endpoints,
    deliveries,
    events,
    can,
}: Props) {
    const format = useFormat();
    const [editing, setEditing] = useState<Endpoint | null | 'new'>(null);
    const [secret, setSecret] = useState<string | null>(null);

    useEffect(
        () =>
            router.on('flash', (event) => {
                const flashed = (event as CustomEvent).detail?.flash?.secret as
                    | { value: string }
                    | undefined;

                if (flashed?.value) {
                    setSecret(flashed.value);
                }
            }),
        [],
    );

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Webhooks"
                description="Send events to other systems as they happen."
                actions={
                    can.create ? (
                        <Button onClick={() => setEditing('new')}>
                            <Plus aria-hidden /> Add webhook
                        </Button>
                    ) : null
                }
            />
            {endpoints.length === 0 ? (
                <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                    <Webhook className="size-8 text-primary" aria-hidden />
                    No webhooks yet.
                </div>
            ) : (
                <div className="grid gap-4 lg:grid-cols-2">
                    {endpoints.map((endpoint) => (
                        <Card key={endpoint.id}>
                            <CardHeader className="flex flex-row items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <CardTitle className="truncate font-mono text-sm">
                                        {endpoint.url}
                                    </CardTitle>
                                    <CardDescription>
                                        {endpoint.description ??
                                            'No description'}
                                    </CardDescription>
                                </div>
                                <TonePill
                                    tone={
                                        !endpoint.is_active
                                            ? 'neutral'
                                            : endpoint.consecutive_failures > 0
                                              ? 'warning'
                                              : 'success'
                                    }
                                >
                                    {!endpoint.is_active
                                        ? 'Disabled'
                                        : endpoint.consecutive_failures > 0
                                          ? `${endpoint.consecutive_failures} failing`
                                          : 'Active'}
                                </TonePill>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex flex-wrap gap-1">
                                    {endpoint.events.map((event) => (
                                        <code
                                            key={event}
                                            className="rounded bg-muted px-1.5 py-0.5 text-xs"
                                        >
                                            {event}
                                        </code>
                                    ))}
                                </div>
                                {(can.edit || can.delete) && (
                                    <div className="flex flex-wrap gap-2 border-t pt-3">
                                        {can.edit && (
                                            <>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(
                                                            test.url(
                                                                endpoint.id,
                                                            ),
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Send aria-hidden /> Send
                                                    test
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        setEditing(endpoint)
                                                    }
                                                >
                                                    <Pencil aria-hidden /> Edit
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        router.post(
                                                            rotate.url(
                                                                endpoint.id,
                                                            ),
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <KeyRound aria-hidden /> New
                                                    secret
                                                </Button>
                                            </>
                                        )}
                                        {can.delete && (
                                            <ConfirmDelete
                                                action={destroy(endpoint.id)}
                                                title="Delete webhook?"
                                                description="Events will no longer be sent to this URL."
                                            />
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}
            <Card className="overflow-x-auto p-0">
                <CardHeader className="pt-6">
                    <CardTitle>Recent deliveries</CardTitle>
                    <CardDescription>
                        Failed deliveries are retried with increasing delays.
                    </CardDescription>
                </CardHeader>
                <table className="w-full text-sm">
                    <caption className="sr-only">Recent deliveries</caption>
                    <thead className="border-y text-left text-xs text-muted-foreground uppercase">
                        <tr>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Event
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Endpoint
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Status
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                When
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {deliveries.map((delivery) => (
                            <tr key={delivery.id}>
                                <td className="px-4 py-3 font-mono text-xs">
                                    {delivery.event}
                                </td>
                                <td className="max-w-64 truncate px-4 py-3 font-mono text-xs">
                                    {delivery.url}
                                </td>
                                <td className="px-4 py-3">
                                    <TonePill
                                        tone={deliveryTones[delivery.status]}
                                    >
                                        {delivery.status}
                                        {delivery.response_status &&
                                            ` · ${delivery.response_status}`}
                                        {delivery.attempts > 1 &&
                                            ` · ${delivery.attempts} tries`}
                                    </TonePill>
                                    {delivery.error && (
                                        <p className="mt-1 max-w-64 truncate text-xs text-muted-foreground">
                                            {delivery.error}
                                        </p>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-xs text-muted-foreground">
                                    {format.dateTime(delivery.created_at)}
                                </td>
                            </tr>
                        ))}
                        {deliveries.length === 0 && (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="px-4 py-8 text-center text-muted-foreground"
                                >
                                    Nothing sent yet.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </Card>
            {editing !== null && (
                <EndpointDialog
                    endpoint={editing === 'new' ? null : editing}
                    events={events}
                    onClose={() => setEditing(null)}
                />
            )}
            {secret && (
                <SecretDialog secret={secret} onClose={() => setSecret(null)} />
            )}
        </div>
    );
}

Webhooks.layout = {
    breadcrumbs: [{ title: 'Webhooks', href: index() }],
};
