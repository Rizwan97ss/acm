import { Form, Link } from '@inertiajs/react';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/people/admissions';
import type {
    AdmissionApplication,
    EnumOption,
    Option,
    YearOption,
} from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

export type AdmissionFormOptions = {
    years: YearOption[];
    classes: Option[];
    genders: EnumOption[];
    relationships: EnumOption[];
};

type Props = AdmissionFormOptions & {
    application?: AdmissionApplication;
    action: RouteFormDefinition<'post'>;
    submitLabel: string;
};

export default function AdmissionForm({
    application,
    years,
    classes,
    genders,
    relationships,
    action,
    submitLabel,
}: Props) {
    const defaultYear = years.find((year) => year.is_current) ?? years[0];
    const today = new Date().toISOString().slice(0, 10);

    return (
        <Form
            {...action}
            options={{ preserveScroll: true }}
            className="max-w-4xl space-y-6"
        >
            {({ errors, processing }) => (
                <>
                    <Card>
                        <CardHeader>
                            <CardTitle>Applicant</CardTitle>
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
                                    defaultValue={application?.first_name}
                                    required
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
                                    defaultValue={application?.last_name}
                                    required
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
                                    defaultValue={application?.gender ?? ''}
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
                                    max={today}
                                    defaultValue={application?.date_of_birth}
                                    required
                                />
                            </FormField>
                            <FormField
                                id="academic_year_id"
                                label="For academic year"
                                error={errors.academic_year_id}
                                required
                            >
                                <NativeSelect
                                    id="academic_year_id"
                                    name="academic_year_id"
                                    defaultValue={
                                        application?.academic_year_id ??
                                        defaultYear?.id ??
                                        ''
                                    }
                                    required
                                >
                                    {years.map((year) => (
                                        <option key={year.id} value={year.id}>
                                            {year.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                            <FormField
                                id="school_class_id"
                                label="Applying for class"
                                error={errors.school_class_id}
                                required
                            >
                                <NativeSelect
                                    id="school_class_id"
                                    name="school_class_id"
                                    defaultValue={
                                        application?.school_class_id ?? ''
                                    }
                                    required
                                >
                                    <option value="" disabled>
                                        Select…
                                    </option>
                                    {classes.map((schoolClass) => (
                                        <option
                                            key={schoolClass.id}
                                            value={schoolClass.id}
                                        >
                                            {schoolClass.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                            <FormField
                                id="previous_school"
                                label="Previous school"
                                error={errors.previous_school}
                                className="sm:col-span-2 lg:col-span-3"
                            >
                                <Input
                                    id="previous_school"
                                    name="previous_school"
                                    defaultValue={
                                        application?.previous_school ?? ''
                                    }
                                />
                            </FormField>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Parent or guardian</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            <FormField
                                id="guardian_name"
                                label="Full name"
                                error={errors.guardian_name}
                                required
                            >
                                <Input
                                    id="guardian_name"
                                    name="guardian_name"
                                    defaultValue={application?.guardian_name}
                                    required
                                />
                            </FormField>
                            <FormField
                                id="guardian_relationship"
                                label="Relationship"
                                error={errors.guardian_relationship}
                                required
                            >
                                <NativeSelect
                                    id="guardian_relationship"
                                    name="guardian_relationship"
                                    defaultValue={
                                        application?.guardian_relationship ??
                                        'mother'
                                    }
                                >
                                    {relationships.map((relationship) => (
                                        <option
                                            key={relationship.value}
                                            value={relationship.value}
                                        >
                                            {relationship.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                            <FormField
                                id="guardian_phone"
                                label="Phone"
                                error={errors.guardian_phone}
                                required
                            >
                                <Input
                                    id="guardian_phone"
                                    name="guardian_phone"
                                    type="tel"
                                    defaultValue={
                                        application?.guardian_phone ?? ''
                                    }
                                    required
                                />
                            </FormField>
                            <FormField
                                id="guardian_email"
                                label="Email"
                                error={errors.guardian_email}
                            >
                                <Input
                                    id="guardian_email"
                                    name="guardian_email"
                                    type="email"
                                    defaultValue={
                                        application?.guardian_email ?? ''
                                    }
                                />
                            </FormField>
                            <FormField
                                id="notes"
                                label="Notes"
                                error={errors.notes}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    id="notes"
                                    name="notes"
                                    defaultValue={application?.notes ?? ''}
                                    rows={3}
                                />
                            </FormField>
                        </CardContent>
                    </Card>

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
