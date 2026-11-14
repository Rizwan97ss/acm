import { useForm } from '@inertiajs/react';
import { Search, UserPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { cn } from '@/lib/utils';
import { search as searchGuardians } from '@/routes/people/guardians';
import { store } from '@/routes/people/students/guardians';
import type { EnumOption, Guardian } from '@/types';

type Props = {
    studentId: number;
    relationships: EnumOption[];
    canCreate: boolean;
};

type Mode = 'existing' | 'new';

/**
 * Link a guardian to a student: search existing guardians (siblings share guardians)
 * or create a new one inline.
 */
export default function LinkGuardianDialog({
    studentId,
    relationships,
    canCreate,
}: Props) {
    const [open, setOpen] = useState(false);
    const [mode, setMode] = useState<Mode>('existing');
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Guardian[]>([]);

    const form = useForm({
        guardian_id: null as number | null,
        relationship: relationships[0]?.value ?? 'guardian',
        is_primary: false,
        is_emergency_contact: true,
        guardian: {
            first_name: '',
            last_name: '',
            email: '',
            phone: '',
            occupation: '',
            address: '',
        },
    });
    const errors = form.errors as Record<string, string | undefined>;

    useEffect(() => {
        if (mode !== 'existing' || query.trim().length < 2) {
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            fetch(searchGuardians.url({ query: { q: query.trim() } }), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then((response) => (response.ok ? response.json() : []))
                .then((data: Guardian[]) => setResults(data))
                .catch(() => undefined);
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [query, mode]);

    const setGuardianField = (
        field: keyof typeof form.data.guardian,
        value: string,
    ): void =>
        form.setData('guardian', { ...form.data.guardian, [field]: value });

    const submit = (event: FormEvent): void => {
        event.preventDefault();

        form.transform((data) =>
            mode === 'existing'
                ? {
                      guardian_id: data.guardian_id,
                      relationship: data.relationship,
                      is_primary: data.is_primary,
                      is_emergency_contact: data.is_emergency_contact,
                  }
                : { ...data, guardian_id: null },
        );
        form.post(store.url(studentId), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setQuery('');
                setResults([]);
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <UserPlus aria-hidden /> Add guardian
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogTitle>Add guardian</DialogTitle>
                <DialogDescription>
                    Link an existing guardian (for siblings) or create a new
                    one.
                </DialogDescription>

                {canCreate && (
                    <div
                        className="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1"
                        role="tablist"
                    >
                        {(['existing', 'new'] as Mode[]).map((option) => (
                            <button
                                key={option}
                                type="button"
                                role="tab"
                                aria-selected={mode === option}
                                onClick={() => setMode(option)}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm',
                                    mode === option &&
                                        'bg-background font-medium shadow-sm',
                                )}
                            >
                                {option === 'existing'
                                    ? 'Existing guardian'
                                    : 'New guardian'}
                            </button>
                        ))}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-4">
                    {mode === 'existing' ? (
                        <div className="space-y-2">
                            <div className="relative">
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden
                                />
                                <Input
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder="Name, email or exact phone"
                                    aria-label="Search guardians"
                                    className="pl-8"
                                />
                            </div>
                            <ul
                                className="max-h-48 divide-y overflow-y-auto rounded-md border"
                                role="listbox"
                                aria-label="Matching guardians"
                            >
                                {results.map((guardian) => (
                                    <li key={guardian.id}>
                                        <button
                                            type="button"
                                            role="option"
                                            aria-selected={
                                                form.data.guardian_id ===
                                                guardian.id
                                            }
                                            onClick={() =>
                                                form.setData(
                                                    'guardian_id',
                                                    guardian.id,
                                                )
                                            }
                                            className={cn(
                                                'flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-muted',
                                                form.data.guardian_id ===
                                                    guardian.id &&
                                                    'bg-primary/10',
                                            )}
                                        >
                                            <span className="font-medium">
                                                {guardian.full_name}
                                            </span>
                                            <span className="truncate text-xs text-muted-foreground">
                                                {guardian.email ??
                                                    guardian.phone}
                                            </span>
                                        </button>
                                    </li>
                                ))}
                                {results.length === 0 && (
                                    <li className="px-3 py-4 text-center text-sm text-muted-foreground">
                                        {query.trim().length < 2
                                            ? 'Type at least two characters.'
                                            : 'No guardians found.'}
                                    </li>
                                )}
                            </ul>
                            {errors.guardian_id && (
                                <p className="text-sm text-destructive">
                                    {errors.guardian_id}
                                </p>
                            )}
                        </div>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2">
                            <FormField
                                id="g-first"
                                label="First name"
                                error={errors['guardian.first_name']}
                                required
                            >
                                <Input
                                    id="g-first"
                                    value={form.data.guardian.first_name}
                                    onChange={(e) =>
                                        setGuardianField(
                                            'first_name',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </FormField>
                            <FormField
                                id="g-last"
                                label="Last name"
                                error={errors['guardian.last_name']}
                                required
                            >
                                <Input
                                    id="g-last"
                                    value={form.data.guardian.last_name}
                                    onChange={(e) =>
                                        setGuardianField(
                                            'last_name',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </FormField>
                            <FormField
                                id="g-phone"
                                label="Phone"
                                error={errors['guardian.phone']}
                                required
                            >
                                <Input
                                    id="g-phone"
                                    type="tel"
                                    value={form.data.guardian.phone}
                                    onChange={(e) =>
                                        setGuardianField(
                                            'phone',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </FormField>
                            <FormField
                                id="g-email"
                                label="Email"
                                error={errors['guardian.email']}
                            >
                                <Input
                                    id="g-email"
                                    type="email"
                                    value={form.data.guardian.email}
                                    onChange={(e) =>
                                        setGuardianField(
                                            'email',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="g-occupation"
                                label="Occupation"
                                error={errors['guardian.occupation']}
                                className="sm:col-span-2"
                            >
                                <Input
                                    id="g-occupation"
                                    value={form.data.guardian.occupation}
                                    onChange={(e) =>
                                        setGuardianField(
                                            'occupation',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        </div>
                    )}

                    <FormField
                        id="g-relationship"
                        label="Relationship"
                        error={errors.relationship}
                        required
                    >
                        <NativeSelect
                            id="g-relationship"
                            value={form.data.relationship}
                            onChange={(e) =>
                                form.setData('relationship', e.target.value)
                            }
                        >
                            {relationships.map((relationship) => (
                                <option
                                    key={relationship.value}
                                    value={relationship.value}
                                >
                                    {relationship.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <div className="flex flex-wrap gap-4">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="g-primary"
                                checked={form.data.is_primary}
                                onCheckedChange={(checked) =>
                                    form.setData('is_primary', checked === true)
                                }
                            />
                            <Label htmlFor="g-primary">Primary guardian</Label>
                        </div>
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="g-emergency"
                                checked={form.data.is_emergency_contact}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'is_emergency_contact',
                                        checked === true,
                                    )
                                }
                            />
                            <Label htmlFor="g-emergency">
                                Emergency contact
                            </Label>
                        </div>
                    </div>
                    <div className="flex justify-end">
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                (mode === 'existing' &&
                                    form.data.guardian_id === null)
                            }
                        >
                            Link guardian
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
