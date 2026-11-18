export type SubjectBadge = { name: string; color: string | null };

export type TeachingOption = {
    id: number;
    name: string;
    sections: { id: number; name: string }[];
    subjects: { id: number; name: string }[];
};

export type PublishStatus = 'draft' | 'published';

export type CourseSummary = {
    id: number;
    title: string;
    description: string | null;
    status: PublishStatus;
    publish_at: string | null;
    school_class_id: number;
    section_id: number | null;
    subject_id: number;
    subject: SubjectBadge;
    audience: string;
    author: string | null;
    updated_at: string | null;
};

export type Progress = { completed: number; total: number; percent: number };

export type MaterialKind =
    | 'pdf'
    | 'document'
    | 'presentation'
    | 'spreadsheet'
    | 'audio'
    | 'video'
    | 'image'
    | 'link';

export type PaginationMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

export type QuestionType =
    | 'single_choice'
    | 'multiple_choice'
    | 'true_false'
    | 'short_answer'
    | 'essay';

export type QuizOption = { id: string; text: string };
