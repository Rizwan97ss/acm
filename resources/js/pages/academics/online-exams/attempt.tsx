import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, MinusCircle, XCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { answerText, keyText } from '@/components/assessment/answer-text';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useFormat } from '@/hooks/use-format';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/academics/online-exams';
import { grade } from '@/routes/academics/online-exams/attempts';
import type { Answer, AttemptStatus, OnlineExam } from '@/types/exams';
import { attemptTone, mark } from '@/types/exams';
import type { QuestionType, QuizOption } from '@/types/learning';

type Item = {
    number: number;
    question: {
        id: number;
        type: QuestionType;
        type_label: string;
        prompt: string;
        marks: number;
        min_marks: number;
        max_marks: number;
        options: QuizOption[];
        answer_key: string[];
        hand_marked: boolean;
    };
    answer: {
        id: number;
        value: Answer | null;
        is_correct: boolean | null;
        marks_awarded: number | null;
        feedback: string | null;
    } | null;
};

type Props = {
    exam: OnlineExam;
    attempt: {
        id: number;
        student: string;
        admission_no: string;
        attempt_no: number;
        status: AttemptStatus;
        status_label: string;
        score: number | null;
        max_score: number;
        percentage: number | null;
        grade: string | null;
        passed: boolean | null;
        feedback: string | null;
        started_at: string;
        submitted_at: string | null;
        submitted_by: string | null;
    };
    items: Item[];
    can: { grade: boolean };
};

type Entry = { marks: number | ''; feedback: string };

