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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/academics/classes';
import type { Option, SectionData, SubjectOption } from '@/types';

export type ClassFormOptions = {
    subjects: SubjectOption[];
    teachers: Option[];
};

type ClassFormData = {
    name: string;
    level: number;
    description: string;
    sections: SectionData[];
    subjects: { id: number; periods_per_week: number }[];
};

type Props = ClassFormOptions & {
    initial?: ClassFormData;
    submitUrl: string;
    method: 'post' | 'put';
    submitLabel: string;
};

const nextSectionName = (sections: SectionData[]): string =>
    String.fromCharCode(65 + Math.min(sections.length, 25));

export default function ClassForm({
    subjects,
    teachers,
    initial,
    submitUrl,
    method,
    submitLabel,
}: Props) {
    const form = useForm<ClassFormData>(
        initial ?? {
            name: '',
            level: 1,
            description: '',
            sections: [
                {
                    id: null,
                    name: 'A',
                    capacity: 30,
                    room: null,
                    class_teacher_id: null,
                },
            ],
            subjects: [],
        },
    );
    const errors = form.errors as Record<string, string | undefined>;
    const allocation = new Map(
        form.data.subjects.map((subject) => [
            subject.id,
            subject.periods_per_week,
        ]),
    );
    const totalPeriods = form.data.subjects.reduce(
        (sum, subject) => sum + subject.periods_per_week,
        0,
    );

    const updateSection = (i: number, changes: Partial<SectionData>): void =>
        form.setData(
            'sections',
            form.data.sections.map((section, index) =>
                index === i ? { ...section, ...changes } : section,
            ),
        );

    const toggleSubject = (id: number, checked: boolean): void =>
        form.setData(
            'subjects',
            checked
                ? [...form.data.subjects, { id, periods_per_week: 4 }]
                : form.data.subjects.filter((subject) => subject.id !== id),
        );

    const setPeriods = (id: number, periods: number): void =>
        form.setData(
            'subjects',
            form.data.subjects.map((subject) =>
                subject.id === id
                    ? { ...subject, periods_per_week: periods }
                    : subject,
            ),
        );

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.submit(method, submitUrl, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Class</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-5 sm:grid-cols-[2fr_1fr]">
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
                            placeholder="Grade 5"
                            required
                        />
                    </FormField>
                    <FormField
                        id="level"
                        label="Level"
                        error={errors.level}
                        required
                        description="Sort order, e.g. 5."
                    >
                        <Input
                            id="level"
                            type="number"
                            min={0}
                            max={100}
                            value={form.data.level}
                            onChange={(e) =>
                                form.setData('level', Number(e.target.value))
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="description"
                        label="Description"
                        error={errors.description}
                        className="sm:col-span-2"
                    >
                        <Textarea
                            id="description"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                            rows={2}
                        />
                    </FormField>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Sections</CardTitle>
                    <CardDescription>
                        Sections used in a timetable cannot be removed.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    {form.data.sections.map((section, i) => (
                        <fieldset
                            key={section.id ?? `new-${i}`}
                            className="grid items-start gap-3 rounded-lg border p-3 sm:grid-cols-[6rem_6rem_7rem_1fr_auto]"
                        >
                            <legend className="sr-only">
                                Section {section.name}
                            </legend>
                            <FormField
                                id={`section-${i}-name`}
                                label="Name"
                                error={errors[`sections.${i}.name`]}
                            >
                                <Input
                                    id={`section-${i}-name`}
                                    value={section.name}
                                    onChange={(e) =>
                                        updateSection(i, {
                                            name: e.target.value,
                                        })
                                    }
                                    required
                                />
                            </FormField>
                            <FormField
                                id={`section-${i}-capacity`}
                                label="Capacity"
                                error={errors[`sections.${i}.capacity`]}
                            >
                                <Input
                                    id={`section-${i}-capacity`}
                                    type="number"
                                    min={1}
                                    value={section.capacity ?? ''}
                                    onChange={(e) =>
                                        updateSection(i, {
                                            capacity:
                                                e.target.value === ''
                                                    ? null
                                                    : Number(e.target.value),
                                        })
                                    }
                                />
                            </FormField>
                            <FormField
                                id={`section-${i}-room`}
                                label="Room"
                                error={errors[`sections.${i}.room`]}
                            >
                                <Input
                                    id={`section-${i}-room`}
                                    value={section.room ?? ''}
                                    onChange={(e) =>
                                        updateSection(i, {
                                            room: e.target.value || null,
                                        })
                                    }
                                />
                            </FormField>
                            <FormField
                                id={`section-${i}-teacher`}
                                label="Class teacher"
                                error={errors[`sections.${i}.class_teacher_id`]}
                            >
                                <NativeSelect
                                    id={`section-${i}-teacher`}
                                    value={section.class_teacher_id ?? ''}
                                    onChange={(e) =>
                                        updateSection(i, {
                                            class_teacher_id:
                                                e.target.value === ''
                                                    ? null
                                                    : Number(e.target.value),
                                        })
                                    }
                                >
                                    <option value="">Not assigned</option>
                                    {teachers.map((teacher) => (
                                        <option
                                            key={teacher.id}
                                            value={teacher.id}
                                        >
                                            {teacher.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="sm:mt-6"
                                aria-label={`Remove section ${section.name}`}
                                onClick={() =>
                                    form.setData(
                                        'sections',
                                        form.data.sections.filter(
                                            (_, index) => index !== i,
                                        ),
                                    )
                                }
                            >
                                <Trash2 aria-hidden />
                            </Button>
                        </fieldset>
                    ))}
                    <InputError message={errors.sections} />
                    <Button
                        type="button"
                        variant="outline"
                        disabled={form.data.sections.length >= 26}
                        onClick={() =>
                            form.setData('sections', [
                                ...form.data.sections,
                                {
                                    id: null,
                                    name: nextSectionName(form.data.sections),
                                    capacity: 30,
                                    room: null,
                                    class_teacher_id: null,
                                },
                            ])
                        }
                    >
                        <Plus aria-hidden /> Add section
                    </Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Subjects</CardTitle>
                    <CardDescription>
                        Choose the subjects taught in this class and their
                        weekly periods ({totalPeriods} in total).
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-2">
                    {subjects.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Create subjects first.
                        </p>
                    )}
                    <div className="grid gap-2 sm:grid-cols-2">
                        {subjects.map((subject) => {
                            const selected = allocation.has(subject.id);

                            return (
                                <div
                                    key={subject.id}
                                    className="flex items-center gap-3 rounded-lg border px-3 py-2"
                                >
                                    <Checkbox
                                        id={`subject-${subject.id}`}
                                        checked={selected}
                                        onCheckedChange={(checked) =>
                                            toggleSubject(
                                                subject.id,
                                                checked === true,
                                            )
                                        }
                                    />
                                    <label
                                        htmlFor={`subject-${subject.id}`}
                                        className="flex min-w-0 flex-1 items-center gap-2 text-sm"
                                    >
                                        <span
                                            className="size-2.5 shrink-0 rounded-full"
                                            style={{
                                                backgroundColor:
                                                    subject.color ??
                                                    'transparent',
                                            }}
                                            aria-hidden
                                        />
                                        <span className="truncate">
                                            {subject.name}
                                        </span>
                                    </label>
                                    {selected && (
                                        <Input
                                            type="number"
                                            min={0}
                                            max={40}
                                            aria-label={`${subject.name} periods per week`}
                                            value={allocation.get(subject.id)}
                                            onChange={(e) =>
                                                setPeriods(
                                                    subject.id,
                                                    Number(e.target.value),
                                                )
                                            }
                                            className="h-8 w-16"
                                        />
                                    )}
                                </div>
                            );
                        })}
                    </div>
                    <InputError message={errors.subjects} />
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
