import { router } from '@inertiajs/react';
import { Library, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import QuestionCard from '@/components/assessment/question-card';
import QuestionEditor from '@/components/assessment/question-editor';
import Pagination from '@/components/data/pagination';
import SearchInput from '@/components/data/search-input';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useTableFilters } from '@/hooks/use-table-filters';
import { index as exams } from '@/routes/academics/online-exams';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/academics/question-bank';
import type { QuestionType, QuizOption } from '@/types/learning';

type Option = { id: number; name: string };

type BankQuestion = {
    id: number;
    subject_id: number;
    subject: string;
    school_class_id: number | null;
    class: string | null;
    type: QuestionType;
    type_label: string;
    difficulty: 'easy' | 'medium' | 'hard';
    difficulty_label: string;
    prompt: string;
    options: QuizOption[];
    answer_key: string[];
    marks: number;
    negative_marks: number;
    explanation: string | null;
    topic: string | null;
    author: string | null;
    can_edit: boolean;
};

type Props = {
    questions: {
        data: BankQuestion[];
        meta: React.ComponentProps<typeof Pagination>['meta'];
    };
    filters: {
        search?: string;
        subject_id?: number;
        class_id?: number;
        type?: string;
        difficulty?: string;
    };
    subjects: Option[];
    classes: Option[];
    questionTypes: { value: QuestionType; label: string }[];
    difficulties: { value: string; label: string }[];
    can: { create: boolean };
};

const difficultyTone = {
    easy: 'success',
    medium: 'info',
    hard: 'danger',
} as const;

