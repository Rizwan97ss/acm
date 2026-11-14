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
import { index } from '@/routes/people/staff';
import type { Employee, EnumOption } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

export type EmployeeFormOptions = {
    genders: EnumOption[];
    employmentTypes: EnumOption[];
    statuses: EnumOption[];
    canManageNonTeaching: boolean;
    users: { id: number; name: string; email: string }[];
};

type Props = EmployeeFormOptions & {
    employee?: Employee;
    action: RouteFormDefinition<'post'>;
    submitLabel: string;
};

export default function EmployeeForm({
    employee,
    genders,
    employmentTypes,
    statuses,
    canManageNonTeaching,
    users,
    action,
    submitLabel,
}: Props) {
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
                            <CardTitle>Personal details</CardTitle>
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
                                    defaultValue={employee?.first_name}
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
                                    defaultValue={employee?.last_name}
                                    required
                                />
                            </FormField>
                            <FormField
                                id="gender"
                                label="Gender"
                                error={errors.gender}
                            >
                                <NativeSelect
                                    id="gender"
                                    name="gender"
                                    defaultValue={employee?.gender ?? ''}
                                >
                                    <option value="">Not specified</option>
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
                            >
                                <Input
                                    id="date_of_birth"
                                    name="date_of_birth"
                                    type="date"
                                    max={today}
                                    defaultValue={employee?.date_of_birth ?? ''}
                                />
                            </FormField>
                            <FormField
                                id="email"
                                label="Email"
                                error={errors.email}
                            >
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    defaultValue={employee?.email ?? ''}
                                />
                            </FormField>
                            <FormField
                                id="phone"
                                label="Phone"
                                error={errors.phone}
                            >
                                <Input
                                    id="phone"
                                    name="phone"
                                    type="tel"
                                    defaultValue={employee?.phone ?? ''}
                                />
                            </FormField>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Employment</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            <FormField
                                id="employee_no"
                                label="Employee number"
                                error={errors.employee_no}
                                description={
                                    employee
                                        ? undefined
                                        : 'Leave empty to generate one.'
                                }
                            >
                                <Input
                                    id="employee_no"
                                    name="employee_no"
                                    defaultValue={employee?.employee_no}
                                    className="uppercase"
                                />
                            </FormField>
                            <FormField
                                id="is_teaching"
                                label="Staff type"
                                error={errors.is_teaching}
                                required
                            >
                                <NativeSelect
                                    id="is_teaching"
                                    name="is_teaching"
                                    defaultValue={
                                        employee
                                            ? String(
                                                  Number(employee.is_teaching),
                                              )
                                            : '1'
                                    }
                                    disabled={!canManageNonTeaching}
                                >
                                    <option value="1">Teaching staff</option>
                                    <option value="0">
                                        Non-teaching staff
                                    </option>
                                </NativeSelect>
                                {!canManageNonTeaching && (
                                    <input
                                        type="hidden"
                                        name="is_teaching"
                                        value="1"
                                    />
                                )}
                            </FormField>
                            <FormField
                                id="designation"
                                label="Designation"
                                error={errors.designation}
                                required
                            >
                                <Input
                                    id="designation"
                                    name="designation"
                                    defaultValue={employee?.designation}
                                    placeholder="Mathematics teacher"
                                    required
                                />
                            </FormField>
                            <FormField
                                id="department"
                                label="Department"
                                error={errors.department}
                            >
                                <Input
                                    id="department"
                                    name="department"
                                    defaultValue={employee?.department ?? ''}
                                />
                            </FormField>
                            <FormField
                                id="qualification"
                                label="Qualification"
                                error={errors.qualification}
                            >
                                <Input
                                    id="qualification"
                                    name="qualification"
                                    defaultValue={employee?.qualification ?? ''}
                                />
                            </FormField>
                            <FormField
                                id="employment_type"
                                label="Employment type"
                                error={errors.employment_type}
                                required
                            >
                                <NativeSelect
                                    id="employment_type"
                                    name="employment_type"
                                    defaultValue={
                                        employee?.employment_type ?? 'full_time'
                                    }
                                >
                                    {employmentTypes.map((type) => (
                                        <option
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                            <FormField
                                id="joined_on"
                                label="Joined on"
                                error={errors.joined_on}
                                required
                            >
                                <Input
                                    id="joined_on"
                                    name="joined_on"
                                    type="date"
                                    defaultValue={employee?.joined_on ?? today}
                                    required
                                />
                            </FormField>
                            <FormField
                                id="left_on"
                                label="Left on"
                                error={errors.left_on}
                            >
                                <Input
                                    id="left_on"
                                    name="left_on"
                                    type="date"
                                    defaultValue={employee?.left_on ?? ''}
                                />
                            </FormField>
                            <FormField
                                id="status"
                                label="Status"
                                error={errors.status}
                                required
                            >
                                <NativeSelect
                                    id="status"
                                    name="status"
                                    defaultValue={employee?.status ?? 'active'}
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
                            <FormField
                                id="user_id"
                                label="Login account"
                                error={errors.user_id}
                                className="sm:col-span-2 lg:col-span-3"
                                description="Link the user account this person signs in with. Teaching staff with an account can be scheduled on timetables."
                            >
                                <NativeSelect
                                    id="user_id"
                                    name="user_id"
                                    defaultValue={employee?.user_id ?? ''}
                                >
                                    <option value="">No account</option>
                                    {users.map((user) => (
                                        <option key={user.id} value={user.id}>
                                            {user.name} ({user.email})
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Confidential details</CardTitle>
                            <CardDescription>
                                Encrypted at rest; used later by payroll.
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
                                    defaultValue={employee?.national_id ?? ''}
                                    autoComplete="off"
                                />
                            </FormField>
                            <FormField
                                id="bank_account"
                                label="Bank account"
                                error={errors.bank_account}
                            >
                                <Input
                                    id="bank_account"
                                    name="bank_account"
                                    defaultValue={employee?.bank_account ?? ''}
                                    autoComplete="off"
                                />
                            </FormField>
                            <FormField
                                id="address"
                                label="Address"
                                error={errors.address}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    id="address"
                                    name="address"
                                    defaultValue={employee?.address ?? ''}
                                    rows={2}
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
