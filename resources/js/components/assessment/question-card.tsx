import { CheckCircle2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { QuestionType, QuizOption } from '@/types/learning';

/**
 * A question as the teacher sees it: prompt, marks, options with the correct ones
 * highlighted, and the accepted answer for true/false and short answers.
 */
export default function QuestionCard({
    number,
    type,
    typeLabel,
    prompt,
    marks,
    negativeMarks = 0,
    options,
    answerKey,
    meta,
    actions,
}: {
    number?: number;
    type: QuestionType;
    typeLabel: string;
    prompt: string;
    marks: number;
    negativeMarks?: number;
    options: QuizOption[];
    answerKey: string[];
    meta?: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <article className="rounded-2xl border bg-card p-5 shadow-sm">
            <div className="flex items-start gap-3">
                {number !== undefined && (
                    <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-navy-700 text-xs font-bold text-white">
                        {number}
                    </span>
                )}
                <div className="min-w-0 flex-1">
                    <p className="break-words whitespace-pre-line">{prompt}</p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {typeLabel} · {marks} {marks === 1 ? 'mark' : 'marks'}
                        {negativeMarks > 0 && ` · −${negativeMarks} if wrong`}
                        {meta}
                    </p>
                    {options.length > 0 && (
                        <ul className="mt-3 grid gap-1.5 sm:grid-cols-2">
                            {options.map((option) => {
                                const correct = answerKey.includes(option.id);

                                return (
                                    <li
                                        key={option.id}
                                        className={cn(
                                            'flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm break-words',
                                            correct &&
                                                'border-emerald-300 bg-emerald-50 dark:border-emerald-500/40 dark:bg-emerald-500/10',
                                        )}
                                    >
                                        {correct && (
                                            <CheckCircle2
                                                className="size-4 shrink-0 text-emerald-600"
                                                aria-label="Correct"
                                            />
                                        )}
                                        {option.text}
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                    {type === 'true_false' && answerKey.length > 0 && (
                        <p className="mt-2 text-sm">
                            Answer:{' '}
                            <strong>
                                {answerKey[0] === 'true' ? 'True' : 'False'}
                            </strong>
                        </p>
                    )}
                    {type === 'short_answer' && (
                        <p className="mt-2 text-sm text-muted-foreground">
                            {answerKey.length > 0
                                ? `Accepted: ${answerKey.join(', ')}`
                                : 'Marked by hand'}
                        </p>
                    )}
                </div>
                {actions && (
                    <div className="flex shrink-0 gap-1">{actions}</div>
                )}
            </div>
        </article>
    );
}
