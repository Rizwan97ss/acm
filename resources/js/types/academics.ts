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
