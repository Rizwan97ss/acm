import { Link, router, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    CheckCircle2,
    Pencil,
    Plus,
    Send,
    Trash2,
    X,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import ConfirmDelete from '@/components/confirm-delete';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useFormat } from '@/hooks/use-format';
import { cn } from '@/lib/utils';
import {
    destroy as destroyQuestion,
    move as moveQuestion,
    update as updateQuestion,
} from '@/routes/learning/questions';
import { destroy, edit, index, update } from '@/routes/learning/quizzes';
import { show as showAttempt } from '@/routes/learning/quizzes/attempts';
import { store as storeQuestion } from '@/routes/learning/quizzes/questions';
import type { QuestionType, QuizOption } from '@/types/learning';

type Question = {
    id: number;
    type: QuestionType;
    type_label: string;
    prompt: string;
    marks: number;
    explanation: string | null;
    options: QuizOption[];
    answer_key: string[];
};

type Attempt = {
    id: number;
    student: string;
    attempt_no: number;
    status: 'in_progress' | 'submitted' | 'graded';
    status_label: string;
    score: number | null;
    max_score: number;
    submitted_at: string | null;
};

type Props = {
    quiz: Record<string, unknown> & {
        id: number;
        title: string;
        type_label: string;
        status: 'draft' | 'published';
        subject: string;
        audience: string;
        opens_at: string | null;
        closes_at: string | null;
        time_limit_minutes: number | null;
        max_attempts: number;
        max_score: number;
    };
    questions: Question[];
    attempts: Attempt[];
    questionTypes: { value: QuestionType; label: string }[];
};

type QuestionForm = {
    type: QuestionType;
    prompt: string;
    marks: number;
    explanation: string;
    options: { text: string; correct: boolean }[];
    correct: 'true' | 'false' | '';
    accepted_answers: string;
};

function blank(type: QuestionType = 'single_choice'): QuestionForm {
    return {
        type,
        prompt: '',
        marks: 1,
        explanation: '',
        options: [
            { text: '', correct: true },
            { text: '', correct: false },
        ],
        correct: '',
        accepted_answers: '',
    };
}

function fromQuestion(question: Question): QuestionForm {
    return {
        type: question.type,
        prompt: question.prompt,
        marks: question.marks,
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
    };
}

