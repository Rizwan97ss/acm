export type InvoiceSummary = {
    id: number;
    number: string;
    title: string;
    student: { id: number; name: string; admission_no: string };
    issued_on: string;
    due_on: string;
    total: number;
    paid: number;
    balance: number;
    status: string;
    status_label: string;
    overdue: boolean;
};

export type MoneyLine = {
    name: string;
    amount: number;
    unpaid_leave?: boolean;
};

export type LeaveRow = {
    id: number;
    type: string;
    starts_on: string;
    ends_on: string;
    days: number;
    reason: string;
    status: string;
    status_label: string;
    review_note: string | null;
    reviewer: string | null;
    cancellable: boolean;
};
