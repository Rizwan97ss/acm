import { router } from '@inertiajs/react';
import { CheckCircle2, Clock, Send, XCircle } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/hooks/use-format';
import { cn } from '@/lib/utils';
import { index } from '@/routes/learn';
import { start, submit } from '@/routes/learn/quizzes';
import type { QuestionType, QuizOption, SubjectBadge } from '@/types/learning';

type Answer = string | string[];

type Question = {
    id: number;
    type: QuestionType;
    prompt: string;
    marks: number;
    options: QuizOption[];
    answer_key: string[] | null;
    explanation: string | null;
    review: {
        value: Answer | null;
        is_correct: boolean | null;
        marks_awarded: number | null;
        feedback: string | null;
    } | null;
};

type Props = {
    quiz: {
        id: number;
        title: string;
        type: string;
        instructions: string | null;
        subject: SubjectBadge;
        opens_at: string | null;
        closes_at: string | null;
        time_limit_minutes: number | null;
        max_attempts: number;
        is_open: boolean;
        questions_count: number;
        max_score: number;
        show_results: boolean;
    };
    attemptsUsed: number;
    current: { id: number; deadline: string | null } | null;
    questions: Question[];
    result: {
        status: string;
        status_label: string;
        score: number | null;
        max_score: number;
        feedback: string | null;
        submitted_at: string | null;
    } | null;
};

function Countdown({
    deadline,
    onExpire,
}: {
    deadline: string;
    onExpire: () => void;
}) {
    const [left, setLeft] = useState(
        () => new Date(deadline).getTime() - Date.now(),
    );
    const fired = useRef(false);

    useEffect(() => {
        const timer = window.setInterval(() => {
            const remaining = new Date(deadline).getTime() - Date.now();
            setLeft(remaining);

            if (remaining <= 0 && !fired.current) {
                fired.current = true;
                onExpire();
            }
        }, 1000);

        return () => window.clearInterval(timer);
    }, [deadline, onExpire]);

    const seconds = Math.max(0, Math.floor(left / 1000));
    const label = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold tabular-nums',
                seconds < 60
                    ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-200'
                    : 'bg-muted',
            )}
            role="timer"
            aria-live={seconds < 60 ? 'assertive' : 'off'}
        >
            <Clock className="size-4" aria-hidden /> {label}
        </span>
    );
}

function AnswerInput({
    question,
    value,
    onChange,
}: {
    question: Question;
    value: Answer | undefined;
    onChange: (value: Answer) => void;
}) {
    if (question.type === 'single_choice' || question.type === 'true_false') {
        const options =
            question.type === 'true_false'
                ? [
                      { id: 'true', text: 'True' },
                      { id: 'false', text: 'False' },
                  ]
                : question.options;

        return (
            <div
                role="radiogroup"
                aria-label="Your answer"
                className="grid gap-2 sm:grid-cols-2"
            >
                {options.map((option) => (
                    <label
                        key={option.id}
                        className={cn(
                            'flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 transition hover:bg-muted/50',
                            value === option.id &&
                                'border-navy-500 bg-navy-50 ring-1 ring-navy-400 dark:bg-navy-500/15',
                        )}
                    >
                        <input
                            type="radio"
                            name={`q-${question.id}`}
                            value={option.id}
                            checked={value === option.id}
                            onChange={() => onChange(option.id)}
                            className="size-4 accent-navy-700"
                        />
                        {option.text}
                    </label>
                ))}
            </div>
        );
    }

    if (question.type === 'multiple_choice') {
        const chosen = Array.isArray(value) ? value : [];

        return (
            <div className="grid gap-2 sm:grid-cols-2">
                {question.options.map((option) => (
                    <div
                        key={option.id}
                        className={cn(
                            'flex items-center gap-3 rounded-xl border px-4 py-3',
                            chosen.includes(option.id) &&
                                'border-navy-500 bg-navy-50 dark:bg-navy-500/15',
                        )}
                    >
                        <Checkbox
                            id={`q-${question.id}-${option.id}`}
                            checked={chosen.includes(option.id)}
                            onCheckedChange={(checked) =>
                                onChange(
                                    checked === true
                                        ? [...chosen, option.id]
                                        : chosen.filter(
                                              (id) => id !== option.id,
                                          ),
                                )
                            }
                        />
                        <Label
                            htmlFor={`q-${question.id}-${option.id}`}
                            className="font-normal"
                        >
                            {option.text}
                        </Label>
                    </div>
                ))}
            </div>
        );
    }

    if (question.type === 'short_answer') {
        return (
            <Input
                aria-label="Your answer"
                value={typeof value === 'string' ? value : ''}
                onChange={(event) => onChange(event.target.value)}
            />
        );
    }

    return (
        <Textarea
            aria-label="Your answer"
            rows={6}
            value={typeof value === 'string' ? value : ''}
            onChange={(event) => onChange(event.target.value)}
        />
    );
}

