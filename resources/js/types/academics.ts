import type { RowAbilities } from './models';

export type Term = {
    id: number | null;
    name: string;
    starts_on: string;
    ends_on: string;
};

export type AcademicYear = {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string;
    is_current: boolean;
    terms?: Term[];
    can: RowAbilities;
};

export type SubjectType = 'core' | 'elective' | 'extracurricular';

export type Subject = {
    id: number;
    name: string;
    code: string;
    type: SubjectType;
    type_label: string;
    color: string | null;
    description: string | null;
    is_active: boolean;
    classes_count?: number;
    can: RowAbilities;
};

export type SubjectOption = Pick<Subject, 'id' | 'name' | 'code' | 'color'>;

export type SectionData = {
    id: number | null;
    name: string;
    capacity: number | null;
    room: string | null;
    class_teacher_id: number | null;
    class_teacher?: { id: number; name: string } | null;
};

export type SchoolClass = {
    id: number;
    name: string;
    level: number;
    description: string | null;
    sections?: SectionData[];
    subjects?: (SubjectOption & { periods_per_week: number })[];
    can: RowAbilities;
};

export type PeriodData = {
    id: number | null;
    name: string;
    starts_at: string;
    ends_at: string;
    is_break: boolean;
};

export type TimetableLesson = {
    id: number;
    day_of_week: number;
    period_id: number;
    room: string | null;
    subject: SubjectOption;
    teacher: { id: number; name: string } | null;
    section: { id: number; name: string };
};

export type SubjectAllocation = SubjectOption & {
    planned: number;
    scheduled: number;
};

export type GradeBandData = {
    grade: string;
    min_percentage: number;
    grade_point: number | null;
    remark: string | null;
    is_passing: boolean;
};

export type ExamSummary = {
    id: number;
    name: string;
    academic_year_id: number;
    academic_year: string | null;
    term_id: number | null;
    term: string | null;
    grade_scale_id: number;
    grade_scale: string | null;
    starts_on: string;
    ends_on: string;
    published_at: string | null;
    papers_count?: number;
};

export type ResultPaper = {
    id: number;
    subject: string;
    code: string;
    max_marks: number;
    pass_marks: number;
};

export type ExamResultRow = {
    student: {
        id: number;
        name: string;
        admission_no: string;
        roll_number: number | null;
    };
    subjects: Record<
        number,
        {
            marks: number | null;
            is_absent: boolean;
            grade: string | null;
            passed: boolean;
            remarks: string | null;
        }
    >;
    total: number;
    max_total: number;
    percentage: number | null;
    grade: string | null;
    grade_point: number | null;
    remark: string | null;
    complete: boolean;
    passed: boolean | null;
    rank: number | null;
};

export type AssignmentSummary = {
    id: number;
    title: string;
    description: string;
    type: string;
    type_label: string;
    due_on: string;
    max_marks: number | null;
    section_id: number;
    section: string | null;
    subject_id: number;
    subject: { id: number; name: string; color: string | null } | null;
    author: string | null;
};
