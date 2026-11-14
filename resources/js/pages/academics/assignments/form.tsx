import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index, show, store, update } from '@/routes/academics/assignments';
import type { AssignmentSummary, EnumOption, SectionOption } from '@/types';

type Props = {
    assignment: AssignmentSummary | null;
    sections: SectionOption[];
    subjectsByClass: Record<string, { id: number; name: string }[]>;
    types: EnumOption[];
    hasCurrentYear: boolean;
};

export default function AssignmentForm({
    assignment,
    sections,
    subjectsByClass,
    types,
    hasCurrentYear,
}: Props) {
    const form = useForm({
        section_id: assignment?.section_id ?? sections[0]?.id ?? '',
        subject_id: assignment?.subject_id ?? ('' as number | ''),
        type: assignment?.type ?? 'homework',
        title: assignment?.title ?? '',
        description: assignment?.description ?? '',
        due_on: assignment?.due_on ?? '',
        max_marks:
            assignment?.max_marks === null ||
            assignment?.max_marks === undefined
                ? ''
                : String(assignment.max_marks),
    });
    const section = sections.find(
        (item) => item.id === Number(form.data.section_id),
    );
    const subjects = section
        ? (subjectsByClass[String(section.school_class_id)] ?? [])
        : [];

    const submit = (event: FormEvent): void => {
        event.preventDefault();

        if (assignment) {
            form.put(update.url(assignment.id), { preserveScroll: true });
        } else {
            form.post(store.url(), { preserveScroll: true });
        }
    };

    if (!hasCurrentYear || sections.length === 0) {
        return (
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Set work" />
                <Card>
                    <CardHeader>
                        <CardTitle>No sections available</CardTitle>
                        <CardDescription>
                            You can set work for sections you teach in the
                            current academic year.
                        </CardDescription>
                    </CardHeader>
                </Card>
            </div>
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={assignment ? `Edit ${assignment.title}` : 'Set work'}
            />
            <form onSubmit={submit} className="max-w-3xl space-y-6">
                <Card>
                    <CardContent className="grid gap-5 sm:grid-cols-2">
                        <FormField
                            id="section_id"
                            label="Section"
                            error={form.errors.section_id}
                            required
                        >
                            <NativeSelect
                                id="section_id"
                                value={form.data.section_id}
                                onChange={(e) =>
                                    form.setData((data) => ({
                                        ...data,
                                        section_id: Number(e.target.value),
                                        subject_id: '',
                                    }))
                                }
                            >
                                {sections.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                        <FormField
                            id="subject_id"
                            label="Subject"
                            error={form.errors.subject_id}
                            required
                        >
                            <NativeSelect
                                id="subject_id"
                                value={form.data.subject_id}
                                onChange={(e) =>
                                    form.setData(
                                        'subject_id',
                                        Number(e.target.value),
                                    )
                                }
                                required
                            >
                                <option value="" disabled>
                                    Select…
                                </option>
                                {subjects.map((subject) => (
                                    <option key={subject.id} value={subject.id}>
                                        {subject.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                        <FormField
                            id="type"
                            label="Type"
                            error={form.errors.type}
                            required
                        >
                            <NativeSelect
                                id="type"
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
                        <FormField
                            id="due_on"
                            label="Due on"
                            error={form.errors.due_on}
                            required
                        >
                            <Input
                                id="due_on"
                                type="date"
                                value={form.data.due_on}
                                onChange={(e) =>
                                    form.setData('due_on', e.target.value)
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            id="title"
                            label="Title"
                            error={form.errors.title}
                            required
                            className="sm:col-span-2"
                        >
                            <Input
                                id="title"
                                value={form.data.title}
                                onChange={(e) =>
                                    form.setData('title', e.target.value)
                                }
                                required
                                maxLength={200}
                            />
                        </FormField>
                        <FormField
                            id="description"
                            label="Instructions"
                            error={form.errors.description}
                            required
                            className="sm:col-span-2"
                        >
                            <Textarea
                                id="description"
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                                rows={6}
                                required
                            />
                        </FormField>
                        <FormField
                            id="max_marks"
                            label="Marked out of"
                            error={form.errors.max_marks}
                            description="Leave empty for feedback only."
                        >
                            <Input
                                id="max_marks"
                                type="number"
                                min={1}
                                value={form.data.max_marks}
                                onChange={(e) =>
                                    form.setData('max_marks', e.target.value)
                                }
                            />
                        </FormField>
                    </CardContent>
                </Card>
                <div className="flex gap-2">
                    <Button type="submit" disabled={form.processing}>
                        {assignment ? 'Save changes' : 'Set work'}
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href={assignment ? show(assignment.id) : index()}>
                            Cancel
                        </Link>
                    </Button>
                </div>
            </form>
        </div>
    );
}

AssignmentForm.layout = {
    breadcrumbs: [{ title: 'Assignments', href: index() }],
};