function reviewText(question: Question, value: Answer | null): string {
    if (value === null || value === '') {
        return 'No answer';
    }

    if (question.type === 'true_false') {
        return value === 'true' ? 'True' : 'False';
    }

    if (question.options.length > 0) {
        const ids = Array.isArray(value) ? value : [value];

        return question.options
            .filter((option) => ids.includes(option.id))
            .map((option) => option.text)
            .join(', ');
    }

    return String(value);
}

export default function LearnQuiz({
    quiz,
    attemptsUsed,
    current,
    questions,
    result,
}: Props) {
    const { dateTime } = useFormat();
    const storageKey = current ? `quiz-answers:${current.id}` : null;
    const [answers, setAnswers] = useState<Record<number, Answer>>({});
    const [restored, setRestored] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (storageKey) {
            try {
                setAnswers(
                    JSON.parse(window.localStorage.getItem(storageKey) ?? '{}'),
                );
            } catch {
                // Nothing saved, or storage blocked.
            }
        }

        setRestored(true);
    }, [storageKey]);

    useEffect(() => {
        if (storageKey && restored) {
            try {
                window.localStorage.setItem(
                    storageKey,
                    JSON.stringify(answers),
                );
            } catch {
                // Storage unavailable: answers still live in memory.
            }
        }
    }, [answers, storageKey, restored]);

    const handIn = useCallback(() => {
        if (!current) {
            return;
        }

        setSubmitting(true);
        router.post(
            submit.url({ quiz: quiz.id, attempt: current.id }),
            { answers },
            {
                onSuccess: () => {
                    try {
                        window.localStorage.removeItem(
                            `quiz-answers:${current.id}`,
                        );
                    } catch {
                        // ignore
                    }
                },
                onFinish: () => setSubmitting(false),
            },
        );
    }, [answers, current, quiz.id]);

    const answered = questions.filter((question) => {
        const value = answers[question.id];

        return (
            value !== undefined &&
            value !== '' &&
            !(Array.isArray(value) && value.length === 0)
        );
    }).length;

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={quiz.title}
                description={`${quiz.type} · ${quiz.subject.name}`}
                actions={
                    current?.deadline ? (
                        <Countdown
                            deadline={current.deadline}
                            onExpire={handIn}
                        />
                    ) : undefined
                }
            />

            {!current && (
                <section className="grid gap-4 rounded-2xl border bg-card p-6 shadow-sm md:grid-cols-[1fr_auto] md:items-center">
                    <div className="space-y-2">
                        {quiz.instructions && (
                            <p className="whitespace-pre-line">
                                {quiz.instructions}
                            </p>
                        )}
                        <div className="flex flex-wrap gap-2 text-sm">
                            <TonePill tone="info">
                                {quiz.questions_count} questions ·{' '}
                                {quiz.max_score} marks
                            </TonePill>
                            {quiz.time_limit_minutes && (
                                <TonePill tone="neutral">
                                    {quiz.time_limit_minutes} minutes
                                </TonePill>
                            )}
                            <TonePill tone="neutral">
                                Attempts: {attemptsUsed} of {quiz.max_attempts}
                            </TonePill>
                            {quiz.closes_at && (
                                <TonePill tone="neutral">
                                    Closes {dateTime(quiz.closes_at)}
                                </TonePill>
                            )}
                        </div>
                        {!quiz.is_open && (
                            <p className="text-sm text-muted-foreground">
                                {quiz.opens_at &&
                                new Date(quiz.opens_at) > new Date()
                                    ? `Opens ${dateTime(quiz.opens_at)}.`
                                    : 'This quiz is closed.'}
                            </p>
                        )}
                    </div>
                    {quiz.is_open && attemptsUsed < quiz.max_attempts && (
                        <Button
                            size="lg"
                            onClick={() => router.post(start.url(quiz.id))}
                        >
                            {attemptsUsed === 0 ? 'Start' : 'Try again'}
                        </Button>
                    )}
                </section>
            )}

            {result && !current && (
                <section
                    className="rounded-2xl border bg-card p-6 shadow-sm"
                    aria-labelledby="result-title"
                >
                    <h2 id="result-title" className="font-semibold">
                        Your latest attempt
                    </h2>
                    <div className="mt-2 flex flex-wrap items-center gap-3">
                        {result.score !== null && (
                            <p className="text-3xl font-bold tabular-nums">
                                {result.score}{' '}
                                <span className="text-lg text-muted-foreground">
                                    / {result.max_score}
                                </span>
                            </p>
                        )}
                        <TonePill
                            tone={
                                result.status === 'graded'
                                    ? 'success'
                                    : 'warning'
                            }
                        >
                            {result.status_label}
                        </TonePill>
                    </div>
                    {result.feedback && (
                        <p className="mt-3 rounded-xl bg-muted/60 p-3 text-sm whitespace-pre-line">
                            {result.feedback}
                        </p>
                    )}
                    {!quiz.show_results && (
                        <p className="mt-2 text-sm text-muted-foreground">
                            Your teacher will share results later.
                        </p>
                    )}
                </section>
            )}

            {questions.length > 0 && (
                <ol className="grid gap-4">
                    {questions.map((question, position) => (
                        <li
                            key={question.id}
                            className="rounded-2xl border bg-card p-5 shadow-sm"
                        >
                            <div className="mb-3 flex items-start gap-3">
                                <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-navy-700 text-xs font-bold text-white">
                                    {position + 1}
                                </span>
                                <p className="flex-1 font-medium whitespace-pre-line">
                                    {question.prompt}
                                </p>
                                <span className="shrink-0 text-xs text-muted-foreground">
                                    {question.marks}{' '}
                                    {question.marks === 1 ? 'mark' : 'marks'}
                                </span>
                            </div>
                            {current ? (
                                <AnswerInput
                                    question={question}
                                    value={answers[question.id]}
                                    onChange={(value) =>
                                        setAnswers((previous) => ({
                                            ...previous,
                                            [question.id]: value,
                                        }))
                                    }
                                />
                            ) : question.review ? (
                                <div className="space-y-2 text-sm">
                                    <p className="rounded-xl bg-muted/60 p-3 whitespace-pre-line">
                                        {reviewText(
                                            question,
                                            question.review.value,
                                        )}
                                    </p>
                                    {question.review.is_correct === true && (
                                        <p className="flex items-center gap-1 text-emerald-700 dark:text-emerald-300">
                                            <CheckCircle2
                                                className="size-4"
                                                aria-hidden
                                            />{' '}
                                            Correct ·{' '}
                                            {question.review.marks_awarded}{' '}
                                            marks
                                        </p>
                                    )}
                                    {question.review.is_correct === false && (
                                        <p className="flex items-center gap-1 text-rose-700 dark:text-rose-300">
                                            <XCircle
                                                className="size-4"
                                                aria-hidden
                                            />{' '}
                                            {question.review.marks_awarded ?? 0}{' '}
                                            marks
                                        </p>
                                    )}
                                    {question.review.is_correct === null &&
                                        question.review.marks_awarded !==
                                            null && (
                                            <p>
                                                {question.review.marks_awarded}{' '}
                                                marks
                                            </p>
                                        )}
                                    {question.answer_key &&
                                        question.answer_key.length > 0 &&
                                        question.review.is_correct ===
                                            false && (
                                            <p className="text-muted-foreground">
                                                Correct answer:{' '}
                                                {reviewText(
                                                    question,
                                                    question.type ===
                                                        'multiple_choice'
                                                        ? question.answer_key
                                                        : question
                                                              .answer_key[0],
                                                )}
                                            </p>
                                        )}
                                    {question.review.feedback && (
                                        <p className="text-muted-foreground">
                                            Teacher: {question.review.feedback}
                                        </p>
                                    )}
                                    {question.explanation && (
                                        <p className="text-muted-foreground">
                                            {question.explanation}
                                        </p>
                                    )}
                                </div>
                            ) : null}
                        </li>
                    ))}
                </ol>
            )}

            {current && (
                <div className="sticky bottom-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border bg-card/95 p-4 shadow-lg backdrop-blur">
                    <p className="text-sm text-muted-foreground">
                        {answered} of {questions.length} answered. Your answers
                        are saved on this device as you go.
                    </p>
                    <Button
                        onClick={() => {
                            if (
                                answered < questions.length &&
                                !window.confirm(
                                    'Some questions are unanswered. Hand in anyway?',
                                )
                            ) {
                                return;
                            }

                            handIn();
                        }}
                        disabled={submitting}
                    >
                        {submitting ? <Spinner /> : <Send aria-hidden />}
                        Hand in
                    </Button>
                </div>
            )}
        </div>
    );
}

LearnQuiz.layout = {
    breadcrumbs: [{ title: 'My learning', href: index() }],
};
