import { Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    BarChart3,
    BookmarkPlus,
    CheckCircle2,
    Eye,
    EyeOff,
    Lock,
    LockOpen,
    Pencil,
    Plus,
    Send,
    Trash2,
    Undo2,
} from 'lucide-react';
import { useState } from 'react';
import BankPicker from '@/components/assessment/bank-picker';
import QuestionCard from '@/components/assessment/question-card';
import QuestionEditor from '@/components/assessment/question-editor';
import ConfirmDelete from '@/components/confirm-delete';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/hooks/use-format';
import { cn } from '@/lib/utils';
import {
    bank as saveToBank,
    destroy as destroyQuestion,
    move as moveQuestion,
    update as updateQuestion,
} from '@/routes/academics/exam-questions';
import {
    destroy,
    edit,
    index,
    publish,
    report,
    unpublish,
} from '@/routes/academics/online-exams';
import { show as showAttempt } from '@/routes/academics/online-exams/attempts';
import { store as storeQuestion } from '@/routes/academics/online-exams/questions';
import {
    approve,
    publish as publishResults,
    reopen,
    withdraw,
} from '@/routes/academics/online-exams/results';
import type {
    ExamQuestion,
    ExamSummary,
    OnlineExam,
    Standing,
} from '@/types/exams';
import { attemptTone, mark, stateTone } from '@/types/exams';
import type { QuestionType } from '@/types/learning';

type Props = {
    exam: OnlineExam & {
        questions_total: number;
        locked: boolean;
        author: string | null;
        results_approved_at: string | null;
    };
    questions: ExamQuestion[];
    summary: ExamSummary;
    standings: Standing[];
    questionTypes: { value: QuestionType; label: string }[];
    can: {
        edit: boolean;
        edit_questions: boolean;
        delete: boolean;
        release: boolean;
        mark: boolean;
        approve: boolean;
        publish_results: boolean;
        export: boolean;
        bank: boolean;
        save_to_bank: boolean;
    };
};

const post = (url: string): void =>
    router.post(url, {}, { preserveScroll: true });
const remove = (url: string): void =>
    router.delete(url, { preserveScroll: true });

/**
 * Marking → approve (marks locked) → publish (students see results).
 */