function QuestionEditor({
    quizId,
    question,
    types,
    onDone,
}: {
    quizId: number;
    question: Question | null;
    types: Props['questionTypes'];
    onDone: () => void;
}) {
    const form = useForm<QuestionForm>(
        question ? fromQuestion(question) : blank(),
    );
    const choice =
        form.data.type === 'single_choice' ||
        form.data.type === 'multiple_choice';
    const lockedError = (form.errors as Partial<Record<string, string>>)
        .question;

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
        form.transform((data) => ({
            ...data,
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
        const options = { preserveScroll: true, onSuccess: onDone };

        if (question) {
            form.put(updateQuestion.url(question.id), options);
        } else {
            form.post(storeQuestion.url(quizId), {
                ...options,
                onSuccess: () => {
                    form.setData(blank(form.data.type));
                    onDone();
                },
            });
        }
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-4 rounded-2xl border-2 border-dashed border-navy-200 bg-card p-5 dark:border-white/15"
        >
            <div className="grid gap-4 sm:grid-cols-[1fr_8rem]">
                <FormField
                    id="q-type"
                    label="Question type"
                    error={form.errors.type}
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
                <FormField id="q-marks" label="Marks" error={form.errors.marks}>
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
            </div>
            <FormField
                id="q-prompt"
                label="Question"
                required
                error={form.errors.prompt}
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
                    {form.errors.options && (
                        <p className="text-sm text-destructive">
                            {form.errors.options}
                        </p>
                    )}
                </fieldset>
            )}

            {form.data.type === 'true_false' && (
                <FormField
                    id="q-correct"
                    label="The statement is"
                    error={form.errors.correct}
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
                    error={form.errors.accepted_answers}
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
                error={form.errors.explanation}
            >
                <Input
                    id="q-explanation"
                    value={form.data.explanation}
                    onChange={(event) =>
                        form.setData('explanation', event.target.value)
                    }
                />
            </FormField>
            {lockedError && (
                <p className="text-sm text-destructive">{lockedError}</p>
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

export default function QuizShow({
    quiz,
    questions,
    attempts,
    questionTypes,
}: Props) {
    const { dateTime } = useFormat();
    const [editing, setEditing] = useState<number | 'new' | null>(
        questions.length === 0 ? 'new' : null,
    );
    const locked = attempts.length > 0;
    const toMark = attempts.filter(
        (attempt) => attempt.status === 'submitted',
    ).length;

    const publish = (): void => {
        const {
            id: _id,
            subject: _subject,
            audience: _audience,
            type_label: _label,
            max_score: _max,
            ...settings
        } = quiz;
        router.put(
            update.url(quiz.id),
            { ...settings, status: 'published' },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={quiz.title}
                description={`${quiz.type_label} · ${quiz.subject} · ${quiz.audience}`}
                actions={
                    <>
                        {quiz.status === 'draft' && (
                            <Button
                                onClick={publish}
                                disabled={questions.length === 0}
                            >
                                <Send aria-hidden /> Publish
                            </Button>
                        )}
                        <Button asChild variant="outline">
                            <Link href={edit(quiz.id)}>
                                <Pencil aria-hidden /> Settings
                            </Link>
                        </Button>
                        <ConfirmDelete
                            action={destroy(quiz.id)}
                            title="Delete this quiz?"
                            description="Questions and every student attempt will be removed."
                        />
                    </>
                }
            />

            <div className="flex flex-wrap gap-2 text-sm">
                <TonePill
                    tone={quiz.status === 'published' ? 'success' : 'warning'}
                >
                    {quiz.status === 'published' ? 'Published' : 'Draft'}
                </TonePill>
                <TonePill tone="info">
                    {questions.length} questions · {quiz.max_score} marks
                </TonePill>
                {quiz.time_limit_minutes && (
                    <TonePill tone="neutral">
                        {quiz.time_limit_minutes} min limit
                    </TonePill>
                )}
                <TonePill tone="neutral">
                    {quiz.max_attempts === 1
                        ? 'One attempt'
                        : `${quiz.max_attempts} attempts`}
                </TonePill>
                {quiz.closes_at && (
                    <TonePill tone="neutral">
                        Closes {dateTime(quiz.closes_at)}
                    </TonePill>
                )}
                {toMark > 0 && (
                    <TonePill tone="warning">{toMark} to mark</TonePill>
                )}
            </div>

            <section aria-labelledby="questions-title" className="space-y-3">
                <div className="flex items-center justify-between">
                    <h2 id="questions-title" className="text-lg font-semibold">
                        Questions
                    </h2>
                    {!locked && editing === null && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setEditing('new')}
                        >
                            <Plus aria-hidden /> Add question
                        </Button>
                    )}
                </div>
                {locked && (
                    <p className="rounded-xl bg-muted p-3 text-sm text-muted-foreground">
                        Students have started this quiz, so questions are locked
                        to keep marking fair.
                    </p>
                )}

                {questions.map((question, position) =>
                    editing === question.id ? (
                        <QuestionEditor
                            key={question.id}
                            quizId={quiz.id}
                            question={question}
                            types={questionTypes}
                            onDone={() => setEditing(null)}
                        />
                    ) : (
                        <article
                            key={question.id}
                            className="rounded-2xl border bg-card p-5 shadow-sm"
                        >
                            <div className="flex items-start gap-3">
                                <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-navy-700 text-xs font-bold text-white">
                                    {position + 1}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="whitespace-pre-line">
                                        {question.prompt}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {question.type_label} · {question.marks}{' '}
                                        {question.marks === 1
                                            ? 'mark'
                                            : 'marks'}
                                    </p>
                                    {question.options.length > 0 && (
                                        <ul className="mt-3 grid gap-1.5 sm:grid-cols-2">
                                            {question.options.map((option) => (
                                                <li
                                                    key={option.id}
                                                    className={cn(
                                                        'flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm',
                                                        question.answer_key.includes(
                                                            option.id,
                                                        ) &&
                                                            'border-emerald-300 bg-emerald-50 dark:border-emerald-500/40 dark:bg-emerald-500/10',
                                                    )}
                                                >
                                                    {question.answer_key.includes(
                                                        option.id,
                                                    ) && (
                                                        <CheckCircle2
                                                            className="size-4 text-emerald-600"
                                                            aria-label="Correct"
                                                        />
                                                    )}
                                                    {option.text}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                    {question.type === 'true_false' && (
                                        <p className="mt-2 text-sm">
                                            Answer:{' '}
                                            <strong>
                                                {question.answer_key[0] ===
                                                'true'
                                                    ? 'True'
                                                    : 'False'}
                                            </strong>
                                        </p>
                                    )}
                                    {question.type === 'short_answer' && (
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {question.answer_key.length > 0
                                                ? `Accepted: ${question.answer_key.join(', ')}`
                                                : 'Marked by hand'}
                                        </p>
                                    )}
                                </div>
                                {!locked && (
                                    <div className="flex shrink-0 gap-1">
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            className="size-8"
                                            aria-label="Move up"
                                            onClick={() =>
                                                router.post(
                                                    moveQuestion.url(
                                                        question.id,
                                                    ),
                                                    { direction: 'up' },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <ArrowUp aria-hidden />
                                        </Button>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            className="size-8"
                                            aria-label="Move down"
                                            onClick={() =>
                                                router.post(
                                                    moveQuestion.url(
                                                        question.id,
                                                    ),
                                                    { direction: 'down' },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <ArrowDown aria-hidden />
                                        </Button>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            className="size-8"
                                            aria-label="Edit question"
                                            onClick={() =>
                                                setEditing(question.id)
                                            }
                                        >
                                            <Pencil aria-hidden />
                                        </Button>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            className="size-8 text-destructive"
                                            aria-label="Delete question"
                                            onClick={() =>
                                                router.delete(
                                                    destroyQuestion.url(
                                                        question.id,
                                                    ),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Trash2 aria-hidden />
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </article>
                    ),
                )}

                {editing === 'new' && !locked && (
                    <QuestionEditor
                        quizId={quiz.id}
                        question={null}
                        types={questionTypes}
                        onDone={() => setEditing(null)}
                    />
                )}
            </section>

            <section aria-labelledby="results-title" className="space-y-3">
                <h2 id="results-title" className="text-lg font-semibold">
                    Results
                </h2>
                {attempts.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                        No attempts yet.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-2xl border bg-card shadow-sm">
                        <table className="w-full text-sm">
                            <caption className="sr-only">
                                Student attempts
                            </caption>
                            <thead className="bg-muted text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th scope="col" className="px-4 py-2">
                                        Student
                                    </th>
                                    <th scope="col" className="px-4 py-2">
                                        Attempt
                                    </th>
                                    <th scope="col" className="px-4 py-2">
                                        Handed in
                                    </th>
                                    <th scope="col" className="px-4 py-2">
                                        Score
                                    </th>
                                    <th scope="col" className="px-4 py-2">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {attempts.map((attempt) => (
                                    <tr key={attempt.id}>
                                        <td className="px-4 py-2 font-medium">
                                            {attempt.status ===
                                            'in_progress' ? (
                                                attempt.student
                                            ) : (
                                                <Link
                                                    href={showAttempt({
                                                        quiz: quiz.id,
                                                        attempt: attempt.id,
                                                    })}
                                                    className="hover:text-primary hover:underline"
                                                >
                                                    {attempt.student}
                                                </Link>
                                            )}
                                        </td>
                                        <td className="px-4 py-2 tabular-nums">
                                            {attempt.attempt_no}
                                        </td>
                                        <td className="px-4 py-2 text-muted-foreground">
                                            {dateTime(
                                                attempt.submitted_at,
                                                'Not yet',
                                            )}
                                        </td>
                                        <td className="px-4 py-2 tabular-nums">
                                            {attempt.score !== null
                                                ? `${attempt.score} / ${attempt.max_score}`
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-2">
                                            <TonePill
                                                tone={
                                                    attempt.status === 'graded'
                                                        ? 'success'
                                                        : attempt.status ===
                                                            'submitted'
                                                          ? 'warning'
                                                          : 'neutral'
                                                }
                                            >
                                                {attempt.status_label}
                                            </TonePill>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </div>
    );
}

QuizShow.layout = {
    breadcrumbs: [{ title: 'Quizzes & tests', href: index() }],
};
