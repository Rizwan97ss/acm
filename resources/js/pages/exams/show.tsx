import { Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    MinusCircle,
    PlayCircle,
    Trophy,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { answerText, keyText } from '@/components/assessment/answer-text';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useFormat } from '@/hooks/use-format';
import { cn } from '@/lib/utils';
import { index, start } from '@/routes/exams/my';
import type { Answer, ExamState } from '@/types/exams';
import { mark, stateTone } from '@/types/exams';
import type { QuestionType, QuizOption } from '@/types/learning';

type Props = {
    exam: {
        id: number;
        title: string;
        subject: string;
        instructions: string | null;
        starts_at: string;
        ends_at: string;
        duration_minutes: number;
        total_marks: number;
        passing_marks: number;
        questions_count: number;
        max_attempts: number;
        attempts_used: number;
        state: ExamState;
        state_label: string;
        can_start: boolean;
        handed_in: boolean;
        results_published: boolean;
        negative_marking: boolean;
    };
    student: { id: number; name: string };
    isExaminee: boolean;
    result: {
        score: number;
        max_score: number;
        percentage: number;
        grade: string | null;
        passed: boolean;
        feedback: string | null;
        rank: number | null;
        ranked_out_of: number | null;
        submitted_at: string | null;
        questions: {
            number: number;
            type: QuestionType;
            prompt: string;
            marks: number;
            options: QuizOption[];
            answer_key: string[];
            explanation: string | null;
            answer: Answer | null;
            is_correct: boolean | null;
            marks_awarded: number | null;
            feedback: string | null;
        }[];
    } | null;
};

function ordinal(value: number): string {
    const suffix = ['th', 'st', 'nd', 'rd'];
    const remainder = value % 100;

    return `${value}${suffix[(remainder - 20) % 10] ?? suffix[remainder] ?? suffix[0]}`;
}

