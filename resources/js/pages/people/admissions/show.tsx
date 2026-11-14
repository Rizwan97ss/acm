import { Link, useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import AdmissionStatusBadge from '@/components/people/admission-status-badge';
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
import { Textarea } from '@/components/ui/textarea';
import { useFormat } from '@/hooks/use-format';
import { decision, edit, enroll, index } from '@/routes/people/admissions';
import { show as showStudent } from '@/routes/people/students';
import type { AdmissionApplication } from '@/types';

type SectionChoice = {
    id: number;
    name: string;
    capacity: number | null;
    enrolled: number;
};

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

function DecisionForm({ application }: { application: AdmissionApplication }) {
    const form = useForm({ decision: '', notes: '' });

    const decide =
        (value: 'approved' | 'rejected') =>
        (event: FormEvent): void => {
            event.preventDefault();
            form.transform((data) => ({ ...data, decision: value }));
            form.post(decision.url(application.id), { preserveScroll: true });
        };

    return (
        <form className="space-y-3" onSubmit={decide('approved')}>
            <FormField
                id="decision-notes"
                label="Notes"
                error={form.errors.notes}
                description="Visible to staff; explain a rejection here."
            >
                <Textarea
                    id="decision-notes"
                    value={form.data.notes}
                    onChange={(e) => form.setData('notes', e.target.value)}
                    rows={3}
                />
            </FormField>
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>
                    Approve
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={form.processing}
                    onClick={decide('rejected')}
                >
                    Reject
                </Button>
            </div>
        </form>
    );
}

function EnrollForm({
    application,
    sections,
}: {
    application: AdmissionApplication;
    sections: SectionChoice[];
}) {
    const firstOpen = sections.find(
        (section) =>
            section.capacity === null || section.enrolled < section.capacity,
    );
    const form = useForm({
        section_id: firstOpen?.id ?? sections[0]?.id ?? '',
        admission_no: '',
    });
    const statusError = (form.errors as Record<string, string | undefined>)
        .status;

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.post(enroll.url(application.id), { preserveScroll: true });
    };

    if (sections.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                This class has no sections yet.
            </p>
        );
    }

    return (
        <form className="space-y-3" onSubmit={submit}>
            <FormField
                id="enroll-section"
                label="Section"
                error={form.errors.section_id}
                required
            >
                <NativeSelect
                    id="enroll-section"
                    value={form.data.section_id}
                    onChange={(e) =>
                        form.setData('section_id', Number(e.target.value))
                    }
                >
                    {sections.map((section) => {
                        const full =
                            section.capacity !== null &&
                            section.enrolled >= section.capacity;

                        return (
                            <option
                                key={section.id}
                                value={section.id}
                                disabled={full}
                            >
                                {section.name} — {section.enrolled}
                                {section.capacity !== null
                                    ? `/${section.capacity}`
                                    : ''}{' '}
                                enrolled{full ? ' (full)' : ''}
                            </option>
                        );
                    })}
                </NativeSelect>
            </FormField>
            <FormField
                id="enroll-admission-no"
                label="Admission number"
                error={form.errors.admission_no}
                description="Leave empty to generate one."
            >
                <Input
                    id="enroll-admission-no"
                    value={form.data.admission_no}
                    onChange={(e) =>
                        form.setData('admission_no', e.target.value)
                    }
                    className="uppercase"
                />
            </FormField>
            {statusError && (
                <p className="text-sm text-destructive">{statusError}</p>
            )}
            <Button type="submit" disabled={form.processing}>
                Enroll student
            </Button>
        </form>
    );
}

export default function ShowAdmission({
    application,
    sections,
}: {
    application: AdmissionApplication;
    sections: SectionChoice[];
}) {
    const format = useFormat();

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={application.full_name}
                description={`${application.application_no} · ${application.school_class} · ${application.academic_year}`}
                actions={
                    application.can.update && (
                        <Button variant="outline" asChild>
                            <Link href={edit(application.id)}>
                                <Pencil aria-hidden /> Edit
                            </Link>
                        </Button>
                    )
                }
            />
            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Application</CardTitle>
                        <AdmissionStatusBadge
                            status={application.status}
                            label={application.status_label}
                        />
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 sm:grid-cols-3">
                            <Detail label="Gender">
                                {application.gender_label}
                            </Detail>
                            <Detail label="Date of birth">
                                {format.date(
                                    `${application.date_of_birth}T12:00:00Z`,
                                )}
                            </Detail>
                            <Detail label="Previous school">
                                {application.previous_school}
                            </Detail>
                            <Detail label="Guardian">
                                {application.guardian_name} (
                                {application.guardian_relationship_label})
                            </Detail>
                            <Detail label="Phone">
                                {application.guardian_phone}
                            </Detail>
                            <Detail label="Email">
                                {application.guardian_email}
                            </Detail>
                            <div className="sm:col-span-3">
                                <Detail label="Notes">
                                    {application.notes}
                                </Detail>
                            </div>
                            <Detail label="Received">
                                {format.dateTime(application.created_at)}
                            </Detail>
                            {application.reviewer && (
                                <Detail label="Reviewed">
                                    {application.reviewer.name},{' '}
                                    {format.dateTime(application.reviewed_at)}
                                </Detail>
                            )}
                            {application.decision_notes && (
                                <Detail label="Decision notes">
                                    {application.decision_notes}
                                </Detail>
                            )}
                        </dl>
                    </CardContent>
                </Card>

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Next step</CardTitle>
                        <CardDescription>
                            {application.status === 'pending' &&
                                'Approve or reject the application.'}
                            {application.status === 'approved' &&
                                'Place the applicant in a section to create the student record.'}
                            {application.status === 'rejected' &&
                                'This application was rejected.'}
                            {application.status === 'enrolled' &&
                                'The applicant is now a student.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {application.can.decide && (
                            <DecisionForm application={application} />
                        )}
                        {application.can.enroll && (
                            <EnrollForm
                                application={application}
                                sections={sections}
                            />
                        )}
                        {application.status === 'enrolled' &&
                            application.student_id && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={showStudent(
                                            application.student_id,
                                        )}
                                    >
                                        Open student record
                                    </Link>
                                </Button>
                            )}
                        {application.status === 'pending' &&
                            !application.can.decide && (
                                <p className="text-sm text-muted-foreground">
                                    Awaiting review by someone with approval
                                    rights.
                                </p>
                            )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

ShowAdmission.layout = {
    breadcrumbs: [{ title: 'Admissions', href: index() }],
};