function ResultsPanel({
    exam,
    summary,
    can,
}: Pick<Props, 'exam' | 'summary' | 'can'>) {
    const { dateTime } = useFormat();
    const steps = [
        {
            key: 'pending',
            title: 'Mark',
            done: exam.results_status !== 'pending',
            text:
                summary.to_mark > 0
                    ? `${summary.to_mark} attempt${summary.to_mark === 1 ? '' : 's'} need written answers marked.`
                    : summary.sat > 0
                      ? 'Everything handed in is marked.'
                      : 'Nobody has handed in yet.',
        },
        {
            key: 'approved',
            title: 'Approve',
            done:
                exam.results_status === 'approved' ||
                exam.results_status === 'published',
            text: exam.results_approved_at
                ? `Approved ${dateTime(exam.results_approved_at)}; marks are locked.`
                : 'Locks the marks once the exam has ended and every attempt is marked.',
        },
        {
            key: 'published',
            title: 'Publish',
            done: exam.results_status === 'published',
            text: exam.results_published_at
                ? `Students and parents can see results since ${dateTime(exam.results_published_at)}.`
                : 'Students and parents are told and can see their marks, answers and feedback.',
        },
    ];

    return (
        <section
            aria-labelledby="results-workflow"
            className="rounded-2xl border bg-card p-5 shadow-sm"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="results-workflow" className="text-lg font-semibold">
                        Results
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Students see nothing of their marks until results are
                        published.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {exam.results_status === 'pending' && can.approve && (
                        <Button
                            disabled={
                                summary.to_mark > 0 ||
                                summary.in_progress > 0 ||
                                exam.state !== 'completed'
                            }
                            title={
                                exam.state !== 'completed'
                                    ? 'Available once the exam has ended'
                                    : summary.to_mark > 0
                                      ? 'Mark every written answer first'
                                      : undefined
                            }
                            size="sm"
                            onClick={() => post(approve.url(exam.id))}
                        >
                            <Lock aria-hidden /> Approve results
                        </Button>
                    )}
                    {exam.results_status === 'approved' && (
                        <>
                            {can.approve && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => remove(reopen.url(exam.id))}
                                >
                                    <LockOpen aria-hidden /> Reopen marking
                                </Button>
                            )}
                            {can.publish_results && (
                                <Button
                                    size="sm"
                                    onClick={() =>
                                        post(publishResults.url(exam.id))
                                    }
                                >
                                    <Eye aria-hidden /> Publish results
                                </Button>
                            )}
                        </>
                    )}
                    {exam.results_status === 'published' &&
                        can.publish_results && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => remove(withdraw.url(exam.id))}
                            >
                                <EyeOff aria-hidden /> Withdraw results
                            </Button>
                        )}
                </div>
            </div>
            <ol className="mt-4 grid gap-3 sm:grid-cols-3">
                {steps.map((step, position) => (
                    <li
                        key={step.key}
                        className={cn(
                            'rounded-xl border p-3',
                            step.done &&
                                'border-emerald-300 bg-emerald-50/60 dark:border-emerald-500/30 dark:bg-emerald-500/10',
                        )}
                    >
                        <p className="flex items-center gap-2 text-sm font-semibold">
                            {step.done ? (
                                <CheckCircle2
                                    className="size-4 text-emerald-600"
                                    aria-label="Done"
                                />
                            ) : (
                                <span className="flex size-4 items-center justify-center rounded-full bg-muted text-[10px]">
                                    {position + 1}
                                </span>
                            )}
                            {step.title}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {step.text}
                        </p>
                    </li>
                ))}
            </ol>
        </section>
    );
}

