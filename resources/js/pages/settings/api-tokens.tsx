import { Head, router, useForm } from '@inertiajs/react';
import { Copy, KeyRound } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import ConfirmDelete from '@/components/confirm-delete';
import FormField from '@/components/form-field';
import Heading from '@/components/heading';
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
import { useFormat } from '@/hooks/use-format';
import { destroy, index, store } from '@/routes/api-tokens';
import type { EnumOption } from '@/types/people';

type Token = {
    id: number;
    name: string;
    abilities: string[];
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string | null;
};

type Props = {
    tokens: Token[];
    abilities: EnumOption[];
};

function TokenDialog({
    token,
    onClose,
}: {
    token: string;
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle>Your new API token</DialogTitle>
                <DialogDescription>
                    Copy it now. It will not be shown again. Send it as an
                    Authorization: Bearer header.
                </DialogDescription>
                <div className="flex gap-2">
                    <Input
                        readOnly
                        value={token}
                        className="font-mono"
                        aria-label="API token"
                        onFocus={(e) => e.target.select()}
                    />
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label="Copy token"
                        onClick={() =>
                            void navigator.clipboard
                                .writeText(token)
                                .then(() => toast.success('Copied'))
                        }
                    >
                        <Copy aria-hidden />
                    </Button>
                </div>
                <pre className="overflow-x-auto rounded-lg bg-muted p-3 text-xs">
                    {`curl -H "Authorization: Bearer <token>" \\
     -H "Accept: application/json" \\
     ${window.location.origin}/api/v1/me`}
                </pre>
            </DialogContent>
        </Dialog>
    );
}

export default function ApiTokens({ tokens, abilities }: Props) {
    const format = useFormat();
    const [plainToken, setPlainToken] = useState<string | null>(null);
    const labels = Object.fromEntries(
        abilities.map((ability) => [ability.value, ability.label]),
    );

    const form = useForm({
        name: '',
        abilities: abilities.map((ability) => ability.value),
        expires_in_days: '90',
    });

    useEffect(
        () =>
            router.on('flash', (event) => {
                const flashed = (event as CustomEvent).detail?.flash?.token as
                    | { value: string }
                    | undefined;

                if (flashed?.value) {
                    setPlainToken(flashed.value);
                }
            }),
        [],
    );

    const toggleAbility = (value: string, checked: boolean): void => {
        form.setData(
            'abilities',
            checked
                ? [...form.data.abilities, value]
                : form.data.abilities.filter((item) => item !== value),
        );
    };

    const submit = (e: FormEvent): void => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            expires_in_days:
                data.expires_in_days === ''
                    ? null
                    : Number(data.expires_in_days),
        }));
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset('name'),
        });
    };

    return (
        <>
            <Head title="API tokens" />
            <h1 className="sr-only">API tokens</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Create API token"
                    description="Tokens let apps and scripts read school data as you. They only see what your account can see."
                />

                <form onSubmit={submit} className="space-y-5">
                    <FormField
                        id="token-name"
                        label="Name"
                        error={form.errors.name}
                        required
                    >
                        <Input
                            id="token-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="e.g. Reporting script"
                            required
                        />
                    </FormField>

                    <fieldset className="space-y-2">
                        <legend className="text-sm font-medium">Scopes</legend>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {abilities.map((ability) => (
                                <div
                                    key={ability.value}
                                    className="flex items-center gap-2"
                                >
                                    <Checkbox
                                        id={`ability-${ability.value}`}
                                        checked={form.data.abilities.includes(
                                            ability.value,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggleAbility(
                                                ability.value,
                                                checked === true,
                                            )
                                        }
                                    />
                                    <Label htmlFor={`ability-${ability.value}`}>
                                        {ability.label}
                                    </Label>
                                </div>
                            ))}
                        </div>
                        {form.errors.abilities && (
                            <p className="text-sm text-destructive">
                                {form.errors.abilities}
                            </p>
                        )}
                    </fieldset>

                    <FormField
                        id="token-expiry"
                        label="Expires"
                        error={form.errors.expires_in_days}
                    >
                        <NativeSelect
                            id="token-expiry"
                            value={form.data.expires_in_days}
                            onChange={(e) =>
                                form.setData('expires_in_days', e.target.value)
                            }
                        >
                            <option value="30">In 30 days</option>
                            <option value="90">In 90 days</option>
                            <option value="365">In 1 year</option>
                            <option value="">Never</option>
                        </NativeSelect>
                    </FormField>

                    <Button
                        type="submit"
                        disabled={
                            form.processing || form.data.abilities.length === 0
                        }
                    >
                        <KeyRound aria-hidden /> Create token
                    </Button>
                </form>
            </div>

            <div className="space-y-4">
                <Heading
                    variant="small"
                    title="Your tokens"
                    description="Revoke a token you no longer use or that may have leaked. Signed-in mobile devices appear here too."
                />

                {tokens.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                        No tokens yet.
                    </p>
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {tokens.map((token) => {
                            const expired =
                                token.expires_at !== null &&
                                new Date(token.expires_at) < new Date();

                            return (
                                <li
                                    key={token.id}
                                    className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex items-center gap-2">
                                            <span className="truncate font-medium">
                                                {token.name}
                                            </span>
                                            {expired && (
                                                <TonePill tone="neutral">
                                                    Expired
                                                </TonePill>
                                            )}
                                        </div>
                                        <div className="flex flex-wrap gap-1">
                                            {token.abilities.map((ability) => (
                                                <TonePill
                                                    key={ability}
                                                    tone="info"
                                                >
                                                    {ability === '*'
                                                        ? 'All scopes'
                                                        : (labels[ability] ??
                                                          ability)}
                                                </TonePill>
                                            ))}
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Last used{' '}
                                            {format.dateTime(
                                                token.last_used_at,
                                                'never',
                                            )}{' '}
                                            · Expires{' '}
                                            {format.date(
                                                token.expires_at,
                                                'never',
                                            )}
                                        </p>
                                    </div>
                                    <ConfirmDelete
                                        action={destroy(token.id)}
                                        title="Revoke this token?"
                                        description="Apps using it will stop working immediately."
                                        label="Revoke"
                                    />
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            {plainToken && (
                <TokenDialog
                    token={plainToken}
                    onClose={() => setPlainToken(null)}
                />
            )}
        </>
    );
}

ApiTokens.layout = {
    breadcrumbs: [{ title: 'API tokens', href: index() }],
};
