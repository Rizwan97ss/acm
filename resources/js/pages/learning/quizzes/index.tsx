import { Link } from '@inertiajs/react';
import { ClipboardCheck, Plus } from 'lucide-react';
import Pagination from '@/components/data/pagination';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/hooks/use-format';
import { create, index, show } from '@/routes/learning/quizzes';
import type { PaginationMeta } from '@/types/learning';

type QuizRow = {
    id: number;
    title: string;
    type_label: string;
    status: 'draft' | 'published';
    subject: string;
    audience: string;
    closes_at: string | null;
    questions_count: number;
    attempts_count: number;
    to_mark_count: number;
};

export default function QuizzesIndex({
    quizzes,
}: {
    quizzes: { data: QuizRow[]; meta: PaginationMeta };
}) {
    const { dateTime } = useFormat();

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Quizzes & tests"
                description="Objective questions are marked automatically; written answers wait for you."
                actions={
                    <Button asChild>
                        <Link href={create()}>
                            <Plus aria-hidden /> New quiz
                        </Link>
                    </Button>
                }
            />
            {quizzes.data.length === 0 ? (
                <div className="rounded-2xl border border-dashed p-10 text-center">
                    <ClipboardCheck
                        className="mx-auto size-8 text-muted-foreground"
                        aria-hidden
                    />
                    <p className="mt-3 font-medium">No quizzes yet</p>
                </div>
            ) : (
                <div className="overflow-x-auto rounded-2xl border bg-card shadow-sm">
                    <table className="w-full text-sm">
                        <caption className="sr-only">Quizzes and tests</caption>
                        <thead className="bg-gradient-to-r from-navy-50 via-royal-50 to-gold-50 text-left text-xs text-navy-950/70 uppercase dark:from-navy-500/10 dark:via-royal-500/10 dark:to-gold-500/10 dark:text-navy-100/80">
                            <tr>
                                <th scope="col" className="px-4 py-3">
                                    Quiz
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    For
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Closes
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 text-right"
                                >
                                    Questions
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 text-right"
                                >
                                    Attempts
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {quizzes.data.map((quiz) => (
                                <tr key={quiz.id} className="hover:bg-muted/40">
                                    <td className="px-4 py-3">
                                        <Link
                                            href={show(quiz.id)}
                                            className="font-medium hover:text-primary hover:underline"
                                        >
                                            {quiz.title}
                                        </Link>
                                        <p className="text-xs text-muted-foreground">
                                            {quiz.type_label} · {quiz.subject}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {quiz.audience}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                        {dateTime(
                                            quiz.closes_at,
                                            'No deadline',
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {quiz.questions_count}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {quiz.attempts_count}
                                        {quiz.to_mark_count > 0 && (
                                            <span className="ml-2">
                                                <TonePill tone="warning">
                                                    {quiz.to_mark_count} to mark
                                                </TonePill>
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <TonePill
                                            tone={
                                                quiz.status === 'published'
                                                    ? 'success'
                                                    : 'neutral'
                                            }
                                        >
                                            {quiz.status === 'published'
                                                ? 'Published'
                                                : 'Draft'}
                                        </TonePill>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            <Pagination meta={quizzes.meta} />
        </div>
    );
}

QuizzesIndex.layout = {
    breadcrumbs: [{ title: 'Quizzes & tests', href: index() }],
};