export default function OnlineExamShow({
    exam,
    questions,
    summary,
    standings,
    questionTypes,
    can,
}: Props) {
    const { dateTime } = useFormat();
    const [editing, setEditing] = useState<number | 'new' | null>(
        questions.length === 0 && can.edit_questions ? 'new' : null,
    );
    const balanced = Math.abs(exam.questions_total - exam.total_marks) < 0.001;

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={exam.title}
                description={`${exam.subject} · ${exam.audience}`}
                actions={
                    <>
                        {exam.status === 'draft' && can.release && (
                            <Button
                                onClick={() => post(publish.url(exam.id))}
                                disabled={questions.length === 0}
                            >
                                <Send aria-hidden /> Publish exam
                            </Button>
                        )}
                        {exam.status === 'published' &&
                            !exam.locked &&
                            can.release && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        remove(unpublish.url(exam.id))
                                    }
                                >
                                    <Undo2 aria-hidden /> Back to draft
                                </Button>
                            )}
                        <Button asChild variant="outline">
                            <Link href={report(exam.id)}>
                                <BarChart3 aria-hidden /> Report
                            </Link>
                        </Button>
                        {can.edit && (
                            <Button asChild variant="outline">
                                <Link href={edit(exam.id)}>
                                    <Pencil aria-hidden /> Settings
                                </Link>
                            </Button>
                        )}
                        {can.delete && (
                            <ConfirmDelete
                                action={destroy(exam.id)}
                                title="Delete this exam?"
                                description="The exam and its questions are removed. This cannot be undone."
                            />
                        )}
                    </>
                }
            />

            <div className="flex flex-wrap gap-2 text-sm">
                <TonePill tone={stateTone[exam.state]}>
                    {exam.state_label}
                </TonePill>
                <TonePill tone={balanced ? 'info' : 'warning'}>
                    {questions.length} questions · {mark(exam.questions_total)}{' '}
                    of {mark(exam.total_marks)} marks
                </TonePill>
                <TonePill tone="neutral">
                    Pass mark {mark(exam.passing_marks)}
                </TonePill>
                <TonePill tone="neutral">
                    {exam.duration_minutes} min ·{' '}
                    {exam.max_attempts === 1
                        ? 'one attempt'
                        : `${exam.max_attempts} attempts`}
                </TonePill>
                <TonePill tone="neutral">
                    {dateTime(exam.starts_at)} – {dateTime(exam.ends_at)}
                </TonePill>
                {exam.negative_marking && (
                    <TonePill tone="warning">Negative marking</TonePill>
                )}
                {(exam.shuffle_questions || exam.shuffle_options) && (
                    <TonePill tone="neutral">Shuffled per student</TonePill>
                )}
            </div>

            {exam.status === 'published' && (
                <ResultsPanel exam={exam} summary={summary} can={can} />
            )}

            <section aria-labelledby="paper-title" className="space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 id="paper-title" className="text-lg font-semibold">
                            Question paper
                        </h2>
                        {!balanced && questions.length > 0 && (
                            <p className="text-sm text-amber-700 dark:text-amber-300">
                                The questions add up to{' '}
                                {mark(exam.questions_total)} marks; the exam is
                                out of {mark(exam.total_marks)}. They must match
                                before you publish.
                            </p>
                        )}
                    </div>
                    {can.edit_questions && editing === null && (
                        <div className="flex gap-2">
                            {can.bank && <BankPicker examId={exam.id} />}
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => setEditing('new')}
                            >
                                <Plus aria-hidden /> Add question
                            </Button>
                        </div>
                    )}
                </div>
                {exam.locked && (
                    <p className="rounded-xl bg-muted p-3 text-sm text-muted-foreground">
                        Students have started this exam, so the paper is locked
                        and every student is marked against the same questions.
                    </p>
                )}

                {questions.map((question, position) =>
                    editing === question.id ? (
                        <QuestionEditor
                            key={question.id}
                            question={question}
                            types={questionTypes}
                            negative={exam.negative_marking}
                            action={{
                                method: 'put',
                                url: updateQuestion.url(question.id),
                            }}
                            onDone={() => setEditing(null)}
                        />
                    ) : (
                        <QuestionCard
                            key={question.id}
                            number={position + 1}
                            type={question.type}
                            typeLabel={question.type_label}
                            prompt={question.prompt}
                            marks={question.marks}
                            negativeMarks={
                                exam.negative_marking
                                    ? question.negative_marks
                                    : 0
                            }
                            options={question.options}
                            answerKey={question.answer_key}
                            meta={
                                question.from_bank ? ' · from the bank' : null
                            }
                            actions={
                                <>
                                    {can.save_to_bank &&
                                        !question.from_bank && (
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                className="size-8"
                                                aria-label="Save to question bank"
                                                title="Save to question bank"
                                                onClick={() =>
                                                    post(
                                                        saveToBank.url(
                                                            question.id,
                                                        ),
                                                    )
                                                }
                                            >
                                                <BookmarkPlus aria-hidden />
                                            </Button>
                                        )}
                                    {can.edit_questions && (
                                        <>
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                className="size-8"
                                                aria-label="Move up"
                                                disabled={position === 0}
                                                onClick={() =>
                                                    router.post(
                                                        moveQuestion.url(
                                                            question.id,
                                                        ),
                                                        { direction: 'up' },
                                                        {
                                                            preserveScroll: true,
                                                        },
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
                                                disabled={
                                                    position ===
                                                    questions.length - 1
                                                }
                                                onClick={() =>
                                                    router.post(
                                                        moveQuestion.url(
                                                            question.id,
                                                        ),
                                                        { direction: 'down' },
                                                        {
                                                            preserveScroll: true,
                                                        },
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
                                                    remove(
                                                        destroyQuestion.url(
                                                            question.id,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Trash2 aria-hidden />
                                            </Button>
                                        </>
                                    )}
                                </>
                            }
                        />
                    ),
                )}

                {editing === 'new' && can.edit_questions && (
                    <QuestionEditor
                        question={null}
                        types={questionTypes}
                        negative={exam.negative_marking}
                        action={{
                            method: 'post',
                            url: storeQuestion.url(exam.id),
                        }}
                        onDone={() => setEditing(null)}
                    />
                )}
                {questions.length === 0 && editing === null && (
                    <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                        No questions yet.
                    </p>
                )}
            </section>

            {exam.status === 'published' && (
                <section aria-labelledby="students-title" className="space-y-3">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2
                            id="students-title"
                            className="text-lg font-semibold"
                        >
                            Students
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {summary.sat} of {summary.eligible} handed in
                            {summary.in_progress > 0 &&
                                ` · ${summary.in_progress} sitting now`}
                            {summary.average !== null &&
                                ` · average ${summary.average}%`}
                        </p>
                    </div>
                    <div className="overflow-x-auto rounded-2xl border bg-card shadow-sm">
                        <table className="w-full text-sm">
                            <caption className="sr-only">
                                Students and their results
                            </caption>
                            <thead className="bg-muted text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th scope="col" className="px-4 py-2">
                                        Rank
                                    </th>
                                    <th scope="col" className="px-4 py-2">
                                        Student
                                    </th>
                                    <th scope="col" className="px-4 py-2">
                                        Status
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-2 text-right"
                                    >
                                        Score
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-2 text-right"
                                    >
                                        %
                                    </th>
                                    <th scope="col" className="px-4 py-2">
                                        Grade
                                    </th>
                                    <th scope="col" className="px-4 py-2">
                                        Handed in
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {standings.map((row) => (
                                    <tr key={row.student_id}>
                                        <td className="px-4 py-2 tabular-nums">
                                            {row.rank ?? '—'}
                                        </td>
                                        <td className="px-4 py-2">
                                            {row.attempt_id &&
                                            row.status !== 'in_progress' ? (
                                                <Link
                                                    href={showAttempt({
                                                        online_exam: exam.id,
                                                        attempt: row.attempt_id,
                                                    })}
                                                    className="font-medium hover:text-primary hover:underline"
                                                >
                                                    {row.name}
                                                </Link>
                                            ) : (
                                                <span className="font-medium">
                                                    {row.name}
                                                </span>
                                            )}
                                            <span className="block text-xs text-muted-foreground">
                                                {row.admission_no} ·{' '}
                                                {row.section}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2">
                                            <TonePill
                                                tone={attemptTone[row.status]}
                                            >
                                                {row.status_label}
                                            </TonePill>
                                            {row.submitted_by === 'timeout' && (
                                                <span className="mt-0.5 block text-xs text-muted-foreground">
                                                    Time ran out
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {row.score !== null
                                                ? `${mark(row.score)} / ${mark(exam.total_marks)}`
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {row.percentage !== null
                                                ? mark(row.percentage)
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-2">
                                            {row.grade ?? '—'}
                                            {row.passed !== null && (
                                                <span
                                                    className={cn(
                                                        'ml-2 text-xs font-medium',
                                                        row.passed
                                                            ? 'text-emerald-700 dark:text-emerald-300'
                                                            : 'text-rose-700 dark:text-rose-300',
                                                    )}
                                                >
                                                    {row.passed
                                                        ? 'Pass'
                                                        : 'Fail'}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-2 whitespace-nowrap text-muted-foreground">
                                            {dateTime(row.submitted_at, '—')}
                                        </td>
                                    </tr>
                                ))}
                                {standings.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-4 py-6 text-center text-muted-foreground"
                                        >
                                            No students are enrolled for this
                                            exam.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}
        </div>
    );
}

OnlineExamShow.layout = {
    breadcrumbs: [{ title: 'Online exams', href: index() }],
};
