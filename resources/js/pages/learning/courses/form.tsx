import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import type { AudienceValue } from '@/components/learning/audience-picker';
import AudiencePicker from '@/components/learning/audience-picker';
import { isoToLocalInput, localInputToIso } from '@/components/learning/format';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { index, store, update } from '@/routes/learning/courses';
import type {
    CourseSummary,
    PublishStatus,
    TeachingOption,
} from '@/types/learning';

type Props = {
    course: (CourseSummary & { student_ids: number[] }) | null;
    options: TeachingOption[];
};

type CourseForm = AudienceValue & {
    title: string;
    description: string;
    status: PublishStatus;
    publish_at: string;
    student_ids: number[];
};

export default function CourseForm({ course, options }: Props) {
    const form = useForm<CourseForm>({
        school_class_id: course?.school_class_id ?? '',
        section_id: course?.section_id ?? '',
        subject_id: course?.subject_id ?? '',
        student_ids: course?.student_ids ?? [],
        title: course?.title ?? '',
        description: course?.description ?? '',
        status: course?.status ?? 'draft',
        publish_at: isoToLocalInput(course?.publish_at),
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            section_id: data.section_id === '' ? null : data.section_id,
            publish_at: localInputToIso(data.publish_at),
        }));

        if (course) {
            form.put(update.url(course.id));
        } else {
            form.post(store.url());
        }
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={course ? `Edit ${course.title}` : 'New course'}
                description="A course is the learning space for one subject in a class. Students in the chosen class (or section) see it once it is published."
            />
            <form
                onSubmit={submit}
                className="grid max-w-3xl gap-5 rounded-2xl border bg-card p-6 shadow-sm sm:grid-cols-2"
            >
                <AudiencePicker
                    options={options}
                    value={form.data}
                    onChange={(audience) =>
                        form.setData((data) => ({
                            ...data,
                            ...audience,
                            student_ids: audience.student_ids ?? [],
                        }))
                    }
                    errors={form.errors}
                    allowStudents
                />
                <FormField
                    id="title"
                    label="Course title"
                    required
                    error={form.errors.title}
                    className="sm:col-span-2"
                >
                    <Input
                        id="title"
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                        placeholder="e.g. Fractions and decimals"
                    />
                </FormField>
                <FormField
                    id="description"
                    label="Description"
                    error={form.errors.description}
                    className="sm:col-span-2"
                >
                    <Textarea
                        id="description"
                        rows={4}
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData('description', event.target.value)
                        }
                        placeholder="What students will learn in this course."
                    />
                </FormField>
                <FormField
                    id="status"
                    label="Status"
                    error={form.errors.status}
                    description="Drafts are only visible to teachers."
                >
                    <NativeSelect
                        id="status"
                        value={form.data.status}
                        onChange={(event) =>
                            form.setData(
                                'status',
                                event.target.value as PublishStatus,
                            )
                        }
                    >
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </NativeSelect>
                </FormField>
                <FormField
                    id="publish_at"
                    label="Publish on"
                    error={form.errors.publish_at}
                    description="Optional: students see it from this time."
                >
                    <Input
                        id="publish_at"
                        type="datetime-local"
                        value={form.data.publish_at}
                        onChange={(event) =>
                            form.setData('publish_at', event.target.value)
                        }
                    />
                </FormField>
                <div className="flex gap-2 sm:col-span-2">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        {course ? 'Save course' : 'Create course'}
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

CourseForm.layout = {
    breadcrumbs: [{ title: 'Courses', href: index() }],
};
