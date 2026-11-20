import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import type { AudienceValue } from '@/components/learning/audience-picker';
import AudiencePicker from '@/components/learning/audience-picker';
import { isoToLocalInput, localInputToIso } from '@/components/learning/format';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { index, roster, store, update } from '@/routes/academics/online-exams';
import type { OnlineExam } from '@/types/exams';
import type { TeachingOption } from '@/types/learning';

type Props = {
    exam: (OnlineExam & { student_ids: number[]; locked: boolean }) | null;
    options: TeachingOption[];
};

type Values = AudienceValue & {
    title: string;
    instructions: string;
    starts_at: string;
    ends_at: string;
    duration_minutes: number | '';
    total_marks: number | '';
    passing_marks: number | '';
    max_attempts: number;
    shuffle_questions: boolean;
    shuffle_options: boolean;
    negative_marking: boolean;
    show_rank: boolean;
};

const toggles: {
    key: keyof Pick<
        Values,
        | 'shuffle_questions'
        | 'shuffle_options'
        | 'negative_marking'
        | 'show_rank'
    >;
    label: string;
    hint: string;
}[] = [
    {
        key: 'shuffle_questions',
        label: 'Shuffle question order',
        hint: 'Each student gets the questions in a different order.',
    },
    {
        key: 'shuffle_options',
        label: 'Shuffle answer options',
        hint: 'MCQ options appear in a different order for each student.',
    },
    {
        key: 'negative_marking',
        label: 'Negative marking',
        hint: 'Wrong objective answers lose the marks you set per question. Unanswered questions never lose marks.',
    },
    {
        key: 'show_rank',
        label: 'Show students their rank',
        hint: 'Shown with published results, e.g. “3rd of 32”.',
    },
];

