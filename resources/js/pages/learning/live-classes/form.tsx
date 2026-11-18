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
import { index, store, update } from '@/routes/learning/live-classes';
import type { TeachingOption } from '@/types/learning';

type Props = {
    liveClass: {
        id: number;
        title: string;
        description: string | null;
        provider: string;
        meeting_url: string;
        starts_at: string;
        duration_minutes: number;
        school_class_id: number;
        section_id: number | null;
        subject_id: number;
        course_id: number | null;
    } | null;
    options: TeachingOption[];
    providers: { value: string; label: string }[];
    courses: {
        id: number;
        title: string;
        school_class_id: number;
        subject_id: number;
    }[];
};

export default function LiveClassForm({
    liveClass,
    options,
    providers,
    courses,
}: Props) {
    const form = useForm<
        AudienceValue & {
            title: string;
            description: string;
            provider: string;
            meeting_url: string;
            starts_at: string;
            duration_minutes: number;
            course_id: number | '';
        }
    >({
        school_class_id: liveClass?.school_class_id ?? '',
        section_id: liveClass?.section_id ?? '',
        subject_id: liveClass?.subject_id ?? '',
        course_id: liveClass?.course_id ?? '',
        title: liveClass?.title ?? '',
        description: liveClass?.description ?? '',
        provider: liveClass?.provider ?? 'google_meet',
        meeting_url: liveClass?.meeting_url ?? '',
        starts_at: isoToLocalInput(liveClass?.starts_at),
        duration_minutes: liveClass?.duration_minutes ?? 45,
    });

    const matchingCourses = courses.filter(
        (course) =>
            course.school_class_id === form.data.school_class_id &&
            course.subject_id === form.data.subject_id,
    );

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            starts_at: localInputToIso(data.starts_at),
            section_id: data.section_id === '' ? null : data.section_id,
            course_id: data.course_id === '' ? null : data.course_id,
        }));

        if (liveClass) {
            form.put(update.url(liveClass.id));
        } else {
            form.post(store.url());
        }
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={liveClass ? 'Edit live class' : 'Schedule a live class'}
                description="Students in the class are notified now and reminded shortly before it starts. The meeting link only appears to them when it is time to join."
            />
            <form
                onSubmit={submit}
                className="grid max-w-3xl gap-5 rounded-2xl border bg-card p-6 shadow-sm sm:grid-cols-2"
            >
                <AudiencePicker
                    options={options}
                    value={form.data}
                    onChange={(audience) =>
                        form.setData((data) => ({ ...data, ...audience }))
                    }
                    errors={form.errors}
                />
                <FormField
                    id="course"
                    label="Course (optional)"
                    error={form.errors.course_id}
                >
                    <NativeSelect
                        id="course"
                        value={form.data.course_id}
                        onChange={(event) =>
                            form.setData(
                                'course_id',
                                event.target.value === ''
                                    ? ''
                                    : Number(event.target.value),
                            )
                        }
                    >
                        <option value="">None</option>
                        {matchingCourses.map((course) => (
                            <option key={course.id} value={course.id}>
                                {course.title}
                            </option>
                        ))}
                    </NativeSelect>
                </FormField>
                <FormField
                    id="title"
                    label="Title"
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
                        placeholder="e.g. Revision: photosynthesis"
                    />
                </FormField>
                <FormField
                    id="provider"
                    label="Meeting tool"
                    required
                    error={form.errors.provider}
                >
                    <NativeSelect
                        id="provider"
                        value={form.data.provider}
                        onChange={(event) =>
                            form.setData('provider', event.target.value)
                        }
                    >
                        {providers.map((provider) => (
                            <option key={provider.value} value={provider.value}>
                                {provider.label}
                            </option>
                        ))}
                    </NativeSelect>
                </FormField>
                <FormField
                    id="meeting_url"
                    label="Meeting link"
                    required
                    error={form.errors.meeting_url}
                    description="Stored encrypted."
                >
                    <Input
                        id="meeting_url"
                        type="url"
                        placeholder="https://meet.google.com/…"
                        value={form.data.meeting_url}
                        onChange={(event) =>
                            form.setData('meeting_url', event.target.value)
                        }
                    />
                </FormField>
                <FormField
                    id="starts_at"
                    label="Starts"
                    required
                    error={form.errors.starts_at}
                >
                    <Input
                        id="starts_at"
                        type="datetime-local"
                        value={form.data.starts_at}
                        onChange={(event) =>
                            form.setData('starts_at', event.target.value)
                        }
                    />
                </FormField>
                <FormField
                    id="duration"
                    label="Duration (minutes)"
                    required
                    error={form.errors.duration_minutes}
                >
                    <Input
                        id="duration"
                        type="number"
                        min={5}
                        max={480}
                        value={form.data.duration_minutes}
                        onChange={(event) =>
                            form.setData(
                                'duration_minutes',
                                Number(event.target.value),
                            )
                        }
                    />
                </FormField>
                <FormField
                    id="description"
                    label="What to prepare"
                    error={form.errors.description}
                    className="sm:col-span-2"
                >
                    <Textarea
                        id="description"
                        rows={3}
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData('description', event.target.value)
                        }
                    />
                </FormField>
                <div className="flex gap-2 sm:col-span-2">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        {liveClass ? 'Save changes' : 'Schedule and notify'}
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

LiveClassForm.layout = {
    breadcrumbs: [{ title: 'Live classes', href: index() }],
};
