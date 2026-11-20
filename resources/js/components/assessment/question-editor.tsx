import { useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { QuestionType, QuizOption } from '@/types/learning';

export type EditableQuestion = {
    type: QuestionType;
    prompt: string;
    marks: number;
    negative_marks?: number;
    explanation: string | null;
    options: QuizOption[];
    answer_key: string[];
};

type QuestionForm = {
    type: QuestionType;
    prompt: string;
    marks: number;
    negative_marks: number;
    explanation: string;
    options: { text: string; correct: boolean }[];
    correct: 'true' | 'false' | '';
    accepted_answers: string;
    meta: Record<string, string>;
};

function blank(
    type: QuestionType = 'single_choice',
    meta: Record<string, string> = {},
): QuestionForm {
    return {
        type,
        prompt: '',
        marks: 1,
        negative_marks: 0,
        explanation: '',
        options: [
            { text: '', correct: true },
            { text: '', correct: false },
        ],
        correct: '',
        accepted_answers: '',
        meta,
    };
}

function fromQuestion(
    question: EditableQuestion,
    meta: Record<string, string>,
): QuestionForm {
    return {
        type: question.type,
        prompt: question.prompt,
        marks: question.marks,
        negative_marks: question.negative_marks ?? 0,
        explanation: question.explanation ?? '',
        options:
            question.options.length > 0
                ? question.options.map((option) => ({
                      text: option.text,
                      correct: question.answer_key.includes(option.id),
                  }))
                : blank().options,
        correct:
            question.type === 'true_false'
                ? ((question.answer_key[0] as 'true' | 'false') ?? '')
                : '',
        accepted_answers:
            question.type === 'short_answer'
                ? question.answer_key.join(', ')
                : '',
        meta,
    };
}

/**
 * Add or edit a question: single/multiple choice with the correct options ticked,
 * true/false, short answer with accepted answers, or essay. Used by quizzes, online
 * exams and the question bank. `negative` shows the "marks lost if wrong" field;
 * `meta` + `renderMeta` add extra fields (e.g. subject and difficulty for the bank),
 * sent alongside the question.
 */
export default function QuestionEditor({
    question,
    types,
    action,
    negative = false,
    meta = {},
    renderMeta,
    onDone,
}: {
    question: EditableQuestion | null;
    types: { value: QuestionType; label: string }[];
    action: { method: 'post' | 'put'; url: string };
    negative?: boolean;
    meta?: Record<string, string>;
    renderMeta?: (
        values: Record<string, string>,
        set: (key: string, value: string) => void,
        errors: Partial<Record<string, string>>,
    ) => ReactNode;
    onDone: () => void;
}) {
    const form = useForm<QuestionForm>(
        question ? fromQuestion(question, meta) : blank('single_choice', meta),
    );
    const errors = form.errors as Partial<Record<string, string>>;
    const choice =
        form.data.type === 'single_choice' ||
        form.data.type === 'multiple_choice';
    const objective = choice || form.data.type === 'true_false';

    const setOption = (
        index: number,
        changes: Partial<{ text: string; correct: boolean }>,
    ): void =>
        form.setData(
            'options',
            form.data.options.map((option, position) => {
                if (form.data.type === 'single_choice' && changes.correct) {
                    return {
                        ...option,
                        ...(position === index ? changes : {}),
                        correct: position === index,
                    };
                }

                return position === index ? { ...option, ...changes } : option;
            }),
        );

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.transform(({ meta: extra, ...data }) => ({
            ...extra,
            ...data,
            negative_marks: negative && objective ? data.negative_marks : 0,
            options: choice ? data.options : null,
            correct: data.type === 'true_false' ? data.correct : null,
            accepted_answers:
                data.type === 'short_answer'
                    ? data.accepted_answers
                          .split(',')
                          .map((answer) => answer.trim())
                          .filter(Boolean)
                    : null,
        }));

        form.submit(action.method, action.url, {
            preserveScroll: true,
            onSuccess: () => {
                if (!question) {
                    form.setData(blank(form.data.type, form.data.meta));
                }

                onDone();
            },
        });
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-4 rounded-2xl border-2 border-dashed border-navy-200 bg-card p-5 dark:border-white/15"
        >
            {renderMeta && (
                <div className="grid gap-4 sm:grid-cols-2">
                    {renderMeta(
                        form.data.meta,
                        (key, value) =>
                            form.setData('meta', {
                                ...form.data.meta,
                                [key]: value,
                            }),
                        errors,
                    )}
                </div>
            )}
            <div
                className={
                    negative && objective
                        ? 'grid gap-4 sm:grid-cols-[1fr_8rem_8rem]'
                        : 'grid gap-4 sm:grid-cols-[1fr_8rem]'
                }
            >
                <FormField
                    id="q-type"
                    label="Question type"
                    error={errors.type}
                >
                    <NativeSelect
                        id="q-type"
                        value={form.data.type}
                        onChange={(event) =>
                            form.setData(
                                'type',
                                event.target.value as QuestionType,
                            )
                        }
                    >
                        {types.map((type) => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </NativeSelect>
                </FormField>
                <FormField id="q-marks" label="Marks" error={errors.marks}>
                    <Input
                        id="q-marks"
                        type="number"
                        min={0.5}
                        max={100}
                        step={0.5}
                        value={form.data.marks}
                        onChange={(event) =>
                            form.setData('marks', Number(event.target.value))
                        }
                    />
                </FormField>
                {negative && objective && (
                    <FormField
                        id="q-negative"
                        label="If wrong, lose"
                        error={errors.negative_marks}
                    >
                        <Input
                            id="q-negative"
                            type="number"
                            min={0}
                            max={form.data.marks}
                            step={0.25}
                            value={form.data.negative_marks}
                            onChange={(event) =>
                                form.setData(
                                    'negative_marks',
                                    Number(event.target.value),
                                )
                            }
                        />
                    </FormField>
                )}
            </div>
            <FormField
                id="q-prompt"
                label="Question"
                required
                error={errors.prompt}
            >
                <Textarea
                    id="q-prompt"
                    rows={3}
                    value={form.data.prompt}
                    onChange={(event) =>
                        form.setData('prompt', event.target.value)
                    }
                />
            </FormField>

            {choice && (
                <fieldset className="space-y-2">
                    <legend className="text-sm font-medium">
                        Options: tick the correct{' '}
                        {form.data.type === 'single_choice' ? 'one' : 'ones'}
                    </legend>
                    {form.data.options.map((option, position) => (
                        <div key={position} className="flex items-center gap-2">
                            <Checkbox
                                aria-label={`Option ${position + 1} is correct`}
                                checked={option.correct}
                                onCheckedChange={(checked) =>
                                    setOption(position, {
                                        correct: checked === true,
                                    })
                                }
                            />
                            <Input
                                aria-label={`Option ${position + 1}`}
                                value={option.text}
                                onChange={(event) =>
                                    setOption(position, {
                                        text: event.target.value,
                                    })
                                }
                                placeholder={`Option ${String.fromCharCode(65 + position)}`}
                            />
                            {form.data.options.length > 2 && (
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    aria-label="Remove option"
                                    onClick={() =>
                                        form.setData(
                                            'options',
                                            form.data.options.filter(
                                                (_, index) =>
                                                    index !== position,
                                            ),
                                        )
                                    }
                                >
                                    <X aria-hidden />
                                </Button>
                            )}
                        </div>
                    ))}
                    {form.data.options.length < 8 && (
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() =>
                                form.setData('options', [
                                    ...form.data.options,
                                    { text: '', correct: false },
                                ])
                            }
                        >
                            <Plus aria-hidden /> Add option
                        </Button>
                    )}
                    {errors.options && (
                        <p className="text-sm text-destructive">
                            {errors.options}
                        </p>
                    )}
                </fieldset>
            )}

            {form.data.type === 'true_false' && (
                <FormField
                    id="q-correct"
                    label="The statement is"
                    error={errors.correct}
                >
                    <NativeSelect
                        id="q-correct"
                        value={form.data.correct}
                        onChange={(event) =>
                            form.setData(
                                'correct',
                                event.target.value as 'true' | 'false',
                            )
                        }
                    >
                        <option value="">Choose…</option>
                        <option value="true">True</option>
                        <option value="false">False</option>
                    </NativeSelect>
                </FormField>
            )}

            {form.data.type === 'short_answer' && (
                <FormField
                    id="q-accepted"
                    label="Accepted answers"
                    error={errors.accepted_answers}
                    description="Separate with commas; matching ignores capitals. Leave empty to mark by hand."
                >
                    <Input
                        id="q-accepted"
                        value={form.data.accepted_answers}
                        onChange={(event) =>
                            form.setData('accepted_answers', event.target.value)
                        }
                        placeholder="e.g. photosynthesis, photo-synthesis"
                    />
                </FormField>
            )}

            {form.data.type === 'essay' && (
                <p className="text-sm text-muted-foreground">
                    Essay answers are marked by you after students hand in.
                </p>
            )}

            <FormField
                id="q-explanation"
                label="Explanation shown after marking"
                error={errors.explanation}
            >
                <Input
                    id="q-explanation"
                    value={form.data.explanation}
                    onChange={(event) =>
                        form.setData('explanation', event.target.value)
                    }
                />
            </FormField>
            {errors.question && (
                <p className="text-sm text-destructive">{errors.question}</p>
            )}
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    {question ? 'Save question' : 'Add question'}
                </Button>
                <Button type="button" variant="ghost" onClick={onDone}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}
