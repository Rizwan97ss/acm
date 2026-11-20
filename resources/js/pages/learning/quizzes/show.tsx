import { Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    CheckCircle2,
    Pencil,
    Plus,
    Send,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import QuestionEditor from '@/components/assessment/question-editor';
import ConfirmDelete from '@/components/confirm-delete';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
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
                            question={question}
                            types={questionTypes}
                            action={{
                                method: 'put',
                                url: updateQuestion.url(question.id),
                            }}
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
                        question={null}
                        types={questionTypes}
                        action={{
                            method: 'post',
                            url: storeQuestion.url(quiz.id),
                        }}
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
