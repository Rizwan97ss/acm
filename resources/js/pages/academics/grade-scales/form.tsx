import { Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, store, update } from '@/routes/academics/grade-scales';
import type { GradeBandData } from '@/types';

type ScaleData = {
    id: number;
    name: string;
    is_default: boolean;
    bands: GradeBandData[];
};

export default function GradeScaleForm({
    scale,
    template,
}: {
    scale: ScaleData | null;
    template: ScaleData | null;
}) {
    const form = useForm({
        name: scale?.name ?? '',
        is_default: scale?.is_default ?? false,
        bands: scale?.bands ?? template?.bands ?? [],
    });
    const errors = form.errors as Record<string, string | undefined>;

    const updateBand = (i: number, changes: Partial<GradeBandData>): void =>
        form.setData(
            'bands',
            form.data.bands.map((band, index) =>
                index === i ? { ...band, ...changes } : band,
            ),
        );

    const submit = (event: FormEvent): void => {
        event.preventDefault();

        if (scale) {
            form.put(update.url(scale.id), { preserveScroll: true });
        } else {
            form.post(store.url(), { preserveScroll: true });
        }
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={scale ? `Edit ${scale.name}` : 'New grading scale'}
                description="Each grade applies from its minimum percentage up to the next grade."
            />
            <form onSubmit={submit} className="max-w-3xl space-y-6">
                <Card>
                    <CardContent className="grid gap-5 sm:grid-cols-[1fr_auto] sm:items-end">
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
                                required
                            />
                        </FormField>
                        <div className="flex items-center gap-2 pb-2">
                            <Checkbox
                                id="is_default"
                                checked={form.data.is_default}
                                onCheckedChange={(checked) =>
                                    form.setData('is_default', checked === true)
                                }
                            />
                            <Label htmlFor="is_default">
                                Default for new exams
                            </Label>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Grades</CardTitle>
                        <CardDescription>
                            The lowest grade must start at 0%.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {form.data.bands.map((band, i) => (
                            <fieldset
                                key={i}
                                className="grid items-start gap-2 rounded-xl border p-3 sm:grid-cols-[5rem_7rem_6rem_1fr_auto_auto]"
                            >
                                <legend className="sr-only">
                                    Grade {band.grade}
                                </legend>
                                <div>
                                    <Input
                                        aria-label="Grade"
                                        value={band.grade}
                                        onChange={(e) =>
                                            updateBand(i, {
                                                grade: e.target.value,
                                            })
                                        }
                                        required
                                    />
                                    <InputError
                                        message={errors[`bands.${i}.grade`]}
                                    />
                                </div>
                                <div>
                                    <Input
                                        aria-label="From percentage"
                                        type="number"
                                        min={0}
                                        max={100}
                                        step="0.01"
                                        value={band.min_percentage}
                                        onChange={(e) =>
                                            updateBand(i, {
                                                min_percentage: Number(
                                                    e.target.value,
                                                ),
                                            })
                                        }
                                        required
                                    />
                                    <InputError
                                        message={
                                            errors[`bands.${i}.min_percentage`]
                                        }
                                    />
                                </div>
                                <Input
                                    aria-label="Grade point"
                                    type="number"
                                    min={0}
                                    max={10}
                                    step="0.01"
                                    placeholder="GPA"
                                    value={band.grade_point ?? ''}
                                    onChange={(e) =>
                                        updateBand(i, {
                                            grade_point:
                                                e.target.value === ''
                                                    ? null
                                                    : Number(e.target.value),
                                        })
                                    }
                                />
                                <Input
                                    aria-label="Remark"
                                    placeholder="Remark"
                                    value={band.remark ?? ''}
                                    onChange={(e) =>
                                        updateBand(i, {
                                            remark: e.target.value || null,
                                        })
                                    }
                                />
                                <label className="flex items-center gap-2 pt-2 text-sm">
                                    <Checkbox
                                        checked={band.is_passing}
                                        onCheckedChange={(checked) =>
                                            updateBand(i, {
                                                is_passing: checked === true,
                                            })
                                        }
                                    />
                                    Pass
                                </label>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Remove grade ${band.grade}`}
                                    onClick={() =>
                                        form.setData(
                                            'bands',
                                            form.data.bands.filter(
                                                (_, index) => index !== i,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 aria-hidden />
                                </Button>
                            </fieldset>
                        ))}
                        <InputError message={errors.bands} />
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                form.setData('bands', [
                                    ...form.data.bands,
                                    {
                                        grade: '',
                                        min_percentage: 0,
                                        grade_point: null,
                                        remark: null,
                                        is_passing: true,
                                    },
                                ])
                            }
                        >
                            <Plus aria-hidden /> Add grade
                        </Button>
                    </CardContent>
                </Card>
                <div className="flex gap-2">
                    <Button type="submit" disabled={form.processing}>
                        Save scale
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href={index()}>Cancel</Link>
                    </Button>
                </div>
            </form>
        </div>
    );
}

GradeScaleForm.layout = {
    breadcrumbs: [{ title: 'Grading scales', href: index() }],
};
