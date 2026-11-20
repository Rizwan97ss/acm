import type { StatusTone } from '@/components/status-badge';
import type { QuestionType, QuizOption } from '@/types/learning';

export type ExamState =
    | 'draft'
    | 'scheduled'
    | 'active'
    | 'completed'
    | 'results_published';

export type ResultsStatus = 'pending' | 'approved' | 'published';

export type AttemptStatus = 'in_progress' | 'submitted' | 'graded';

export type Answer = string | string[];

export type OnlineExam = {
    id: number;
    title: string;
    instructions: string | null;
    school_class_id: number;
    section_id: number | null;
    subject_id: number;
    subject: string;
    subject_color: string | null;
    audience: string;
    starts_at: string;
    ends_at: string;
    duration_minutes: number;
    total_marks: number;
    passing_marks: number;
    max_attempts: number;
    shuffle_questions: boolean;
    shuffle_options: boolean;
    negative_marking: boolean;
    show_rank: boolean;
    status: 'draft' | 'published';
    state: ExamState;
    state_label: string;
    results_status: ResultsStatus;
    results_status_label: string;
    results_published_at: string | null;
};

export type ExamQuestion = {
    id: number;
    type: QuestionType;
    type_label: string;
    prompt: string;
    marks: number;
    negative_marks: number;
    explanation: string | null;
    options: QuizOption[];
    answer_key: string[];
    from_bank: boolean;
};

export type Standing = {
    student_id: number;
    name: string;
    admission_no: string;
    section: string;
    attempt_id: number | null;
    attempt_no: number | null;
    status: AttemptStatus | 'absent';
    status_label: string;
    score: number | null;
    percentage: number | null;
    grade: string | null;
    passed: boolean | null;
    rank: number | null;
    submitted_at: string | null;
    submitted_by: string | null;
};

export type ExamSummary = {
    eligible: number;
    sat: number;
    in_progress: number;
    absent: number;
    to_mark: number;
    graded: number;
    average: number | null;
    median: number | null;
    highest: number | null;
    lowest: number | null;
    passed: number;
    pass_rate: number | null;
};

export const stateTone: Record<ExamState, StatusTone> = {
    draft: 'neutral',
    scheduled: 'info',
    active: 'warning',
    completed: 'neutral',
    results_published: 'success',
};

export const attemptTone: Record<AttemptStatus | 'absent', StatusTone> = {
    in_progress: 'info',
    submitted: 'warning',
    graded: 'success',
    absent: 'neutral',
};

/** Formats a mark without trailing zeros: 7, 7.5, 7.25. */
export function mark(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return String(Math.round(value * 100) / 100);
}
