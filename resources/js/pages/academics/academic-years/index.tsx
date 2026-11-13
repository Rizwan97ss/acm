import { Link, router } from '@inertiajs/react';
import { CalendarCheck, Pencil, Plus } from 'lucide-react';
import ConfirmDelete from '@/components/confirm-delete';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useFormat } from '@/hooks/use-format';
import {
    create,
    current,
    destroy,
    edit,
    index,
} from '@/routes/academics/academic-years';
import type { AcademicYear } from '@/types';

type Props = {
    years: AcademicYear[];
    can: { create: boolean };
};

export default function AcademicYearsIndex({ years, can }: Props) {
    const format = useFormat();
    const range = (from: string, to: string): string =>
        `${format.date(`${from}T12:00:00Z`)} – ${format.date(`${to}T12:00:00Z`)}`;

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Academic years"
                description="The current year drives timetables, attendance and results."
                actions={
                    can.create && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus aria-hidden /> New year
                            </Link>
                        </Button>
                    )
                }
            />

            {years.length === 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>No academic years yet</CardTitle>
                        <CardDescription>
                            Create the first year; it becomes the current year
                            automatically.
                        </CardDescription>
                    </CardHeader>
                </Card>
            )}

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {years.map((year) => (
                    <Card
                        key={year.id}
                        className={
                            year.is_current ? 'border-primary/60' : undefined
                        }
                    >
                        <CardHeader className="flex flex-row items-start justify-between gap-2">
                            <div className="space-y-1.5">
                                <CardTitle className="flex items-center gap-2">
                                    {year.name}
                                    {year.is_current && <Badge>Current</Badge>}
                                </CardTitle>
                                <CardDescription>
                                    {range(year.starts_on, year.ends_on)}
                                </CardDescription>
                            </div>
                            <div className="flex">
                                {year.can.update && (
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link
                                            href={edit(year.id)}
                                            aria-label={`Edit ${year.name}`}
                                        >
                                            <Pencil aria-hidden />
                                        </Link>
                                    </Button>
                                )}
                                {year.can.delete && !year.is_current && (
                                    <ConfirmDelete
                                        action={destroy(year.id)}
                                        title={`Delete ${year.name}?`}
                                        description="Years that already have a timetable cannot be deleted."
                                    />
                                )}
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <ul className="space-y-1 text-sm">
                                {(year.terms ?? []).map((term) => (
                                    <li
                                        key={term.id}
                                        className="flex justify-between gap-2"
                                    >
                                        <span>{term.name}</span>
                                        <span className="text-muted-foreground">
                                            {range(
                                                term.starts_on,
                                                term.ends_on,
                                            )}
                                        </span>
                                    </li>
                                ))}
                                {year.terms?.length === 0 && (
                                    <li className="text-muted-foreground">
                                        No terms defined.
                                    </li>
                                )}
                            </ul>
                            {!year.is_current && year.can.update && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.visit(current(year.id), {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    <CalendarCheck aria-hidden /> Make current
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </div>
    );
}

AcademicYearsIndex.layout = {
    breadcrumbs: [{ title: 'Academic years', href: index() }],
};
