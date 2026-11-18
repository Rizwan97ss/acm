import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, XCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/learning/quizzes';
import { grade } from '@/routes/learning/quizzes/attempts';
import type { QuestionType, QuizOption } from '@/types/learning';

type Item = {
    question: {
        id: number;
        type: QuestionType;
        prompt: string;
        marks: number;
        options: QuizOption[];
        answer_key: string[];
    };
    answer: {
        id: number;
        value: string | string[] | null;
        is_correct: boolean | null;
        marks_awarded: number | null;
        feedback: string | null;
    } | null;
};

type Props = {
    quiz: { id: number; title: string };
    attempt: {
        id: number;
        student: string;
        status: string;
        score: number | null;
        max_score: number;
        feedback: string | null;
        submitted_at: string | null;
    };
    items: Item[];
};

function describe(item: Item): string {
    const value = item.answer?.value;

    if (value === null || value === undefined || value === '') {
        return 'No answer';
    }

    if (item.question.type === 'true_false') {
        return value === 'true' ? 'True' : 'False';
    }

    if (item.question.options.length > 0) {
        const ids = Array.isArray(value) ? value : [value];

        return (
            item.question.options
                .filter((option) => ids.includes(option.id))
                .map((option) => option.text)
                .join(', ') || 'No answer'
        );
    }

    return String(value);
}

export default function AttemptGrade({ quiz, attempt, items }: Props) {
    const form = useForm<{
        marks: Record<number, { marks: number | ''; feedback: string }>;
        feedback: string;
    }>({
        marks: Object.fromEntries(
            items
                .filter((item) => item.answer)
                .map((item) => [
                    item.answer!.id,
                    {
                        marks: item.answer!.marks_awarded ?? '',
                        feedback: item.answer!.feedback ?? '',
                    },
                ]),
        ),
        feedback: attempt.feedback ?? '',
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.put(grade.url({ quiz: quiz.id, attempt: attempt.id }));
    };

    const total = Object.values(form.data.marks).reduce(
        (sum, entry) => sum + (entry.marks === '' ? 0 : Number(entry.marks)),
        0,
    );

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={`Marking: ${attempt.student}`}
                description={quiz.title}
                actions={
                    <Button asChild variant="outline">
                        <Link href={show(quiz.id)}>
                            <ArrowLeft aria-hidden /> Back to quiz
                        </Link>
                    </Button>
                }
            />
            <form onSubmit={submit} className="grid max-w-4xl gap-4">
                {items.map((item, position) => {
                    const needsMarking =
                        item.answer !== null && item.answer.is_correct === null;

                    return (
                        <article
                            key={item.question.id}
                            className={cn(
                                'rounded-2xl border bg-card p-5 shadow-sm',
                                needsMarking &&
                                    'border-gold-400 ring-1 ring-gold-300',
                            )}
                        >
                            <div className="flex items-start gap-3">
                                <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-navy-700 text-xs font-bold text-white">
                                    {position + 1}
                                </span>
                                <div className="min-w-0 flex-1 space-y-2">
                                    <p className="font-medium whitespace-pre-line">
                                        {item.question.prompt}
                                    </p>
                                    <div className="rounded-xl bg-muted/60 p-3 text-sm whitespace-pre-line">
                                        {describe(item)}
                                    </div>
                                    {item.answer?.is_correct === true && (
                                        <p className="flex items-center gap-1 text-sm text-emerald-700 dark:text-emerald-300">
                                            <CheckCircle2
                                                className="size-4"
                                                aria-hidden
                                            />{' '}
                                            Correct (marked automatically)
                                        </p>
                                    )}
                                    {item.answer?.is_correct === false && (
                                        <p className="flex items-center gap-1 text-sm text-rose-700 dark:text-rose-300">
                                            <XCircle
                                                className="size-4"
                                                aria-hidden
                                            />{' '}
                                            Incorrect (marked automatically)
                                        </p>
                                    )}
                                    {needsMarking && (
                                        <TonePill tone="warning">
                                            Needs your mark
                                        </TonePill>
                                    )}
                                    {item.answer && (
                                        <div className="grid gap-3 sm:grid-cols-[8rem_1fr]">
                                            <FormField
                                                id={`marks-${item.answer.id}`}
                                                label={`Marks (of ${item.question.marks})`}
                                            >
                                                <Input
                                                    id={`marks-${item.answer.id}`}
                                                    type="number"
                                                    min={0}
                                                    max={item.question.marks}
                                                    step={0.5}
                                                    value={
                                                        form.data.marks[
                                                            item.answer.id
                                                        ]?.marks ?? ''
                                                    }
                                                    onChange={(event) =>
                                                        form.setData('marks', {
                                                            ...form.data.marks,
                                                            [item.answer!.id]: {
                                                                ...form.data
                                                                    .marks[
                                                                    item.answer!
                                                                        .id
                                                                ],
                                                                marks:
                                                                    event.target
                                                                        .value ===
                                                                    ''
                                                                        ? ''
                                                                        : Number(
                                                                              event
                                                                                  .target
                                                                                  .value,
                                                                          ),
                                                            },
                                                        })
                                                    }
                                                />
                                            </FormField>
                                            <FormField
                                                id={`feedback-${item.answer.id}`}
                                                label="Comment"
                                            >
                                                <Input
                                                    id={`feedback-${item.answer.id}`}
                                                    value={
                                                        form.data.marks[
                                                            item.answer.id
                                                        ]?.feedback ?? ''
                                                    }
                                                    onChange={(event) =>
                                                        form.setData('marks', {
                                                            ...form.data.marks,
                                                            [item.answer!.id]: {
                                                                ...form.data
                                                                    .marks[
                                                                    item.answer!
                                                                        .id
                                                                ],
                                                                feedback:
                                                                    event.target
                                                                        .value,
                                                            },
                                                        })
                                                    }
                                                />
                                            </FormField>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </article>
                    );
                })}
                <div className="rounded-2xl border bg-card p-5 shadow-sm">
                    <FormField
                        id="overall"
                        label="Overall feedback for the student"
                        error={form.errors.feedback}
                    >
                        <Textarea
                            id="overall"
                            rows={3}
                            value={form.data.feedback}
                            onChange={(event) =>
                                form.setData('feedback', event.target.value)
                            }
                        />
                    </FormField>
                    <div className="mt-4 flex flex-wrap items-center gap-3">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Save marks
                        </Button>
                        <span className="text-sm text-muted-foreground tabular-nums">
                            Total: {total} / {attempt.max_score}
                        </span>
                    </div>
                </div>
            </form>
        </div>
    );
}

AttemptGrade.layout = {
    breadcrumbs: [{ title: 'Quizzes & tests', href: index() }],
};