export default function QuestionBank({
    questions,
    filters,
    subjects,
    classes,
    questionTypes,
    difficulties,
    can,
}: Props) {
    const [editing, setEditing] = useState<number | 'new' | null>(null);
    const { values, update: filter } = useTableFilters(index.url(), {
        search: filters.search ?? '',
        subject_id: filters.subject_id ?? '',
        class_id: filters.class_id ?? '',
        type: filters.type ?? '',
        difficulty: filters.difficulty ?? '',
    });

    const metaFields = (
        meta: Record<string, string>,
        set: (key: string, value: string) => void,
        errors: Partial<Record<string, string>>,
    ) => (
        <>
            <FormField
                id="bank-subject"
                label="Subject"
                required
                error={errors.subject_id}
            >
                <NativeSelect
                    id="bank-subject"
                    value={meta.subject_id}
                    onChange={(event) => set('subject_id', event.target.value)}
                >
                    <option value="">Choose…</option>
                    {subjects.map((subject) => (
                        <option key={subject.id} value={subject.id}>
                            {subject.name}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <FormField
                id="bank-class"
                label="Class"
                error={errors.school_class_id}
                description="Leave on “Any class” to reuse it across classes."
            >
                <NativeSelect
                    id="bank-class"
                    value={meta.school_class_id}
                    onChange={(event) =>
                        set('school_class_id', event.target.value)
                    }
                >
                    <option value="">Any class</option>
                    {classes.map((option) => (
                        <option key={option.id} value={option.id}>
                            {option.name}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <FormField
                id="bank-difficulty"
                label="Difficulty"
                error={errors.difficulty}
            >
                <NativeSelect
                    id="bank-difficulty"
                    value={meta.difficulty}
                    onChange={(event) => set('difficulty', event.target.value)}
                >
                    {difficulties.map((difficulty) => (
                        <option key={difficulty.value} value={difficulty.value}>
                            {difficulty.label}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <FormField id="bank-topic" label="Topic" error={errors.topic}>
                <Input
                    id="bank-topic"
                    value={meta.topic}
                    onChange={(event) => set('topic', event.target.value)}
                    placeholder="e.g. Fractions"
                />
            </FormField>
        </>
    );

    const metaFor = (
        question: BankQuestion | null,
    ): Record<string, string> => ({
        subject_id: String(
            question?.subject_id ??
                (values.subject_id || subjects[0]?.id || ''),
        ),
        school_class_id: String(
            question?.school_class_id ?? (values.class_id || ''),
        ),
        difficulty: question?.difficulty ?? 'medium',
        topic: question?.topic ?? '',
    });

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Question bank"
                description="Reusable questions for your subjects. Add them to any exam; each exam keeps its own copy."
                actions={
                    can.create &&
                    editing === null && (
                        <Button onClick={() => setEditing('new')}>
                            <Plus aria-hidden /> New question
                        </Button>
                    )
                }
            />

            <div className="flex flex-col gap-2 lg:flex-row">
                <SearchInput
                    value={values.search}
                    onChange={(search) =>
                        filter({ search }, { debounce: true })
                    }
                    placeholder="Question text or topic"
                />
                <NativeSelect
                    aria-label="Subject"
                    value={values.subject_id}
                    onChange={(event) =>
                        filter({ subject_id: event.target.value })
                    }
                    className="lg:w-44"
                >
                    <option value="">All subjects</option>
                    {subjects.map((subject) => (
                        <option key={subject.id} value={subject.id}>
                            {subject.name}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect
                    aria-label="Class"
                    value={values.class_id}
                    onChange={(event) =>
                        filter({ class_id: event.target.value })
                    }
                    className="lg:w-40"
                >
                    <option value="">All classes</option>
                    {classes.map((option) => (
                        <option key={option.id} value={option.id}>
                            {option.name}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect
                    aria-label="Question type"
                    value={values.type}
                    onChange={(event) => filter({ type: event.target.value })}
                    className="lg:w-44"
                >
                    <option value="">All types</option>
                    {questionTypes.map((type) => (
                        <option key={type.value} value={type.value}>
                            {type.label}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect
                    aria-label="Difficulty"
                    value={values.difficulty}
                    onChange={(event) =>
                        filter({ difficulty: event.target.value })
                    }
                    className="lg:w-36"
                >
                    <option value="">Any difficulty</option>
                    {difficulties.map((difficulty) => (
                        <option key={difficulty.value} value={difficulty.value}>
                            {difficulty.label}
                        </option>
                    ))}
                </NativeSelect>
            </div>

            {editing === 'new' && (
                <QuestionEditor
                    question={null}
                    types={questionTypes}
                    negative
                    meta={metaFor(null)}
                    renderMeta={metaFields}
                    action={{ method: 'post', url: store.url() }}
                    onDone={() => setEditing(null)}
                />
            )}

            {questions.data.length === 0 && editing === null ? (
                <div className="rounded-2xl border border-dashed p-10 text-center">
                    <Library
                        className="mx-auto size-8 text-muted-foreground"
                        aria-hidden
                    />
                    <p className="mt-3 font-medium">No questions in the bank</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Add questions here, or use the bookmark button on any
                        question in an{' '}
                        <a
                            href={exams.url()}
                            className="underline hover:text-primary"
                        >
                            online exam
                        </a>{' '}
                        to save it for reuse.
                    </p>
                </div>
            ) : (
                <div className="space-y-3">
                    {questions.data.map((question) =>
                        editing === question.id ? (
                            <QuestionEditor
                                key={question.id}
                                question={question}
                                types={questionTypes}
                                negative
                                meta={metaFor(question)}
                                renderMeta={metaFields}
                                action={{
                                    method: 'put',
                                    url: update.url(question.id),
                                }}
                                onDone={() => setEditing(null)}
                            />
                        ) : (
                            <QuestionCard
                                key={question.id}
                                type={question.type}
                                typeLabel={question.type_label}
                                prompt={question.prompt}
                                marks={question.marks}
                                negativeMarks={question.negative_marks}
                                options={question.options}
                                answerKey={question.answer_key}
                                meta={
                                    <>
                                        {' '}
                                        · {question.subject}
                                        {question.class
                                            ? ` · ${question.class}`
                                            : ''}
                                        {question.topic
                                            ? ` · ${question.topic}`
                                            : ''}
                                        {question.author
                                            ? ` · by ${question.author}`
                                            : ''}{' '}
                                        <TonePill
                                            tone={
                                                difficultyTone[
                                                    question.difficulty
                                                ]
                                            }
                                        >
                                            {question.difficulty_label}
                                        </TonePill>
                                    </>
                                }
                                actions={
                                    question.can_edit && (
                                        <>
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
                                                        destroy.url(
                                                            question.id,
                                                        ),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <Trash2 aria-hidden />
                                            </Button>
                                        </>
                                    )
                                }
                            />
                        ),
                    )}
                </div>
            )}
            <Pagination meta={questions.meta} />
        </div>
    );
}

QuestionBank.layout = {
    breadcrumbs: [
        { title: 'Online exams', href: exams() },
        { title: 'Question bank', href: index() },
    ],
};
