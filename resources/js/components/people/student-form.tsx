import { Form, Link } from '@inertiajs/react';
import FormField from '@/components/form-field';
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
import { index } from '@/routes/people/students';
import type { EnumOption, SectionOption, Student, YearOption } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

export type StudentFormOptions = {
    years: YearOption[];
    sections: SectionOption[];
    statuses: EnumOption[];
    genders: EnumOption[];
    bloodGroups: string[];
};

type Props = StudentFormOptions & {
    student?: Student;
    action: RouteFormDefinition<'post'>;
    submitLabel: string;
};

export default function StudentForm({
    student,
    years,
    sections,
    statuses,
    genders,
    bloodGroups,
    action,
    submitLabel,
}: Props) {
    const currentYear = years.find((year) => year.is_current) ?? years[0];
    const today = new Date().toISOString().slice(0, 10);

    return (
        <Form
            {...action}
            options={{ preserveScroll: true }}
            className="space-y-6"
        >
            {({ errors, processing }) => (
                <>
                    <Card>
                        <CardHeader>
                            <CardTitle>Student</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            <FormField
                                id="first_name"
                                label="First name"
                                error={errors.first_name}
                                required
                            >
                                <Input
                                    id="first_name"
                                    name="first_name"
                                    defaultValue={student?.first_name}
                                    required
                                    maxLength={100}
                                />
                            </FormField>
                            <FormField
                                id="last_name"
                                label="Last name"
                                error={errors.last_name}
                                required
                            >
                                <Input
                                    id="last_name"
                                    name="last_name"
                                    defaultValue={student?.last_name}
                                    required
                                    maxLength={100}
                                />
                            </FormField>
                            <FormField
                                id="admission_no"
                                label="Admission number"
                                error={errors.admission_no}
                                description={
                                    student
                                        ? undefined
                                        : 'Leave empty to generate one.'
                                }
                            >
                                <Input
                                    id="admission_no"
                                    name="admission_no"
                                    defaultValue={student?.admission_no}
                                    maxLength={32}
                                    className="uppercase"
                                />
                            </FormField>
                            <FormField
                                id="gender"
                                label="Gender"
                                error={errors.gender}
                                required
                            >
                                <NativeSelect
                                    id="gender"
                                    name="gender"
                                    defaultValue={student?.gender ?? ''}
                                    required
                                >
                                    <option value="" disabled>
                                        Select…
                                    </option>
                                    {genders.map((gender) => (
                                        <option
                                            key={gender.value}
                                            value={gender.value}
                                        >
                                            {gender.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                            <FormField
                                id="date_of_birth"
                                label="Date of birth"
                                error={errors.date_of_birth}
                                required
                            >
                                <Input
                                    id="date_of_birth"
                                    name="date_of_birth"
                                    type="date"
                                    defaultValue={student?.date_of_birth}
                                    max={today}
                                    required
                                />
                            </FormField>
                            <FormField
                                id="admission_date"
                                label="Admission date"
                                error={errors.admission_date}
                                required
                            >
                                <Input
                                    id="admission_date"
                                    name="admission_date"
                                    type="date"
                                    defaultValue={
                                        student?.admission_date ?? today
                                    }
                                    required
                                />
                            </FormField>
                            <FormField
                                id="email"
                                label="Email"
                                error={errors.email}
                                description="Needed for a portal account."
                            >
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    defaultValue={student?.email ?? ''}
                                />
                            </FormField>
                            <FormField
                                id="blood_group"
                                label="Blood group"
                                error={errors.blood_group}
                            >
                                <NativeSelect
                                    id="blood_group"
                                    name="blood_group"
                                    defaultValue={student?.blood_group ?? ''}
                                >
                                    <option value="">Unknown</option>
                                    {bloodGroups.map((group) => (
                                        <option key={group} value={group}>
                                            {group}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                            {student && (
                                <FormField
                                    id="status"
                                    label="Status"
                                    error={errors.status}
                                    required
                                >
                                    <NativeSelect
                                        id="status"
                                        name="status"
                                        defaultValue={student.status}
                                    >
                                        {statuses.map((status) => (
                                            <option
                                                key={status.value}
                                                value={status.value}
                                            >
                                                {status.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Confidential details</CardTitle>
                            <CardDescription>
                                Encrypted at rest and only shown to staff who
                                can edit students.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            <FormField
                                id="national_id"
                                label="National ID"
                                error={errors.national_id}
                            >
                                <Input
                                    id="national_id"
                                    name="national_id"
                                    defaultValue={student?.national_id ?? ''}
                                    maxLength={32}
                                    autoComplete="off"
                                />
                            </FormField>
                            <FormField
                                id="address"
                                label="Address"
                                error={errors.address}
                            >
                                <Input
                                    id="address"
                                    name="address"
                                    defaultValue={student?.address ?? ''}
                                    maxLength={500}
                                />
                            </FormField>
                            <FormField
                                id="medical_notes"
                                label="Medical notes"
                                error={errors.medical_notes}
                                className="sm:col-span-2"
                                description="Allergies, conditions and medication."
                            >
                                <Textarea
                                    id="medical_notes"
                                    name="medical_notes"
                                    defaultValue={student?.medical_notes ?? ''}
                                    rows={3}
                                    maxLength={2000}
                                />
                            </FormField>
                        </CardContent>
                    </Card>

                    {!student && currentYear && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Enrollment</CardTitle>
                                <CardDescription>
                                    Optionally place the student in a section
                                    now.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-5 sm:grid-cols-2">
                                <FormField
                                    id="enrollment_year"
                                    label="Academic year"
                                    error={
                                        errors['enrollment.academic_year_id']
                                    }
                                >
                                    <NativeSelect
                                        id="enrollment_year"
                                        name="enrollment[academic_year_id]"
                                        defaultValue={currentYear.id}
                                    >
                                        {years.map((year) => (
                                            <option
                                                key={year.id}
                                                value={year.id}
                                            >
                                                {year.name}
                                                {year.is_current
                                                    ? ' (current)'
                                                    : ''}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>
                                <FormField
                                    id="enrollment_section"
                                    label="Section"
                                    error={errors['enrollment.section_id']}
                                >
                                    <NativeSelect
                                        id="enrollment_section"
                                        name="enrollment[section_id]"
                                        defaultValue=""
                                    >
                                        <option value="">
                                            Do not enroll yet
                                        </option>
                                        {sections.map((section) => (
                                            <option
                                                key={section.id}
                                                value={section.id}
                                            >
                                                {section.name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>
                            </CardContent>
                        </Card>
                    )}

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>
                            {submitLabel}
                        </Button>
                        <Button variant="ghost" asChild>
                            <Link href={index()}>Cancel</Link>
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