export default function OnlineExamAttempt({
    exam,
    attempt,
    items,
    can,
}: Props) {
    const { dateTime } = useFormat();
    const form = useForm<{ marks: Record<number, Entry>; feedback: string }>({
        marks: Object.fromEntries(
            items.flatMap((item) =>
                item.answer
                    ? [
                          [
                              item.answer.id,
                              {
                                  marks: item.answer.marks_awarded ?? '',
                                  feedback: item.answer.feedback ?? '',
                              },
                          ],
                      ]
                    : [],
            ),
        ),
        feedback: attempt.feedback ?? '',
    });

    const setEntry = (answerId: number, changes: Partial<Entry>): void =>
        form.setData('marks', {
            ...form.data.marks,
            [answerId]: { ...form.data.marks[answerId], ...changes },
        });

    const total = Math.max(
        0,
        Object.values(form.data.marks).reduce(
            (sum, entry) =>
                sum + (entry.marks === '' ? 0 : Number(entry.marks)),
            0,
        ),
    );
    const unmarked = Object.values(form.data.marks).filter(
        (entry) => entry.marks === '',
    ).length;

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.put(grade.url({ online_exam: exam.id, attempt: attempt.id }), {
            preserveScroll: true,
        });
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={`${can.grade ? 'Marking' : 'Answers'}: ${attempt.student}`}
                description={`${exam.title} · ${attempt.admission_no} · attempt ${attempt.attempt_no}`}
                actions={
                    <Button asChild variant="outline">
                        <Link href={show(exam.id)}>
                            <ArrowLeft aria-hidden /> Back to exam
                        </Link>
                    </Button>
                }
            />
            <div className="flex flex-wrap gap-2 text-sm">
                <TonePill tone={attemptTone[attempt.status]}>
                    {attempt.status_label}
                </TonePill>
                <TonePill tone="neutral">
                    Started {dateTime(attempt.started_at)}
                </TonePill>
                <TonePill tone="neutral">
                    Handed in {dateTime(attempt.submitted_at, 'not yet')}
                    {attempt.submitted_by === 'timeout' && ' (time ran out)'}
                </TonePill>
                {attempt.grade && (
                    <TonePill tone={attempt.passed ? 'success' : 'danger'}>
                        {mark(attempt.percentage)}% · {attempt.grade} ·{' '}
                        {attempt.passed ? 'Pass' : 'Fail'}
                    </TonePill>
                )}
                {!can.grade && exam.results_status !== 'pending' && (
                    <TonePill tone="info">
                        Results approved: marks locked
                    </TonePill>
                )}
            </div>

            <form onSubmit={submit} className="grid max-w-4xl gap-4">
                {items.map((item) => {
                    const answer = item.answer;
                    const entry = answer ? form.data.marks[answer.id] : null;
                    const waiting =
                        answer !== null &&
                        answer.value !== null &&
                        answer.marks_awarded === null;
                    const key = keyText(
                        item.question.type,
                        item.question.options,
                        item.question.answer_key,
                    );

                    return (
                        <article
                            key={item.question.id}
                            className={cn(
                                'rounded-2xl border bg-card p-5 shadow-sm',
                                waiting &&
                                    'border-gold-400 ring-1 ring-gold-300',
                            )}
                        >
                            <div className="flex items-start gap-3">
                                <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-navy-700 text-xs font-bold text-white">
                                    {item.number}
                                </span>
                                <div className="min-w-0 flex-1 space-y-2">
                                    <p className="font-medium break-words whitespace-pre-line">
                                        {item.question.prompt}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {item.question.type_label} ·{' '}
                                        {mark(item.question.marks)}{' '}
                                        {item.question.marks === 1
                                            ? 'mark'
                                            : 'marks'}
                                        {item.question.min_marks < 0 &&
                                            ` · −${mark(-item.question.min_marks)} if wrong`}
                                    </p>
                                    <div className="rounded-xl bg-muted/60 p-3 text-sm break-words whitespace-pre-line">
                                        {answerText(
                                            item.question.type,
                                            item.question.options,
                                            answer?.value,
                                        )}
                                    </div>
                                    {key && (
                                        <p className="text-sm text-emerald-700 dark:text-emerald-300">
                                            Correct answer: {key}
                                        </p>
                                    )}
                                    {answer?.is_correct === true && (
                                        <p className="flex items-center gap-1 text-sm text-emerald-700 dark:text-emerald-300">
                                            <CheckCircle2
                                                className="size-4"
                                                aria-hidden
                                            />
                                            Correct
                                        </p>
                                    )}
                                    {answer?.is_correct === false &&
                                        answer.value !== null && (
                                            <p className="flex items-center gap-1 text-sm text-rose-700 dark:text-rose-300">
                                                <XCircle
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                                Incorrect
                                            </p>
                                        )}
                                    {answer?.value === null && (
                                        <p className="flex items-center gap-1 text-sm text-muted-foreground">
                                            <MinusCircle
                                                className="size-4"
                                                aria-hidden
                                            />
                                            Not answered
                                        </p>
                                    )}
                                    {answer && entry && (
                                        <div className="grid gap-3 pt-1 sm:grid-cols-[9rem_1fr]">
                                            <FormField
                                                id={`marks-${answer.id}`}
                                                label={`Marks (${mark(item.question.min_marks)} to ${mark(item.question.max_marks)})`}
                                            >
                                                <Input
                                                    id={`marks-${answer.id}`}
                                                    type="number"
                                                    step={0.25}
                                                    min={
                                                        item.question.min_marks
                                                    }
                                                    max={
                                                        item.question.max_marks
                                                    }
                                                    disabled={!can.grade}
                                                    value={entry.marks}
                                                    onChange={(event) =>
                                                        setEntry(answer.id, {
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
                                                        })
                                                    }
                                                />
                                            </FormField>
                                            <FormField
                                                id={`feedback-${answer.id}`}
                                                label="Feedback for the student"
                                            >
                                                <Input
                                                    id={`feedback-${answer.id}`}
                                                    disabled={!can.grade}
                                                    value={entry.feedback}
                                                    onChange={(event) =>
                                                        setEntry(answer.id, {
                                                            feedback:
                                                                event.target
                                                                    .value,
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

                <div className="sticky bottom-4 grid gap-3 rounded-2xl border bg-card/95 p-5 shadow-lg backdrop-blur">
                    <FormField id="overall" label="Overall feedback">
                        <Textarea
                            id="overall"
                            rows={2}
                            disabled={!can.grade}
                            value={form.data.feedback}
                            onChange={(event) =>
                                form.setData('feedback', event.target.value)
                            }
                        />
                    </FormField>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm">
                            Total{' '}
                            <strong className="tabular-nums">
                                {mark(total)} / {mark(attempt.max_score)}
                            </strong>
                            {unmarked > 0 && (
                                <span className="ml-2 text-muted-foreground">
                                    · {unmarked} still to mark
                                </span>
                            )}
                        </p>
                        {can.grade && (
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />} Save marks
                            </Button>
                        )}
                    </div>
                    {(form.errors as Partial<Record<string, string>>)
                        .attempt && (
                        <p className="text-sm text-destructive">
                            {
                                (form.errors as Partial<Record<string, string>>)
                                    .attempt
                            }
                        </p>
                    )}
                </div>
            </form>
        </div>
    );
}

OnlineExamAttempt.layout = {
    breadcrumbs: [{ title: 'Online exams', href: index() }],
};
