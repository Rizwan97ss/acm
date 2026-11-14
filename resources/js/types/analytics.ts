export type Insight<T> = { available: boolean; data: T | null };

export type AttendanceTrends = {
    daily: {
        date: string;
        rate: number | null;
        moving_average: number | null;
    }[];
    weekdays: { weekday: string; rate: number | null }[];
    summary: {
        overall_rate: number | null;
        weekly_trend: number | null;
        trend_direction: 'improving' | 'declining' | 'steady' | null;
        best_day: { date: string; rate: number } | null;
        worst_day: { date: string; rate: number } | null;
        school_days: number;
    } | null;
};

export type AtRiskStudent = {
    student_id: number;
    name: string;
    admission_no: string;
    section: string | null;
    rate: number | null;
    z_score: number | null;
    risk_score: number;
    reasons: string[];
};

export type AtRisk = {
    students: AtRiskStudent[];
    summary: { total: number; at_risk: number; mean_rate: number | null };
};

export type Mover = {
    student_id: number;
    name: string;
    admission_no: string;
    change: number;
};

export type Performance = {
    subjects: {
        subject: string;
        mean: number;
        median: number;
        std: number;
        pass_rate: number;
        students: number;
    }[];
    distribution: { band: string; students: number }[];
    correlation: number | null;
    movers: { improved: Mover[]; declined: Mover[] };
    exams: { exam: string; mean: number }[];
};

export type ForecastPoint = {
    month: string;
    value: number;
    low: number;
    high: number;
};

export type FinanceInsight = {
    history: {
        month: string;
        billed: number;
        collected: number;
        expenses: number;
        collection_rate: number | null;
    }[];
    forecast: { collected: ForecastPoint[]; expenses: ForecastPoint[] };
    aging: { bucket: string; amount: number; invoices: number }[];
};

export type ExportableReport = {
    key: string;
    label: string;
    description: string;
    dated: boolean;
};
