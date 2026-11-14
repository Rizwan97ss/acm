import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useMoney } from '@/hooks/use-money';
import { cn } from '@/lib/utils';
import { index, store } from '@/routes/finance/invoices';
import type { Option } from '@/types';

type Props = {
    year: { id: number; name: string } | null;
    classes: Option[];
    feeItems: {
        id: number;
        name: string;
        amount: number;
        frequency_label: string;
        school_class: string | null;
    }[];
};

const toggle = (list: number[], id: number, checked: boolean): number[] =>
    checked ? [...list, id] : list.filter((item) => item !== id);

export default function GenerateInvoices({ year, classes, feeItems }: Props) {
    const money = useMoney();
    const today = new Date().toISOString().slice(0, 10);
    const form = useForm({
        title: '',
        issued_on: today,
        due_on: '',
        fee_item_ids: [] as number[],
        class_ids: classes.map((item) => item.id),
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.post(store.url());
    };

    if (!year) {
        return (
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Generate invoices" />
                <p className="text-sm text-muted-foreground">
                    Set a current academic year first.
                </p>
            </div>
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Generate invoices"
                description={`Bill every enrolled student in the chosen classes · ${year.name}. Concessions are applied automatically, and students already billed under the same title are skipped.`}
            />
            <form onSubmit={submit} className="max-w-4xl space-y-6">
                <Card>
                    <CardContent className="grid gap-5 sm:grid-cols-3">
                        <FormField
                            id="title"
                            label="Title"
                            error={form.errors.title}
                            required
                            className="sm:col-span-3"
                        >
                            <Input
                                id="title"
                                value={form.data.title}
                                onChange={(e) =>
                                    form.setData('title', e.target.value)
                                }
                                placeholder="September 2026 fees"
                                required
                            />
                        </FormField>
                        <FormField
                            id="issued_on"
                            label="Issue date"
                            error={form.errors.issued_on}
                            required
                        >
                            <Input
                                id="issued_on"
                                type="date"
                                value={form.data.issued_on}
                                onChange={(e) =>
                                    form.setData('issued_on', e.target.value)
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            id="due_on"
                            label="Due date"
                            error={form.errors.due_on}
                            required
                        >
                            <Input
                                id="due_on"
                                type="date"
                                value={form.data.due_on}
                                onChange={(e) =>
                                    form.setData('due_on', e.target.value)
                                }
                                required
                            />
                        </FormField>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Fees to charge</CardTitle>
                        <CardDescription>
                            Class-specific fees are only charged to that class.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2 sm:grid-cols-2">
                        {feeItems.map((item) => {
                            const checked = form.data.fee_item_ids.includes(
                                item.id,
                            );

                            return (
                                <label
                                    key={item.id}
                                    className={cn(
                                        'flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition',
                                        checked &&
                                            'border-emerald-500 bg-emerald-50 dark:bg-emerald-500/10',
                                    )}
                                >
                                    <Checkbox
                                        checked={checked}
                                        onCheckedChange={(value) =>
                                            form.setData(
                                                'fee_item_ids',
                                                toggle(
                                                    form.data.fee_item_ids,
                                                    item.id,
                                                    value === true,
                                                ),
                                            )
                                        }
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-medium">
                                            {item.name}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {item.school_class ?? 'Every class'}{' '}
                                            · {item.frequency_label}
                                        </span>
                                    </span>
                                    <span className="font-semibold tabular-nums">
                                        {money.format(item.amount)}
                                    </span>
                                </label>
                            );
                        })}
                        {feeItems.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No active fees this year. Add them under Fee
                                structure.
                            </p>
                        )}
                        <InputError
                            message={form.errors.fee_item_ids}
                            className="sm:col-span-2"
                        />
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Classes</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        {classes.map((schoolClass) => {
                            const checked = form.data.class_ids.includes(
                                schoolClass.id,
                            );

                            return (
                                <label
                                    key={schoolClass.id}
                                    className={cn(
                                        'flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-sm',
                                        checked &&
                                            'border-indigo-500 bg-indigo-50 dark:bg-indigo-500/10',
                                    )}
                                >
                                    <Checkbox
                                        checked={checked}
                                        onCheckedChange={(value) =>
                                            form.setData(
                                                'class_ids',
                                                toggle(
                                                    form.data.class_ids,
                                                    schoolClass.id,
                                                    value === true,
                                                ),
                                            )
                                        }
                                    />
                                    {schoolClass.name}
                                </label>
                            );
                        })}
                        <InputError
                            message={form.errors.class_ids}
                            className="w-full"
                        />
                    </CardContent>
                </Card>
                <div className="flex gap-2">
                    <Button
                        type="submit"
                        disabled={form.processing}
                        className="bg-gradient-to-r from-emerald-600 to-teal-600 text-white"
                    >
                        Generate invoices
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href={index()}>Cancel</Link>
                    </Button>
                </div>
            </form>
        </div>
    );
}

GenerateInvoices.layout = {
    breadcrumbs: [{ title: 'Invoices', href: index() }],
};
