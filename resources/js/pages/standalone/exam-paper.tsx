import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    CloudOff,
    Flag,
    Loader2,
    Send,
    Timer,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { save, show, submit } from '@/routes/exams/my';
import type { Answer } from '@/types/exams';
import { mark } from '@/types/exams';
import type { QuestionType, QuizOption } from '@/types/learning';

type Question = {
    id: number;
    type: QuestionType;
    prompt: string;
    marks: number;
    negative_marks: number;
    options: QuizOption[];
    answer: Answer | null;
};

type Props = {
    exam: {
        id: number;
        title: string;
        subject: string | null;
        instructions: string | null;
        total_marks: number;
        negative_marking: boolean;
    };
    attempt: {
        id: number;
        attempt_no: number;
        deadline: string;
        server_now: string;
        last_saved_at: string | null;
    };
    student: string;
    questions: Question[];
};

type SaveState = 'saved' | 'saving' | 'unsaved' | 'offline';

/** How often answers are saved (and the session kept alive) while the exam is open. */
const SAVE_EVERY_MS = 30_000;
/** Pause after typing before saving. */
const DEBOUNCE_MS = 1_500;

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function isAnswered(value: Answer | null | undefined): boolean {
    if (value === null || value === undefined) {
        return false;
    }

    return Array.isArray(value) ? value.length > 0 : value.trim() !== '';
}

function formatLeft(ms: number): string {
    const total = Math.max(0, Math.ceil(ms / 1000));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const seconds = total % 60;
    const pad = (value: number): string => String(value).padStart(2, '0');

    return hours > 0
        ? `${hours}:${pad(minutes)}:${pad(seconds)}`
        : `${pad(minutes)}:${pad(seconds)}`;
}

function AnswerInput({
    question,
    value,
    onChange,
}: {
    question: Question;
    value: Answer | null;
    onChange: (value: Answer | null) => void;
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
                aria-label="Choose one answer"
                className={cn(
                    'grid gap-2',
                    question.type === 'true_false' && 'sm:grid-cols-2',
                )}
            >
                {options.map((option, position) => {
                    const selected = value === option.id;

                    return (
                        <button
                            key={option.id}
                            type="button"
                            role="radio"
                            aria-checked={selected}
                            onClick={() =>
                                onChange(selected ? null : option.id)
                            }
                            className={cn(
                                'flex items-center gap-3 rounded-xl border-2 px-4 py-3 text-left transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                selected
                                    ? 'border-primary bg-primary/10'
                                    : 'border-border bg-card hover:border-primary/40 hover:bg-muted/50',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-7 shrink-0 items-center justify-center rounded-full border-2 text-xs font-bold',
                                    selected
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-muted-foreground/40 text-muted-foreground',
                                )}
                            >
                                {question.type === 'true_false'
                                    ? option.text.charAt(0)
                                    : String.fromCharCode(65 + position)}
                            </span>
                            <span className="break-words">{option.text}</span>
                        </button>
                    );
                })}
            </div>
        );
    }

    if (question.type === 'multiple_choice') {
        const chosen = Array.isArray(value) ? value : [];

        return (
            <fieldset className="grid gap-2">
                <legend className="mb-2 text-sm text-muted-foreground">
                    Choose every correct answer.
                </legend>
                {question.options.map((option, position) => {
                    const checked = chosen.includes(option.id);

                    return (
                        <label
                            key={option.id}
                            className={cn(
                                'flex cursor-pointer items-center gap-3 rounded-xl border-2 px-4 py-3 transition',
                                checked
                                    ? 'border-primary bg-primary/10'
                                    : 'border-border bg-card hover:border-primary/40 hover:bg-muted/50',
                            )}
                        >
                            <Checkbox
                                checked={checked}
                                onCheckedChange={(next) => {
                                    const list =
                                        next === true
                                            ? [...chosen, option.id]
                                            : chosen.filter(
                                                  (id) => id !== option.id,
                                              );

                                    onChange(list.length > 0 ? list : null);
                                }}
                            />
                            <span className="text-xs font-bold text-muted-foreground">
                                {String.fromCharCode(65 + position)}
                            </span>
                            <span className="break-words">{option.text}</span>
                        </label>
                    );
                })}
            </fieldset>
        );
    }

    const text = typeof value === 'string' ? value : '';

    if (question.type === 'short_answer') {
        return (
            <Input
                aria-label="Your answer"
                value={text}
                maxLength={500}
                onChange={(event) => onChange(event.target.value || null)}
                placeholder="Type your answer"
                className="h-12 text-base"
            />
        );
    }

    const words = text.trim() === '' ? 0 : text.trim().split(/\s+/).length;

    return (
        <div className="space-y-1">
            <Textarea
                aria-label="Your answer"
                rows={12}
                value={text}
                maxLength={20000}
                onChange={(event) => onChange(event.target.value || null)}
                placeholder="Write your answer"
                className="text-base leading-relaxed"
            />
            <p className="text-right text-xs text-muted-foreground">
                {words} {words === 1 ? 'word' : 'words'}
            </p>
        </div>
    );
}

