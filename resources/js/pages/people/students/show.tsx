import { Link, router, useForm } from '@inertiajs/react';
import { KeyRound, Mail, Pencil, Star, Unlink } from 'lucide-react';
import type { FormEvent } from 'react';
import ConfirmDelete from '@/components/confirm-delete';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import LinkGuardianDialog from '@/components/people/link-guardian-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useFormat } from '@/hooks/use-format';
import {
    destroy as removeConcession,
    update as updateConcession,
} from '@/routes/finance/concessions';
import { destroy, edit, index } from '@/routes/people/students';
import { store as enroll } from '@/routes/people/students/enrollments';
import { destroy as unlinkGuardian } from '@/routes/people/students/guardians';
import { store as invite } from '@/routes/people/students/portal-account';
import type {
    EnumOption,
    Guardian,
    SectionOption,
    Student,
    YearOption,
} from '@/types';

type Props = {
    student: Student;
    guardians: Guardian[];
    enrollments: {
        id: number;
        academic_year: string;
        academic_year_id: number;
        section: string;
        section_id: number;
        roll_number: number | null;
        status: string;
    }[];
    years: YearOption[];
    sections: SectionOption[];
    relationships: EnumOption[];
    concession: { percent: string; reason: string } | null;
    can: {
        linkGuardians: boolean;
        createGuardians: boolean;
        viewConcession: boolean;
        manageConcession: boolean;
    };
};

