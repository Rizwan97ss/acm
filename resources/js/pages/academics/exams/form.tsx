import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { index, show, store, update } from '@/routes/academics/exams';
import type { ExamSummary, Option, YearOption } from '@/types';

type Props = {
    exam: ExamSummary | null;
    years: YearOption[];
    terms: { id: number; name: string; academic_year_id: number }[];
    scales: Option[];
    currentYearId: number | null;
};

export default function ExamForm({
    exam,
    years,
    terms,
    scales,
    currentYearId,
}: Props) {
    const form = useForm({
        name: exam?.name ?? '',
        academic_year_id:
            exam?.academic_year_id ?? currentYearId ?? years[0]?.id ?? '',
        term_id: exam?.term_id ?? ('' as number | ''),
        grade_scale_id: exam?.grade_scale_id ?? scales[0]?.id ?? '',
        starts_on: exam?.starts_on ?? '',
        ends_on: exam?.ends_on ?? '',
    });
    const yearTerms = terms.filter(
        (term) => term.academic_year_id === Number(form.data.academic_year_id),
    );

    const submit = (event: FormEvent): void => {
        event.preventDefault();

        if (exam) {
            form.put(update.url(exam.id), { preserveScroll: true });
        } else {
            form.post(store.url(), { preserveScroll: true });
        }
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader title={exam ? `Edit ${exam.name}` : 'New exam'} />
            <form onSubmit={submit} className="max-w-3xl space-y-6">
                <Card>
                    <CardContent className="grid gap-5 sm:grid-cols-2">
                        <FormField
                            id="name"
                            label="Name"
                            error={form.errors.name}
                            required
                            className="sm:col-span-2"
                        >
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                placeholder="Mid-term examination"
                                required
                            />
                        </FormField>
                        <FormField
                            id="academic_year_id"
                            label="Academic year"
                            error={form.errors.academic_year_id}
                            required
                        >
                            <NativeSelect
                                id="academic_year_id"
                                value={form.data.academic_year_id}
                                onChange={(e) =>
                                    form.setData((data) => ({
                                        ...data,
                                        academic_year_id: Number(
                                            e.target.value,
                                        ),
                                        term_id: '',
                                    }))
                                }
                            >
                                {years.map((year) => (
                                    <option key={year.id} value={year.id}>
                                        {year.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                        <FormField
                            id="term_id"
                            label="Term"
                            error={form.errors.term_id}
                        >
                            <NativeSelect
                                id="term_id"
                                value={form.data.term_id}
                                onChange={(e) =>
                                    form.setData(
                                        'term_id',
                                        e.target.value === ''
                                            ? ''
                                            : Number(e.target.value),
                                    )
                                }
                            >
                                <option value="">Whole year</option>
                                {yearTerms.map((term) => (
                                    <option key={term.id} value={term.id}>
                                        {term.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                        <FormField
                            id="starts_on"
                            label="Starts"
                            error={form.errors.starts_on}
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
                            error={form.errors.ends_on}
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
                        <FormField
                            id="grade_scale_id"
                            label="Grading scale"
                            error={form.errors.grade_scale_id}
                            required
                        >
                            <NativeSelect
                                id="grade_scale_id"
                                value={form.data.grade_scale_id}
                                onChange={(e) =>
                                    form.setData(
                                        'grade_scale_id',
                                        Number(e.target.value),
                                    )
                                }
                            >
                                {scales.map((scale) => (
                                    <option key={scale.id} value={scale.id}>
                                        {scale.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                    </CardContent>
                </Card>
                {(form.errors as Record<string, string | undefined>).exam && (
                    <p className="text-sm text-destructive">
                        {(form.errors as Record<string, string>).exam}
                    </p>
                )}
                <div className="flex gap-2">
                    <Button type="submit" disabled={form.processing}>
                        {exam ? 'Save changes' : 'Create exam'}
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href={exam ? show(exam.id) : index()}>
                            Cancel
                        </Link>
                    </Button>
                </div>
            </form>
        </div>
    );
}

ExamForm.layout = {
    breadcrumbs: [{ title: 'Exams & results', href: index() }],
};