export default function OnlineExamForm({ exam, options }: Props) {
    const locked = exam?.locked ?? false;
    const form = useForm<Values>({
        school_class_id: exam?.school_class_id ?? '',
        section_id: exam?.section_id ?? '',
        subject_id: exam?.subject_id ?? '',
        student_ids: exam?.student_ids ?? [],
        title: exam?.title ?? '',
        instructions: exam?.instructions ?? '',
        starts_at: isoToLocalInput(exam?.starts_at),
        ends_at: isoToLocalInput(exam?.ends_at),
        duration_minutes: exam?.duration_minutes ?? 60,
        total_marks: exam?.total_marks ?? '',
        passing_marks: exam?.passing_marks ?? '',
        max_attempts: exam?.max_attempts ?? 1,
        shuffle_questions: exam?.shuffle_questions ?? true,
        shuffle_options: exam?.shuffle_options ?? true,
        negative_marking: exam?.negative_marking ?? false,
        show_rank: exam?.show_rank ?? false,
    });
    const errors = form.errors as Partial<Record<string, string>>;

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            section_id: data.section_id === '' ? null : data.section_id,
            starts_at: localInputToIso(data.starts_at),
            ends_at: localInputToIso(data.ends_at),
        }));

        if (exam) {
            form.put(update.url(exam.id));
        } else {
            form.post(store.url());
        }
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={exam ? `Settings: ${exam.title}` : 'New online exam'}
                description="Set who sits it, when, for how long and how it is marked. Then add questions and publish."
            />
            {locked && (
                <p className="max-w-3xl rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                    Students have started this exam. Only the title,
                    instructions, a later end time and the rank setting can
                    change now, so every student is treated the same.
                </p>
            )}
            {errors.exam && (
                <p className="max-w-3xl text-sm text-destructive">
                    {errors.exam}
                </p>
            )}
            <form
                onSubmit={submit}
                className="grid max-w-3xl gap-5 rounded-2xl border bg-card p-6 shadow-sm sm:grid-cols-2"
            >
                <fieldset
                    disabled={locked}
                    className="contents disabled:opacity-60"
                >
                    <AudiencePicker
                        options={options}
                        value={form.data}
                        onChange={(audience) =>
                            form.setData((data) => ({ ...data, ...audience }))
                        }
                        errors={errors}
                        allowStudents
                        rosterUrl={(query) => roster.url({ query })}
                    />
                </fieldset>
                <FormField
                    id="title"
                    label="Title"
                    required
                    error={errors.title}
                    className="sm:col-span-2"
                >
                    <Input
                        id="title"
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                        placeholder="e.g. Mid-term mathematics"
                    />
                </FormField>
                <FormField
                    id="instructions"
                    label="Instructions for students"
                    error={errors.instructions}
                    className="sm:col-span-2"
                >
                    <Textarea
                        id="instructions"
                        rows={3}
                        value={form.data.instructions}
                        onChange={(event) =>
                            form.setData('instructions', event.target.value)
                        }
                        placeholder="e.g. Answer all questions. Calculators are not allowed."
                    />
                </FormField>
                <FormField
                    id="starts_at"
                    label="Opens"
                    required
                    error={errors.starts_at}
                >
                    <Input
                        id="starts_at"
                        type="datetime-local"
                        disabled={locked}
                        value={form.data.starts_at}
                        onChange={(event) =>
                            form.setData('starts_at', event.target.value)
                        }
                    />
                </FormField>
                <FormField
                    id="ends_at"
                    label="Closes"
                    required
                    error={errors.ends_at}
                    description="No one can start after this, and attempts still open are handed in."
                >
                    <Input
                        id="ends_at"
                        type="datetime-local"
                        value={form.data.ends_at}
                        onChange={(event) =>
                            form.setData('ends_at', event.target.value)
                        }
                    />
                </FormField>
                <FormField
                    id="duration"
                    label="Duration (minutes)"
                    required
                    error={errors.duration_minutes}
                    description="Each student's timer starts when they begin; it never runs past the closing time."
                >
                    <Input
                        id="duration"
                        type="number"
                        min={1}
                        max={600}
                        disabled={locked}
                        value={form.data.duration_minutes}
                        onChange={(event) =>
                            form.setData(
                                'duration_minutes',
                                event.target.value === ''
                                    ? ''
                                    : Number(event.target.value),
                            )
                        }
                    />
                </FormField>
                <FormField
                    id="attempts"
                    label="Attempts allowed"
                    required
                    error={errors.max_attempts}
                    description="The best fully marked attempt counts."
                >
                    <Input
                        id="attempts"
                        type="number"
                        min={1}
                        max={5}
                        disabled={locked}
                        value={form.data.max_attempts}
                        onChange={(event) =>
                            form.setData(
                                'max_attempts',
                                Number(event.target.value),
                            )
                        }
                    />
                </FormField>
                <FormField
                    id="total"
                    label="Total marks"
                    required
                    error={errors.total_marks}
                    description="Your questions must add up to this before you can publish."
                >
                    <Input
                        id="total"
                        type="number"
                        min={1}
                        max={1000}
                        step={0.5}
                        disabled={locked}
                        value={form.data.total_marks}
                        onChange={(event) =>
                            form.setData(
                                'total_marks',
                                event.target.value === ''
                                    ? ''
                                    : Number(event.target.value),
                            )
                        }
                    />
                </FormField>
                <FormField
                    id="passing"
                    label="Passing marks"
                    required
                    error={errors.passing_marks}
                >
                    <Input
                        id="passing"
                        type="number"
                        min={0}
                        step={0.5}
                        disabled={locked}
                        value={form.data.passing_marks}
                        onChange={(event) =>
                            form.setData(
                                'passing_marks',
                                event.target.value === ''
                                    ? ''
                                    : Number(event.target.value),
                            )
                        }
                    />
                </FormField>
                <div className="grid gap-3 sm:col-span-2 sm:grid-cols-2">
                    {toggles.map((toggle) => (
                        <div
                            key={toggle.key}
                            className="flex items-start gap-3 rounded-xl border p-3"
                        >
                            <Checkbox
                                id={toggle.key}
                                className="mt-0.5"
                                disabled={locked && toggle.key !== 'show_rank'}
                                checked={form.data[toggle.key]}
                                onCheckedChange={(checked) =>
                                    form.setData(toggle.key, checked === true)
                                }
                            />
                            <div>
                                <Label htmlFor={toggle.key}>
                                    {toggle.label}
                                </Label>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {toggle.hint}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
                <div className="flex gap-2 sm:col-span-2">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        {exam ? 'Save settings' : 'Create and add questions'}
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => history.back()}
                    >
                        Cancel
                    </Button>
                </div>
            </form>
        </div>
    );
}

OnlineExamForm.layout = {
    breadcrumbs: [{ title: 'Online exams', href: index() }],
};
