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
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { index, store, update } from '@/routes/learning/quizzes';
import type { PublishStatus, TeachingOption } from '@/types/learning';

type QuizValues = {
    id: number;
    title: string;
    instructions: string | null;
    type: string;
    status: PublishStatus;
    school_class_id: number;
    section_id: number | null;
    subject_id: number;
    course_id: number | null;
    opens_at: string | null;
    closes_at: string | null;
    time_limit_minutes: number | null;
    max_attempts: number;
    shuffle_questions: boolean;
    show_results: boolean;
};

type Props = {
    quiz: QuizValues | null;
    options: TeachingOption[];
    types: { value: string; label: string }[];
    courses: {
        id: number;
        title: string;
        school_class_id: number;
        subject_id: number;
    }[];
};

export default function QuizForm({ quiz, options, types, courses }: Props) {
    const form = useForm<
        AudienceValue & {
            title: string;
            instructions: string;
            type: string;
            status: PublishStatus;
            course_id: number | '';
            opens_at: string;
            closes_at: string;
            time_limit_minutes: number | '';
            max_attempts: number;
            shuffle_questions: boolean;
            show_results: boolean;
        }
    >({
        school_class_id: quiz?.school_class_id ?? '',
        section_id: quiz?.section_id ?? '',
        subject_id: quiz?.subject_id ?? '',
        course_id: quiz?.course_id ?? '',
        title: quiz?.title ?? '',
        instructions: quiz?.instructions ?? '',
        type: quiz?.type ?? 'quiz',
        status: quiz?.status ?? 'draft',
        opens_at: isoToLocalInput(quiz?.opens_at),
        closes_at: isoToLocalInput(quiz?.closes_at),
        time_limit_minutes: quiz?.time_limit_minutes ?? '',
        max_attempts: quiz?.max_attempts ?? 1,
        shuffle_questions: quiz?.shuffle_questions ?? false,
        show_results: quiz?.show_results ?? true,
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
            section_id: data.section_id === '' ? null : data.section_id,
            course_id: data.course_id === '' ? null : data.course_id,
            opens_at: localInputToIso(data.opens_at),
            closes_at: localInputToIso(data.closes_at),
            time_limit_minutes:
                data.time_limit_minutes === '' ? null : data.time_limit_minutes,
        }));

        if (quiz) {
            form.put(update.url(quiz.id));
        } else {
            form.post(store.url());
        }
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={quiz ? `Settings: ${quiz.title}` : 'New quiz or test'}
                description="Create the quiz, add questions, then publish it to students."
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
                >
                    <Input
                        id="title"
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                    />
                </FormField>
                <FormField
                    id="type"
                    label="Type"
                    required
                    error={form.errors.type}
                >
                    <NativeSelect
                        id="type"
                        value={form.data.type}
                        onChange={(event) =>
                            form.setData('type', event.target.value)
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
                    id="instructions"
                    label="Instructions"
                    error={form.errors.instructions}
                    className="sm:col-span-2"
                >
                    <Textarea
                        id="instructions"
                        rows={3}
                        value={form.data.instructions}
                        onChange={(event) =>
                            form.setData('instructions', event.target.value)
                        }
                    />
                </FormField>
                <FormField
                    id="opens_at"
                    label="Opens"
                    error={form.errors.opens_at}
                    description="Leave empty to open as soon as it is published."
                >
                    <Input
                        id="opens_at"
                        type="datetime-local"
                        value={form.data.opens_at}
                        onChange={(event) =>
                            form.setData('opens_at', event.target.value)
                        }
                    />
                </FormField>
                <FormField
                    id="closes_at"
                    label="Closes"
                    error={form.errors.closes_at}
                >
                    <Input
                        id="closes_at"
                        type="datetime-local"
                        value={form.data.closes_at}
                        onChange={(event) =>
                            form.setData('closes_at', event.target.value)
                        }
                    />
                </FormField>
                <FormField
                    id="time_limit"
                    label="Time limit (minutes)"
                    error={form.errors.time_limit_minutes}
                    description="Optional. The quiz is handed in automatically when time runs out."
                >
                    <Input
                        id="time_limit"
                        type="number"
                        min={1}
                        max={600}
                        value={form.data.time_limit_minutes}
                        onChange={(event) =>
                            form.setData(
                                'time_limit_minutes',
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
                    error={form.errors.max_attempts}
                >
                    <Input
                        id="attempts"
                        type="number"
                        min={1}
                        max={10}
                        value={form.data.max_attempts}
                        onChange={(event) =>
                            form.setData(
                                'max_attempts',
                                Number(event.target.value),
                            )
                        }
                    />
                </FormField>
                <div className="flex items-center gap-2">
                    <Checkbox
                        id="shuffle"
                        checked={form.data.shuffle_questions}
                        onCheckedChange={(checked) =>
                            form.setData('shuffle_questions', checked === true)
                        }
                    />
                    <Label htmlFor="shuffle" className="font-normal">
                        Shuffle question order for each student
                    </Label>
                </div>
                <div className="flex items-center gap-2">
                    <Checkbox
                        id="results"
                        checked={form.data.show_results}
                        onCheckedChange={(checked) =>
                            form.setData('show_results', checked === true)
                        }
                    />
                    <Label htmlFor="results" className="font-normal">
                        Show scores and correct answers after marking
                    </Label>
                </div>
                {quiz && (
                    <FormField
                        id="status"
                        label="Status"
                        error={form.errors.status}
                        description="Publishing notifies the students."
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
                )}
                <div className="flex gap-2 sm:col-span-2">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        {quiz ? 'Save settings' : 'Create and add questions'}
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

QuizForm.layout = {
    breadcrumbs: [{ title: 'Quizzes & tests', href: index() }],
};
