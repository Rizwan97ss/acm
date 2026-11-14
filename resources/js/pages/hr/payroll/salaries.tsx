import { useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useMoney } from '@/hooks/use-money';
import { index, salaries } from '@/routes/hr/payroll';
import { update } from '@/routes/hr/payroll/salaries';
import type { MoneyLine } from '@/types';

type EmployeeRow = {
    id: number;
    name: string;
    employee_no: string;
    designation: string;
    profile: {
        basic: number;
        allowances: MoneyLine[];
        deductions: MoneyLine[];
    } | null;
};

type Line = { name: string; amount: string };

function LinesEditor({
    label,
    lines,
    onChange,
}: {
    label: string;
    lines: Line[];
    onChange: (lines: Line[]) => void;
}) {
    return (
        <fieldset className="space-y-2">
            <legend className="text-sm font-medium">{label}</legend>
            {lines.map((line, i) => (
                <div key={i} className="grid grid-cols-[1fr_8rem_auto] gap-2">
                    <Input
                        aria-label={`${label} name`}
                        value={line.name}
                        placeholder="Name"
                        onChange={(e) =>
                            onChange(
                                lines.map((item, index) =>
                                    index === i
                                        ? { ...item, name: e.target.value }
                                        : item,
                                ),
                            )
                        }
                        required
                    />
                    <Input
                        aria-label={`${label} amount`}
                        type="number"
                        min={0}
                        step="0.01"
                        value={line.amount}
                        onChange={(e) =>
                            onChange(
                                lines.map((item, index) =>
                                    index === i
                                        ? { ...item, amount: e.target.value }
                                        : item,
                                ),
                            )
                        }
                        required
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Remove"
                        onClick={() =>
                            onChange(lines.filter((_, index) => index !== i))
                        }
                    >
                        <Trash2 aria-hidden />
                    </Button>
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => onChange([...lines, { name: '', amount: '' }])}
            >
                <Plus aria-hidden /> Add
            </Button>
        </fieldset>
    );
}

function SalaryDialog({
    employee,
    onClose,
}: {
    employee: EmployeeRow;
    onClose: () => void;
}) {
    const money = useMoney();
    const toLines = (lines: MoneyLine[] | undefined): Line[] =>
        (lines ?? []).map((line) => ({
            name: line.name,
            amount: money.toMajor(line.amount),
        }));
    const form = useForm({
        basic: employee.profile ? money.toMajor(employee.profile.basic) : '',
        allowances: toLines(employee.profile?.allowances),
        deductions: toLines(employee.profile?.deductions),
    });
    const sum = (lines: Line[]): number =>
        lines.reduce((total, line) => total + Number(line.amount || 0), 0);
    const gross = Number(form.data.basic || 0) + sum(form.data.allowances);
    const net = gross - sum(form.data.deductions);

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.put(update.url(employee.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogTitle>Salary · {employee.name}</DialogTitle>
                <DialogDescription>
                    Monthly amounts in {money.currency}. Stored encrypted.
                </DialogDescription>
                <form onSubmit={submit} className="space-y-5">
                    <FormField
                        id="basic"
                        label="Basic salary"
                        error={form.errors.basic}
                        required
                    >
                        <Input
                            id="basic"
                            type="number"
                            min={0}
                            step="0.01"
                            value={form.data.basic}
                            onChange={(e) =>
                                form.setData('basic', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <LinesEditor
                        label="Allowances"
                        lines={form.data.allowances}
                        onChange={(lines) => form.setData('allowances', lines)}
                    />
                    <LinesEditor
                        label="Deductions"
                        lines={form.data.deductions}
                        onChange={(lines) => form.setData('deductions', lines)}
                    />
                    <p className="rounded-xl bg-muted px-4 py-3 text-sm">
                        Gross {gross.toFixed(2)} · Net{' '}
                        <span className="font-semibold">{net.toFixed(2)}</span>{' '}
                        {money.currency}
                    </p>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            Save salary
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Salaries({ employees }: { employees: EmployeeRow[] }) {
    const money = useMoney();
    const [editing, setEditing] = useState<EmployeeRow | null>(null);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Salaries"
                description="Monthly salary profiles used by payroll. Only active staff are listed."
            />
            <ul className="divide-y overflow-hidden rounded-2xl border bg-card shadow-sm">
                {employees.map((employee) => {
                    const gross = employee.profile
                        ? employee.profile.basic +
                          employee.profile.allowances.reduce(
                              (sum, line) => sum + line.amount,
                              0,
                          )
                        : null;

                    return (
                        <li
                            key={employee.id}
                            className="flex items-center gap-3 px-4 py-3"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="font-medium">{employee.name}</p>
                                <p className="text-xs text-muted-foreground">
                                    {employee.employee_no} ·{' '}
                                    {employee.designation}
                                </p>
                            </div>
                            {gross === null ? (
                                <TonePill tone="warning">Not set</TonePill>
                            ) : (
                                <span className="font-semibold tabular-nums">
                                    {money.format(gross)}
                                </span>
                            )}
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={`Edit salary for ${employee.name}`}
                                onClick={() => setEditing(employee)}
                            >
                                <Pencil aria-hidden />
                            </Button>
                        </li>
                    );
                })}
                {employees.length === 0 && (
                    <li className="p-8 text-center text-sm text-muted-foreground">
                        No active staff.
                    </li>
                )}
            </ul>
            {editing && (
                <SalaryDialog
                    key={editing.id}
                    employee={editing}
                    onClose={() => setEditing(null)}
                />
            )}
        </div>
    );
}

Salaries.layout = {
    breadcrumbs: [
        { title: 'Payroll', href: index() },
        { title: 'Salaries', href: salaries() },
    ],
};
