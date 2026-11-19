import type { RowAbilities } from './models';

export type EnumOption = { value: string; label: string };

export type SectionOption = {
    id: number;
    name: string;
    school_class_id: number;
    room: string | null;
    capacity: number | null;
};

export type YearOption = { id: number; name: string; is_current: boolean };

export type Student = {
    id: number;
    photo_url?: string | null;
    admission_no: string;
    first_name: string;
    last_name: string;
    full_name: string;
    gender: string;
    gender_label: string;
    date_of_birth: string;
    email: string | null;
    blood_group: string | null;
    national_id: string | null;
    medical_notes: string | null;
    has_medical_notes: boolean;
    address: string | null;
    admission_date: string;
    status: string;
    status_label: string;
    has_portal_account: boolean;
    current_enrollment?: {
        id: number;
        section_id: number;
        section: string | null;
        roll_number: number | null;
    } | null;
    can: RowAbilities & { invite: boolean };
};

export type Guardian = {
    id: number;
    photo_url?: string | null;
    first_name: string;
    last_name: string;
    full_name: string;
    email: string | null;
    phone: string | null;
    occupation: string | null;
    address: string | null;
    has_portal_account: boolean;
    students_count?: number;
    students?: {
        id: number;
        full_name: string;
        admission_no: string;
        relationship: string | null;
    }[];
    link: {
        relationship: string;
        is_primary: boolean;
        is_emergency_contact: boolean;
    } | null;
    can: RowAbilities & { invite: boolean };
};

export type Employee = {
    id: number;
    photo_url?: string | null;
    employee_no: string;
    user_id: number | null;
    user?: { id: number; name: string; email: string } | null;
    first_name: string;
    last_name: string;
    full_name: string;
    gender: string | null;
    date_of_birth: string | null;
    email: string | null;
    phone: string | null;
    national_id: string | null;
    bank_account: string | null;
    address: string | null;
    is_teaching: boolean;
    designation: string;
    department: string | null;
    qualification: string | null;
    employment_type: string;
    employment_type_label: string;
    joined_on: string;
    left_on: string | null;
    status: string;
    status_label: string;
    can: RowAbilities;
};

export type AdmissionApplication = {
    id: number;
    application_no: string;
    academic_year_id: number;
    school_class_id: number;
    academic_year?: string;
    school_class?: string;
    first_name: string;
    last_name: string;
    full_name: string;
    gender: string;
    gender_label: string;
    date_of_birth: string;
    previous_school: string | null;
    guardian_name: string;
    guardian_relationship: string;
    guardian_relationship_label: string;
    guardian_phone: string | null;
    guardian_email: string | null;
    notes: string | null;
    status: 'pending' | 'approved' | 'rejected' | 'enrolled';
    status_label: string;
    reviewer?: { id: number; name: string } | null;
    reviewed_at: string | null;
    decision_notes: string | null;
    student_id: number | null;
    created_at: string | null;
    can: { update: boolean; decide: boolean; enroll: boolean };
};