function ConcessionCard({
    studentId,
    concession,
    canManage,
}: {
    studentId: number;
    concession: Props['concession'];
    canManage: boolean;
}) {
    const form = useForm({
        percent: concession?.percent ?? '',
        reason: concession?.reason ?? '',
    });

    const save = (event: FormEvent): void => {
        event.preventDefault();
        form.put(updateConcession.url(studentId), { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Fee concession</CardTitle>
                <CardDescription>
                    Applied to invoices generated from now on.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {canManage ? (
                    <form
                        onSubmit={save}
                        className="grid items-end gap-3 sm:grid-cols-[7rem_1fr_auto_auto]"
                    >
                        <FormField
                            id="concession-percent"
                            label="Discount %"
                            error={form.errors.percent}
                        >
                            <Input
                                id="concession-percent"
                                type="number"
                                min={0.01}
                                max={100}
                                step="0.01"
                                value={form.data.percent}
                                onChange={(e) =>
                                    form.setData('percent', e.target.value)
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            id="concession-reason"
                            label="Reason"
                            error={form.errors.reason}
                        >
                            <Input
                                id="concession-reason"
                                value={form.data.reason}
                                onChange={(e) =>
                                    form.setData('reason', e.target.value)
                                }
                                placeholder="Sibling discount"
                                required
                            />
                        </FormField>
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                        {concession && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() =>
                                    router.visit(removeConcession(studentId), {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                Remove
                            </Button>
                        )}
                    </form>
                ) : concession ? (
                    <p className="text-sm">
                        {concession.percent}% · {concession.reason}
                    </p>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        No concession.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

function Detail({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-0.5">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm">{children ?? '—'}</dd>
        </div>
    );
}

function EnrollForm({
    studentId,
    years,
    sections,
    current,
}: {
    studentId: number;
    years: YearOption[];
    sections: SectionOption[];
    current?: Student['current_enrollment'];
}) {
    const currentYear = years.find((year) => year.is_current) ?? years[0];
    const form = useForm({
        academic_year_id: currentYear?.id ?? '',
        section_id: current?.section_id ?? sections[0]?.id ?? '',
        roll_number: '' as string | number,
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.post(enroll.url(studentId), {
            preserveScroll: true,
            onSuccess: () => form.setData('roll_number', ''),
        });
    };

    if (!currentYear || sections.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Create an academic year and sections first.
            </p>
        );
    }

    return (
        <form
            onSubmit={submit}
            className="grid items-end gap-3 sm:grid-cols-[1fr_1.5fr_6rem_auto]"
        >
            <FormField
                id="enroll-year"
                label="Year"
                error={form.errors.academic_year_id}
            >
                <NativeSelect
                    id="enroll-year"
                    value={form.data.academic_year_id}
                    onChange={(e) =>
                        form.setData('academic_year_id', Number(e.target.value))
                    }
                >
                    {years.map((year) => (
                        <option key={year.id} value={year.id}>
                            {year.name}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <FormField
                id="enroll-section"
                label="Section"
                error={form.errors.section_id}
            >
                <NativeSelect
                    id="enroll-section"
                    value={form.data.section_id}
                    onChange={(e) =>
                        form.setData('section_id', Number(e.target.value))
                    }
                >
                    {sections.map((section) => (
                        <option key={section.id} value={section.id}>
                            {section.name}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <FormField
                id="enroll-roll"
                label="Roll no."
                error={form.errors.roll_number}
            >
                <Input
                    id="enroll-roll"
                    type="number"
                    min={1}
                    placeholder="Auto"
                    value={form.data.roll_number}
                    onChange={(e) =>
                        form.setData('roll_number', e.target.value)
                    }
                />
            </FormField>
            <Button type="submit" disabled={form.processing}>
                Save
            </Button>
        </form>
    );
}

export default function ShowStudent({
    student,
    guardians,
    enrollments,
    years,
    sections,
    relationships,
    concession,
    can,
}: Props) {
    const format = useFormat();
    const day = (value: string): string => format.date(`${value}T12:00:00Z`);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={student.full_name}
                description={`${student.admission_no} · ${student.current_enrollment?.section ?? 'Not enrolled this year'}`}
                actions={
                    <>
                        {student.can.invite && !student.has_portal_account && (
                            <Button
                                variant="outline"
                                disabled={!student.email}
                                title={
                                    student.email
                                        ? undefined
                                        : 'Add an email address first'
                                }
                                onClick={() =>
                                    router.visit(invite(student.id), {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                <Mail aria-hidden /> Invite to portal
                            </Button>
                        )}
                        {student.can.update && (
                            <Button variant="outline" asChild>
                                <Link href={edit(student.id)}>
                                    <Pencil aria-hidden /> Edit
                                </Link>
                            </Button>
                        )}
                        {student.can.delete && (
                            <ConfirmDelete
                                action={destroy(student.id)}
                                label="Archive"
                                title={`Archive ${student.full_name}?`}
                                description="The record is kept for history but hidden from lists, and any portal account is deactivated."
                            />
                        )}
                    </>
                }
            />

            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Profile</CardTitle>
                        <div className="flex gap-1.5">
                            <Badge
                                variant={
                                    student.status === 'active'
                                        ? 'secondary'
                                        : 'outline'
                                }
                            >
                                {student.status_label}
                            </Badge>
                            {student.has_portal_account && (
                                <Badge variant="outline" className="gap-1">
                                    <KeyRound aria-hidden /> Portal
                                </Badge>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 sm:grid-cols-3">
                            <Detail label="Gender">
                                {student.gender_label}
                            </Detail>
                            <Detail label="Date of birth">
                                {day(student.date_of_birth)}
                            </Detail>
                            <Detail label="Admitted">
                                {day(student.admission_date)}
                            </Detail>
                            <Detail label="Email">{student.email}</Detail>
                            <Detail label="Blood group">
                                {student.blood_group}
                            </Detail>
                            <Detail label="National ID">
                                <span className="font-mono">
                                    {student.national_id}
                                </span>
                            </Detail>
                            <Detail label="Address">
                                {student.address ??
                                    (student.can.update ? null : 'Restricted')}
                            </Detail>
                            <div className="space-y-0.5 sm:col-span-2">
                                <dt className="text-xs text-muted-foreground">
                                    Medical notes
                                </dt>
                                <dd className="text-sm whitespace-pre-line">
                                    {student.medical_notes ??
                                        (student.has_medical_notes
                                            ? 'Recorded — restricted to authorised staff.'
                                            : '—')}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-2">
                        <div className="space-y-1.5">
                            <CardTitle>Guardians</CardTitle>
                            <CardDescription>
                                The starred guardian is the primary contact.
                            </CardDescription>
                        </div>
                        {can.linkGuardians && (
                            <LinkGuardianDialog
                                studentId={student.id}
                                relationships={relationships}
                                canCreate={can.createGuardians}
                            />
                        )}
                    </CardHeader>
                    <CardContent>
                        <ul className="space-y-3">
                            {guardians.map((guardian) => (
                                <li
                                    key={guardian.id}
                                    className="flex items-start justify-between gap-2 rounded-lg border p-3"
                                >
                                    <div className="min-w-0 space-y-0.5 text-sm">
                                        <p className="flex items-center gap-1.5 font-medium">
                                            {guardian.link?.is_primary && (
                                                <Star
                                                    className="size-3.5 fill-amber-400 text-amber-500"
                                                    aria-label="Primary guardian"
                                                />
                                            )}
                                            {guardian.full_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground capitalize">
                                            {guardian.link?.relationship}
                                            {guardian.link?.is_emergency_contact
                                                ? ' · emergency contact'
                                                : ''}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {[guardian.phone, guardian.email]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                    </div>
                                    {can.linkGuardians && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Unlink ${guardian.full_name}`}
                                            onClick={() =>
                                                router.visit(
                                                    unlinkGuardian([
                                                        student.id,
                                                        guardian.id,
                                                    ]),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Unlink aria-hidden />
                                        </Button>
                                    )}
                                </li>
                            ))}
                            {guardians.length === 0 && (
                                <li className="text-sm text-muted-foreground">
                                    No guardians linked.
                                </li>
                            )}
                        </ul>
                    </CardContent>
                </Card>
            </div>

            {can.viewConcession && (
                <ConcessionCard
                    studentId={student.id}
                    concession={concession}
                    canManage={can.manageConcession}
                />
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Enrollment</CardTitle>
                    <CardDescription>
                        One placement per academic year. Saving a new section
                        for the same year transfers the student.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-5">
                    {student.can.update && (
                        <EnrollForm
                            studentId={student.id}
                            years={years}
                            sections={sections}
                            current={student.current_enrollment}
                        />
                    )}
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <caption className="sr-only">
                                Enrollment history
                            </caption>
                            <thead className="text-left text-xs text-muted-foreground">
                                <tr>
                                    <th
                                        scope="col"
                                        className="py-2 pr-4 font-medium"
                                    >
                                        Year
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-2 pr-4 font-medium"
                                    >
                                        Section
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-2 pr-4 font-medium"
                                    >
                                        Roll no.
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-2 font-medium"
                                    >
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {enrollments.map((enrollment) => (
                                    <tr key={enrollment.id}>
                                        <td className="py-2 pr-4">
                                            {enrollment.academic_year}
                                        </td>
                                        <td className="py-2 pr-4">
                                            {enrollment.section}
                                        </td>
                                        <td className="py-2 pr-4 tabular-nums">
                                            {enrollment.roll_number ?? '—'}
                                        </td>
                                        <td className="py-2">
                                            {enrollment.status}
                                        </td>
                                    </tr>
                                ))}
                                {enrollments.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="py-4 text-muted-foreground"
                                        >
                                            No enrollments yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

ShowStudent.layout = {
    breadcrumbs: [{ title: 'Students', href: index() }],
};