/**
 * The exam paper: one question at a time with a navigator, a countdown driven by the
 * server's deadline, autosave (after each change and every 30 seconds) and hand-in,
 * automatically when time runs out. The server enforces the deadline; this page only
 * keeps the student informed.
 */
export default function ExamPaper({
    exam,
    attempt,
    student,
    questions,
}: Props) {
    const [answers, setAnswers] = useState<Record<number, Answer | null>>(() =>
        Object.fromEntries(
            questions.map((question) => [question.id, question.answer]),
        ),
    );
    const [current, setCurrent] = useState(0);
    const [flags, setFlags] = useState<number[]>(() => {
        try {
            return JSON.parse(
                window.localStorage.getItem(`exam-flags-${attempt.id}`) ?? '[]',
            ) as number[];
        } catch {
            return [];
        }
    });
    const [saveState, setSaveState] = useState<SaveState>('saved');
    const [savedAt, setSavedAt] = useState<string | null>(
        attempt.last_saved_at,
    );
    const [confirming, setConfirming] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    const offset = useRef(new Date(attempt.server_now).getTime() - Date.now());
    const [left, setLeft] = useState(
        () =>
            new Date(attempt.deadline).getTime() -
            (Date.now() + offset.current),
    );
    const dirty = useRef(new Set<number>());
    const latest = useRef(answers);
    const finished = useRef(false);
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

    latest.current = answers;

    const finish = useCallback(
        (timedOut: boolean): void => {
            if (finished.current) {
                return;
            }

            finished.current = true;
            setSubmitting(true);
            router.post(
                submit.url({ online_exam: exam.id, attempt: attempt.id }),
                { answers: latest.current, timed_out: timedOut },
                {
                    onError: () => {
                        finished.current = false;
                        setSubmitting(false);
                    },
                },
            );
        },
        [exam.id, attempt.id],
    );

    const persist = useCallback(
        async (keepalive = false): Promise<void> => {
            if (finished.current) {
                return;
            }

            const ids = [...dirty.current];
            const payload = Object.fromEntries(
                ids.map((id) => [id, latest.current[id] ?? null]),
            );
            dirty.current.clear();

            if (ids.length > 0) {
                setSaveState('saving');
            }

            try {
                const response = await fetch(
                    save.url({ online_exam: exam.id, attempt: attempt.id }),
                    {
                        method: 'PUT',
                        credentials: 'same-origin',
                        keepalive,
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-XSRF-TOKEN': xsrfToken(),
                        },
                        body: JSON.stringify({ answers: payload }),
                    },
                );

                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                const result = (await response.json()) as {
                    status: string;
                    saved_at: string | null;
                    server_now: string;
                };

                offset.current =
                    new Date(result.server_now).getTime() - Date.now();

                if (result.status !== 'in_progress') {
                    finished.current = true;
                    router.visit(show.url(exam.id));

                    return;
                }

                setSavedAt(result.saved_at);
                setSaveState(dirty.current.size > 0 ? 'unsaved' : 'saved');
            } catch {
                ids.forEach((id) => dirty.current.add(id));
                setSaveState('offline');
            }
        },
        [exam.id, attempt.id],
    );

    const answer = (questionId: number, value: Answer | null): void => {
        setAnswers((previous) => ({ ...previous, [questionId]: value }));
        dirty.current.add(questionId);
        setSaveState('unsaved');

        if (debounce.current) {
            clearTimeout(debounce.current);
        }

        debounce.current = setTimeout(() => void persist(), DEBOUNCE_MS);
    };

    useEffect(() => {
        const tick = window.setInterval(() => {
            const remaining =
                new Date(attempt.deadline).getTime() -
                (Date.now() + offset.current);
            setLeft(remaining);

            if (remaining <= 0) {
                finish(true);
            }
        }, 500);
        const autosave = window.setInterval(
            () => void persist(),
            SAVE_EVERY_MS,
        );
        const onHide = (): void => {
            if (
                document.visibilityState === 'hidden' &&
                dirty.current.size > 0
            ) {
                void persist(true);
            }
        };
        const onLeave = (event: BeforeUnloadEvent): void => {
            if (!finished.current) {
                event.preventDefault();
            }
        };

        document.addEventListener('visibilitychange', onHide);
        window.addEventListener('beforeunload', onLeave);

        return () => {
            window.clearInterval(tick);
            window.clearInterval(autosave);
            document.removeEventListener('visibilitychange', onHide);
            window.removeEventListener('beforeunload', onLeave);

            if (debounce.current) {
                clearTimeout(debounce.current);
            }
        };
    }, [attempt.deadline, finish, persist]);

    useEffect(() => {
        try {
            window.localStorage.setItem(
                `exam-flags-${attempt.id}`,
                JSON.stringify(flags),
            );
        } catch {
            // Flags are a convenience; the exam works without storage.
        }
    }, [flags, attempt.id]);

    const question = questions[current];
    const answeredCount = questions.filter((item) =>
        isAnswered(answers[item.id]),
    ).length;
    const unanswered = questions.length - answeredCount;
    const flagged = flags.filter((id) =>
        questions.some((item) => item.id === id),
    ).length;
    const urgent = left <= 60_000;
    const warning = left <= 5 * 60_000;

    return (
        <div className="min-h-screen bg-gradient-to-b from-navy-50 to-background dark:from-navy-950 dark:to-background">
            <Head title={exam.title} />

            <header className="sticky top-0 z-20 border-b bg-card/95 shadow-sm backdrop-blur">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-3 px-4 py-3">
                    <div className="min-w-0 basis-full sm:flex-1 sm:basis-0">
                        <h1 className="truncate font-semibold">{exam.title}</h1>
                        <p className="truncate text-xs text-muted-foreground">
                            {exam.subject} · {student} · attempt{' '}
                            {attempt.attempt_no}
                        </p>
                    </div>
                    <p
                        className="mr-auto flex items-center gap-1.5 text-xs text-muted-foreground sm:mr-0"
                        aria-live="polite"
                    >
                        {saveState === 'saving' && (
                            <>
                                <Loader2
                                    className="size-3.5 animate-spin"
                                    aria-hidden
                                />
                                Saving…
                            </>
                        )}
                        {saveState === 'saved' && (
                            <>
                                <CheckCircle2
                                    className="size-3.5 text-emerald-600"
                                    aria-hidden
                                />
                                {savedAt ? 'All answers saved' : 'Ready'}
                            </>
                        )}
                        {saveState === 'unsaved' && 'Unsaved changes'}
                        {saveState === 'offline' && (
                            <span className="flex items-center gap-1.5 font-medium text-rose-700 dark:text-rose-300">
                                <CloudOff className="size-3.5" aria-hidden />
                                Not saved: check your connection
                            </span>
                        )}
                    </p>
                    <div
                        role="timer"
                        aria-label="Time left"
                        className={cn(
                            'flex items-center gap-2 rounded-xl px-3 py-1.5 font-mono text-lg font-bold tabular-nums',
                            urgent
                                ? 'animate-pulse bg-rose-600 text-white'
                                : warning
                                  ? 'bg-amber-100 text-amber-900 dark:bg-amber-500/20 dark:text-amber-200'
                                  : 'bg-navy-900 text-white dark:bg-navy-700',
                        )}
                    >
                        <Timer className="size-4" aria-hidden />
                        {formatLeft(left)}
                    </div>
                    <Button
                        onClick={() => setConfirming(true)}
                        disabled={submitting}
                    >
                        <Send aria-hidden /> Hand in
                    </Button>
                </div>
                <div
                    className="h-1 bg-muted"
                    role="progressbar"
                    aria-label="Questions answered"
                    aria-valuemin={0}
                    aria-valuemax={questions.length}
                    aria-valuenow={answeredCount}
                >
                    <div
                        className="h-full bg-primary transition-all"
                        style={{
                            width: `${(answeredCount / Math.max(1, questions.length)) * 100}%`,
                        }}
                    />
                </div>
            </header>

            {warning && !urgent && (
                <p className="mx-auto mt-4 flex max-w-6xl items-center gap-2 px-4 text-sm font-medium text-amber-800 dark:text-amber-200">
                    <AlertTriangle className="size-4" aria-hidden />
                    Less than five minutes left. Your exam is handed in
                    automatically when time runs out.
                </p>
            )}

            <main className="mx-auto grid max-w-6xl gap-6 px-4 py-6 lg:grid-cols-[1fr_16rem]">
                {question && (
                    <section
                        aria-labelledby="question-title"
                        className="rounded-2xl border bg-card p-6 shadow-sm"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <p
                                id="question-title"
                                className="text-sm font-semibold text-primary"
                            >
                                Question {current + 1} of {questions.length}
                            </p>
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                {mark(question.marks)}{' '}
                                {question.marks === 1 ? 'mark' : 'marks'}
                                {exam.negative_marking &&
                                    question.negative_marks > 0 &&
                                    ` · −${mark(question.negative_marks)} if wrong`}
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={
                                        flags.includes(question.id)
                                            ? 'secondary'
                                            : 'ghost'
                                    }
                                    aria-pressed={flags.includes(question.id)}
                                    onClick={() =>
                                        setFlags((list) =>
                                            list.includes(question.id)
                                                ? list.filter(
                                                      (id) =>
                                                          id !== question.id,
                                                  )
                                                : [...list, question.id],
                                        )
                                    }
                                >
                                    <Flag
                                        aria-hidden
                                        className={cn(
                                            flags.includes(question.id) &&
                                                'fill-gold-400 text-gold-600',
                                        )}
                                    />
                                    {flags.includes(question.id)
                                        ? 'Flagged'
                                        : 'Flag for review'}
                                </Button>
                            </div>
                        </div>
                        <p className="mt-3 text-lg break-words whitespace-pre-line">
                            {question.prompt}
                        </p>
                        <div className="mt-6">
                            <AnswerInput
                                key={question.id}
                                question={question}
                                value={answers[question.id] ?? null}
                                onChange={(value) => answer(question.id, value)}
                            />
                        </div>
                        <div className="mt-8 flex items-center justify-between gap-2">
                            <Button
                                variant="outline"
                                disabled={current === 0}
                                onClick={() => setCurrent((index) => index - 1)}
                            >
                                <ChevronLeft aria-hidden /> Previous
                            </Button>
                            {current < questions.length - 1 ? (
                                <Button
                                    onClick={() =>
                                        setCurrent((index) => index + 1)
                                    }
                                >
                                    Next <ChevronRight aria-hidden />
                                </Button>
                            ) : (
                                <Button onClick={() => setConfirming(true)}>
                                    <Send aria-hidden /> Review and hand in
                                </Button>
                            )}
                        </div>
                    </section>
                )}

                <aside className="h-fit space-y-4 lg:sticky lg:top-24">
                    <nav
                        aria-label="Questions"
                        className="rounded-2xl border bg-card p-4 shadow-sm"
                    >
                        <p className="text-sm font-semibold">
                            {answeredCount} of {questions.length} answered
                        </p>
                        <ol className="mt-3 grid grid-cols-6 gap-1.5 lg:grid-cols-5">
                            {questions.map((item, position) => {
                                const done = isAnswered(answers[item.id]);
                                const isFlagged = flags.includes(item.id);

                                return (
                                    <li key={item.id}>
                                        <button
                                            type="button"
                                            onClick={() => setCurrent(position)}
                                            aria-current={
                                                position === current
                                                    ? 'step'
                                                    : undefined
                                            }
                                            aria-label={`Question ${position + 1}${done ? ', answered' : ', not answered'}${isFlagged ? ', flagged' : ''}`}
                                            className={cn(
                                                'relative flex aspect-square w-full items-center justify-center rounded-lg border text-xs font-semibold transition',
                                                done
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'bg-background hover:bg-muted',
                                                position === current &&
                                                    'ring-2 ring-gold-400 ring-offset-2 ring-offset-card',
                                            )}
                                        >
                                            {position + 1}
                                            {isFlagged && (
                                                <span
                                                    className="absolute -top-1 -right-1 size-2.5 rounded-full bg-gold-500 ring-2 ring-card"
                                                    aria-hidden
                                                />
                                            )}
                                        </button>
                                    </li>
                                );
                            })}
                        </ol>
                        <ul className="mt-4 space-y-1 text-xs text-muted-foreground">
                            <li className="flex items-center gap-2">
                                <span
                                    className="size-3 rounded bg-primary"
                                    aria-hidden
                                />
                                Answered
                            </li>
                            <li className="flex items-center gap-2">
                                <span
                                    className="size-3 rounded border bg-background"
                                    aria-hidden
                                />
                                Not answered
                            </li>
                            <li className="flex items-center gap-2">
                                <span
                                    className="size-3 rounded-full bg-gold-500"
                                    aria-hidden
                                />
                                Flagged for review
                            </li>
                        </ul>
                    </nav>
                    {exam.instructions && (
                        <details className="rounded-2xl border bg-card p-4 text-sm shadow-sm">
                            <summary className="cursor-pointer font-semibold">
                                Instructions
                            </summary>
                            <p className="mt-2 whitespace-pre-line text-muted-foreground">
                                {exam.instructions}
                            </p>
                        </details>
                    )}
                </aside>
            </main>

            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hand in your exam?</DialogTitle>
                        <DialogDescription>
                            You cannot change your answers after handing in.
                        </DialogDescription>
                    </DialogHeader>
                    <ul className="space-y-1 text-sm">
                        <li>
                            <strong>{answeredCount}</strong> of{' '}
                            {questions.length} questions answered
                        </li>
                        {unanswered > 0 && (
                            <li className="text-amber-700 dark:text-amber-300">
                                {unanswered} not answered
                            </li>
                        )}
                        {flagged > 0 && (
                            <li className="text-amber-700 dark:text-amber-300">
                                {flagged} flagged for review
                            </li>
                        )}
                        <li className="text-muted-foreground">
                            Time left: {formatLeft(left)}
                        </li>
                    </ul>
                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setConfirming(false)}
                        >
                            Keep working
                        </Button>
                        <Button
                            onClick={() => finish(false)}
                            disabled={submitting}
                        >
                            {submitting && <Spinner />} Hand in now
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {submitting && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-background/80 backdrop-blur-sm"
                    role="status"
                >
                    <p className="flex items-center gap-2 rounded-xl border bg-card px-5 py-3 font-medium shadow-lg">
                        <Spinner /> Handing in…
                    </p>
                </div>
            )}
        </div>
    );
}
