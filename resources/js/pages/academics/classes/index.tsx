import { Link } from '@inertiajs/react';
import { Pencil, Plus, Users } from 'lucide-react';
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
import { create, destroy, edit, index } from '@/routes/academics/classes';
import type { SchoolClass } from '@/types';

type Props = {
    classes: SchoolClass[];
    can: { create: boolean };
};

export default function ClassesIndex({ classes, can }: Props) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Classes & sections"
                description="Grade levels, their sections and the subjects each class studies."
                actions={
                    can.create && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus aria-hidden /> New class
                            </Link>
                        </Button>
                    )
                }
            />

            {classes.length === 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>No classes yet</CardTitle>
                        <CardDescription>
                            Create subjects first, then add classes with their
                            sections.
                        </CardDescription>
                    </CardHeader>
                </Card>
            )}

            <div className="grid gap-4 lg:grid-cols-2">
                {classes.map((schoolClass) => {
                    const capacity = (schoolClass.sections ?? []).reduce(
                        (sum, section) => sum + (section.capacity ?? 0),
                        0,
                    );
                    const periods = (schoolClass.subjects ?? []).reduce(
                        (sum, subject) => sum + subject.periods_per_week,
                        0,
                    );

                    return (
                        <Card key={schoolClass.id}>
                            <CardHeader className="flex flex-row items-start justify-between gap-2">
                                <div className="space-y-1.5">
                                    <CardTitle>{schoolClass.name}</CardTitle>
                                    <CardDescription>
                                        {schoolClass.sections?.length ?? 0}{' '}
                                        sections · {capacity} seats · {periods}{' '}
                                        periods/week
                                    </CardDescription>
                                </div>
                                <div className="flex">
                                    {schoolClass.can.update && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={edit(schoolClass.id)}
                                                aria-label={`Edit ${schoolClass.name}`}
                                            >
                                                <Pencil aria-hidden />
                                            </Link>
                                        </Button>
                                    )}
                                    {schoolClass.can.delete && (
                                        <ConfirmDelete
                                            action={destroy(schoolClass.id)}
                                            title={`Delete ${schoolClass.name}?`}
                                            description="All its sections are removed too. Classes used in a timetable cannot be deleted."
                                        />
                                    )}
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <ul className="divide-y rounded-lg border text-sm">
                                    {(schoolClass.sections ?? []).map(
                                        (section) => (
                                            <li
                                                key={section.id}
                                                className="flex items-center justify-between gap-3 px-3 py-2"
                                            >
                                                <span className="font-medium">
                                                    Section {section.name}
                                                </span>
                                                <span className="flex min-w-0 items-center gap-3 text-muted-foreground">
                                                    {section.room && (
                                                        <span>
                                                            {section.room}
                                                        </span>
                                                    )}
                                                    <span className="flex items-center gap-1">
                                                        <Users
                                                            className="size-3.5"
                                                            aria-hidden
                                                        />
                                                        {section.capacity ??
                                                            '—'}
                                                    </span>
                                                    <span className="truncate">
                                                        {section.class_teacher
                                                            ?.name ??
                                                            'No class teacher'}
                                                    </span>
                                                </span>
                                            </li>
                                        ),
                                    )}
                                </ul>
                                <div className="flex flex-wrap gap-1.5">
                                    {(schoolClass.subjects ?? []).map(
                                        (subject) => (
                                            <Badge
                                                key={subject.id}
                                                variant="outline"
                                                className="gap-1.5"
                                            >
                                                <span
                                                    className="size-2 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            subject.color ??
                                                            'transparent',
                                                    }}
                                                    aria-hidden
                                                />
                                                {subject.name} ·{' '}
                                                {subject.periods_per_week}
                                            </Badge>
                                        ),
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
        </div>
    );
}

ClassesIndex.layout = {
    breadcrumbs: [{ title: 'Classes & sections', href: index() }],
};