export default function MyExam({ exam, student, isExaminee, result }: Props) {
    const { dateTime } = useFormat();
    const [confirming, setConfirming] = useState(false);
    const [starting, setStarting] = useState(false);
    const attemptsLeft = exam.max_attempts - exam.attempts_used;

    const begin = (): void => {
        router.post(
            start.url(exam.id),
            {},
            {
                onStart: () => setStarting(true),
                onFinish: () => setStarting(false),
            },
        );
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={exam.title}
                description={
                    isExaminee
                        ? exam.subject
                        : `${exam.subject} · ${student.name}`
                }
                actions={
                    <Button asChild variant="outline">
                        <Link
                            href={index(
                                isExaminee
                                    ? undefined
                                    : {
                                          query: {
                                              student: String(student.id),
                                          },
                                      },
                            )}
                        >
                            <ArrowLeft aria-hidden /> All exams
                        </Link>
                    </Button>
                }
            />

            <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
                <div className="space-y-6">
                    {result ? (
                        <section
                            className={cn(
                                'overflow-hidden rounded-2xl border shadow-sm',
                                result.passed
                                    ? 'border-emerald-300 dark:border-emerald-500/40'
                                    : 'border-rose-300 dark:border-rose-500/40',
                            )}
                        >
                            <div
                                className={cn(
                                    'flex flex-wrap items-center gap-6 p-6 text-white',
                                    result.passed
                                        ? 'bg-gradient-to-br from-emerald-600 to-teal-700'
                                        : 'bg-gradient-to-br from-rose-600 to-crimson-700',
                                )}
                            >
                                <Trophy
                                    className="size-10 opacity-80"
                                    aria-hidden
                                />
                                <div>
                                    <p className="text-sm opacity-90">
                                        {result.passed
                                            ? 'Passed'
                                            : 'Not passed'}
                                    </p>
                                    <p className="text-3xl font-bold tabular-nums">
                                        {mark(result.score)} /{' '}
                                        {mark(result.max_score)}
                                    </p>
                                </div>
                                <dl className="ml-auto flex gap-6 text-sm">
                                    <div>
                                        <dt className="opacity-80">
                                            Percentage
                                        </dt>
                                        <dd className="text-xl font-semibold tabular-nums">
                                            {mark(result.percentage)}%
                                        </dd>
                                    </div>
                                    {result.grade && (
                                        <div>
                                            <dt className="opacity-80">
                                                Grade
                                            </dt>
                                            <dd className="text-xl font-semibold">
                                                {result.grade}
                                            </dd>
                                        </div>
                                    )}
                                    {result.rank !== null && (
                                        <div>
                                            <dt className="opacity-80">Rank</dt>
                                            <dd className="text-xl font-semibold">
                                                {ordinal(result.rank)}
                                                <span className="text-sm font-normal opacity-80">
                                                    {' '}
                                                    of {result.ranked_out_of}
                                                </span>
                                            </dd>
                                        </div>
                                    )}
                                </dl>
                            </div>
                            {result.feedback && (
                                <div className="bg-card p-5">
                                    <p className="text-sm font-semibold">
                                        Teacher’s feedback
                                    </p>
                                    <p className="mt-1 text-sm whitespace-pre-line">
                                        {result.feedback}
                                    </p>
                                </div>
                            )}
                        </section>
                    ) : (
                        <section className="rounded-2xl border bg-card p-6 shadow-sm">
                            {exam.instructions ? (
                                <>
                                    <h2 className="font-semibold">
                                        Instructions
                                    </h2>
                                    <p className="mt-2 text-sm whitespace-pre-line">
                                        {exam.instructions}
                                    </p>
                                </>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No special instructions.
                                </p>
                            )}
                            <ul className="mt-4 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                                <li>
                                    You have {exam.duration_minutes} minutes
                                    from when you start, and the exam closes at{' '}
                                    {dateTime(exam.ends_at)}.
                                </li>
                                <li>
                                    Your answers save automatically as you work.
                                    If time runs out, the answers saved so far
                                    are handed in for you.
                                </li>
                                {exam.negative_marking && (
                                    <li className="font-medium text-amber-700 dark:text-amber-300">
                                        Negative marking: wrong objective
                                        answers lose marks; leaving a question
                                        blank does not.
                                    </li>
                                )}
                            </ul>

                            {isExaminee && exam.can_start && (
                                <Button
                                    size="lg"
                                    className="mt-6"
                                    onClick={() => setConfirming(true)}
                                >
                                    <PlayCircle aria-hidden /> Start exam
                                </Button>
                            )}
                            {exam.state === 'scheduled' && (
                                <p className="mt-6 rounded-xl bg-sky-50 p-3 text-sm text-sky-900 dark:bg-sky-500/10 dark:text-sky-200">
                                    This exam opens at{' '}
                                    {dateTime(exam.starts_at)}.
                                </p>
                            )}
                            {exam.handed_in && !exam.results_published && (
                                <p className="mt-6 flex items-center gap-2 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-200">
                                    <CheckCircle2
                                        className="size-4"
                                        aria-hidden
                                    />
                                    Handed in. Results appear here when your
                                    teacher publishes them.
                                </p>
                            )}
                            {isExaminee &&
                                !exam.can_start &&
                                !exam.handed_in &&
                                (exam.state === 'completed' ||
                                    exam.state === 'results_published') && (
                                    <p className="mt-6 rounded-xl bg-muted p-3 text-sm text-muted-foreground">
                                        This exam has closed.
                                    </p>
                                )}
                        </section>
                    )}

                    {result && (
                        <section className="space-y-3">
                            <h2 className="text-lg font-semibold">
                                Your answers
                            </h2>
                            {result.questions.map((question) => {
                                const key = keyText(
                                    question.type,
                                    question.options,
                                    question.answer_key,
                                );

                                return (
                                    <article
                                        key={question.number}
                                        className="rounded-2xl border bg-card p-5 shadow-sm"
                                    >
                                        <div className="flex items-start gap-3">
                                            <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-navy-700 text-xs font-bold text-white">
                                                {question.number}
                                            </span>
                                            <div className="min-w-0 flex-1 space-y-2">
                                                <div className="flex flex-wrap items-start justify-between gap-2">
                                                    <p className="font-medium break-words whitespace-pre-line">
                                                        {question.prompt}
                                                    </p>
                                                    <span className="shrink-0 text-sm font-semibold tabular-nums">
                                                        {mark(
                                                            question.marks_awarded,
                                                        )}{' '}
                                                        / {mark(question.marks)}
                                                    </span>
                                                </div>
                                                <div className="rounded-xl bg-muted/60 p-3 text-sm break-words whitespace-pre-line">
                                                    <span className="text-xs text-muted-foreground">
                                                        Your answer:{' '}
                                                    </span>
                                                    {answerText(
                                                        question.type,
                                                        question.options,
                                                        question.answer,
                                                        'Not answered',
                                                    )}
                                                </div>
                                                {question.answer === null ? (
                                                    <p className="flex items-center gap-1 text-sm text-muted-foreground">
                                                        <MinusCircle
                                                            className="size-4"
                                                            aria-hidden
                                                        />
                                                        Not answered
                                                    </p>
                                                ) : question.is_correct ===
                                                  true ? (
                                                    <p className="flex items-center gap-1 text-sm text-emerald-700 dark:text-emerald-300">
                                                        <CheckCircle2
                                                            className="size-4"
                                                            aria-hidden
                                                        />
                                                        Correct
                                                    </p>
                                                ) : question.is_correct ===
                                                  false ? (
                                                    <p className="flex items-center gap-1 text-sm text-rose-700 dark:text-rose-300">
                                                        <XCircle
                                                            className="size-4"
                                                            aria-hidden
                                                        />
                                                        Not correct
                                                    </p>
                                                ) : null}
                                                {key &&
                                                    question.is_correct !==
                                                        true && (
                                                        <p className="text-sm text-emerald-700 dark:text-emerald-300">
                                                            Correct answer:{' '}
                                                            {key}
                                                        </p>
                                                    )}
                                                {question.explanation && (
                                                    <p className="text-sm text-muted-foreground">
                                                        {question.explanation}
                                                    </p>
                                                )}
                                                {question.feedback && (
                                                    <p className="rounded-lg border-l-4 border-gold-400 bg-gold-50 px-3 py-2 text-sm dark:bg-gold-500/10">
                                                        {question.feedback}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </article>
                                );
                            })}
                        </section>
                    )}
                </div>

                <aside className="h-fit rounded-2xl border bg-card p-5 shadow-sm">
                    <TonePill tone={stateTone[exam.state]}>
                        {exam.state_label}
                    </TonePill>
                    <dl className="mt-4 space-y-3 text-sm">
                        {[
                            ['Opens', dateTime(exam.starts_at)],
                            ['Closes', dateTime(exam.ends_at)],
                            ['Duration', `${exam.duration_minutes} minutes`],
                            ['Questions', String(exam.questions_count)],
                            [
                                'Marks',
                                `${mark(exam.total_marks)} (pass ${mark(exam.passing_marks)})`,
                            ],
                            [
                                'Attempts',
                                `${exam.attempts_used} of ${exam.max_attempts} used`,
                            ],
                        ].map(([label, value]) => (
                            <div
                                key={label}
                                className="flex justify-between gap-3"
                            >
                                <dt className="text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="text-right font-medium">
                                    {value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </aside>
            </div>

            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Start the exam now?</DialogTitle>
                        <DialogDescription>
                            The {exam.duration_minutes}-minute timer starts as
                            soon as you begin and keeps running even if you
                            close the page.
                            {attemptsLeft === 1
                                ? ' This is your only attempt.'
                                : ` You have ${attemptsLeft} attempts left.`}
                        </DialogDescription>
                    </DialogHeader>
                    {exam.negative_marking && (
                        <p className="flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                            <AlertTriangle
                                className="mt-0.5 size-4 shrink-0"
                                aria-hidden
                            />
                            Wrong objective answers lose marks. If unsure, you
                            may leave a question blank.
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setConfirming(false)}
                        >
                            Not yet
                        </Button>
                        <Button onClick={begin} disabled={starting}>
                            {starting && <Spinner />} Start now
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

MyExam.layout = {
    breadcrumbs: [{ title: 'Exams', href: index() }],
};
