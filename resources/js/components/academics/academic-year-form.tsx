import { Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/academics/academic-years';
import type { Term } from '@/types';

type AcademicYearFormData = {
    name: string;
    starts_on: string;
    ends_on: string;
    terms: Term[];
};

type Props = {
    initial?: AcademicYearFormData;
    submitUrl: string;
    method: 'post' | 'put';
    submitLabel: string;
};

export default function AcademicYearForm({
    initial,
    submitUrl,
    method,
    submitLabel,
}: Props) {
    const form = useForm<AcademicYearFormData>(
        initial ?? { name: '', starts_on: '', ends_on: '', terms: [] },
    );
    const errors = form.errors as Record<string, string | undefined>;

    const updateTerm = (index: number, changes: Partial<Term>): void => {
        form.setData(
            'terms',
            form.data.terms.map((term, i) =>
                i === index ? { ...term, ...changes } : term,
            ),
        );
    };

    const addTerm = (): void => {
        form.setData('terms', [
            ...form.data.terms,
            {
                id: null,
                name: `Term ${form.data.terms.length + 1}`,
                starts_on: '',
                ends_on: '',
            },
        ]);
    };

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.submit(method, submitUrl, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="max-w-3xl space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Year</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-5 sm:grid-cols-3">
                    <FormField
                        id="name"
                        label="Name"
                        error={errors.name}
                        required
                    >
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="2026-2027"
                            required
                        />
                    </FormField>
                    <FormField
                        id="starts_on"
                        label="Starts"
                        error={errors.starts_on}
                        required
                    >
                        <Input
                            id="starts_on"
                            type="date"
                            value={form.data.starts_on}
                            onChange={(e) =>
                                form.setData('starts_on', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="ends_on"
                        label="Ends"
                        error={errors.ends_on}
                        required
                    >
                        <Input
                            id="ends_on"
                            type="date"
                            value={form.data.ends_on}
                            onChange={(e) =>
                                form.setData('ends_on', e.target.value)
                            }
                            required
                        />
                    </FormField>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Terms</CardTitle>
                    <CardDescription>
                        Terms must fall inside the year and must not overlap.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {form.data.terms.map((term, i) => (
                        <fieldset
                            key={term.id ?? `new-${i}`}
                            className="grid items-start gap-3 rounded-lg border p-3 sm:grid-cols-[1fr_1fr_1fr_auto]"
                        >
                            <legend className="sr-only">Term {i + 1}</legend>
                            <FormField
                                id={`term-${i}-name`}
                                label="Name"
                                error={errors[`terms.${i}.name`]}
                            >
                                <Input
                                    id={`term-${i}-name`}
                                    value={term.name}
                                    onChange={(e) =>
                                        updateTerm(i, { name: e.target.value })
                                    }
                                    required
                                />
                            </FormField>
                            <FormField
                                id={`term-${i}-starts`}
                                label="Starts"
                                error={errors[`terms.${i}.starts_on`]}
                            >
                                <Input
                                    id={`term-${i}-starts`}
                                    type="date"
                                    value={term.starts_on}
                                    onChange={(e) =>
                                        updateTerm(i, {
                                            starts_on: e.target.value,
                                        })
                                    }
                                    required
                                />
                            </FormField>
                            <FormField
                                id={`term-${i}-ends`}
                                label="Ends"
                                error={errors[`terms.${i}.ends_on`]}
                            >
                                <Input
                                    id={`term-${i}-ends`}
                                    type="date"
                                    value={term.ends_on}
                                    onChange={(e) =>
                                        updateTerm(i, {
                                            ends_on: e.target.value,
                                        })
                                    }
                                    required
                                />
                            </FormField>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="sm:mt-6"
                                aria-label={`Remove ${term.name}`}
                                onClick={() =>
                                    form.setData(
                                        'terms',
                                        form.data.terms.filter(
                                            (_, index) => index !== i,
                                        ),
                                    )
                                }
                            >
                                <Trash2 aria-hidden />
                            </Button>
                        </fieldset>
                    ))}
                    <InputError message={errors.terms} />
                    <Button
                        type="button"
                        variant="outline"
                        onClick={addTerm}
                        disabled={form.data.terms.length >= 6}
                    >
                        <Plus aria-hidden /> Add term
                    </Button>
                </CardContent>
            </Card>

            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>
                    {submitLabel}
                </Button>
                <Button variant="ghost" asChild>
                    <Link href={index()}>Cancel</Link>
                </Button>
            </div>
        </form>
    );
}
