import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
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
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { index } from '@/routes/admin/users';
import type { Option } from '@/types';

export type RoleOption = Option & {
    school_id: number | null;
    description: string | null;
};

export type UserFormOptions = {
    roles: RoleOption[];
    schools: Option[];
    defaultSchoolId: number | null;
    passwordRules: string;
};

type UserFormData = {
    name: string;
    email: string;
    phone: string;
    password: string;
    password_confirmation: string;
    school_id: number | null;
    is_active: boolean;
    roles: number[];
};

type UserFormProps = UserFormOptions & {
    initial?: Partial<UserFormData>;
    submitUrl: string;
    method: 'post' | 'put';
    submitLabel: string;
};

export default function UserForm({
    roles,
    schools,
    defaultSchoolId,
    passwordRules,
    initial,
    submitUrl,
    method,
    submitLabel,
}: UserFormProps) {
    const isEditing = method === 'put';
    const canChooseSchool = schools.length > 0;

    const form = useForm<UserFormData>({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        school_id: defaultSchoolId,
        is_active: true,
        roles: [],
        ...initial,
    });

    /** Roles usable in the chosen school: platform-wide roles plus that school's own. */
    const availableRoles = canChooseSchool
        ? roles.filter(
              (role) =>
                  role.school_id === null ||
                  role.school_id === form.data.school_id,
          )
        : roles;

    const toggleRole = (roleId: number, checked: boolean): void => {
        form.setData(
            'roles',
            checked
                ? [...form.data.roles, roleId]
                : form.data.roles.filter((id) => id !== roleId),
        );
    };

    const changeSchool = (value: string): void => {
        const schoolId = value === '' ? null : Number(value);
        const stillValid = form.data.roles.filter((id) => {
            const role = roles.find((candidate) => candidate.id === id);

            return (
                role && (role.school_id === null || role.school_id === schoolId)
            );
        });

        form.setData((data) => ({
            ...data,
            school_id: schoolId,
            roles: stillValid,
        }));
    };

    const submit = (event: FormEvent): void => {
        event.preventDefault();

        const payload = canChooseSchool
            ? form.data
            : { ...form.data, school_id: undefined };
        form.transform(() => payload);
        form.submit(method, submitUrl, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="grid gap-6 lg:grid-cols-3">
            <Card className="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Account</CardTitle>
                    <CardDescription>
                        Contact details are encrypted at rest.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5 sm:grid-cols-2">
                    <FormField
                        id="name"
                        label="Full name"
                        error={form.errors.name}
                        required
                    >
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                            autoComplete="off"
                        />
                    </FormField>
                    <FormField
                        id="email"
                        label="Email"
                        error={form.errors.email}
                        required
                    >
                        <Input
                            id="email"
                            type="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            required
                            autoComplete="off"
                        />
                    </FormField>
                    <FormField
                        id="phone"
                        label="Phone"
                        error={form.errors.phone}
                    >
                        <Input
                            id="phone"
                            type="tel"
                            value={form.data.phone}
                            onChange={(e) =>
                                form.setData('phone', e.target.value)
                            }
                            autoComplete="off"
                        />
                    </FormField>
                    {canChooseSchool && (
                        <FormField
                            id="school_id"
                            label="School"
                            error={form.errors.school_id}
                            description="Leave empty for a platform account."
                        >
                            <NativeSelect
                                id="school_id"
                                value={form.data.school_id ?? ''}
                                onChange={(e) => changeSchool(e.target.value)}
                            >
                                <option value="">Platform (no school)</option>
                                {schools.map((school) => (
                                    <option key={school.id} value={school.id}>
                                        {school.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                    )}
                    <FormField
                        id="password"
                        label={isEditing ? 'New password' : 'Password'}
                        error={form.errors.password}
                        required={!isEditing}
                        description={
                            isEditing
                                ? 'Leave blank to keep the current password.'
                                : undefined
                        }
                    >
                        <PasswordInput
                            id="password"
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                            required={!isEditing}
                            autoComplete="new-password"
                            passwordrules={passwordRules}
                        />
                    </FormField>
                    <FormField
                        id="password_confirmation"
                        label="Confirm password"
                        error={form.errors.password_confirmation}
                        required={!isEditing}
                    >
                        <PasswordInput
                            id="password_confirmation"
                            value={form.data.password_confirmation}
                            onChange={(e) =>
                                form.setData(
                                    'password_confirmation',
                                    e.target.value,
                                )
                            }
                            required={!isEditing}
                            autoComplete="new-password"
                        />
                    </FormField>
                    <div className="flex items-center gap-2 sm:col-span-2">
                        <Checkbox
                            id="is_active"
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked === true)
                            }
                        />
                        <Label htmlFor="is_active">Account is active</Label>
                        <InputError message={form.errors.is_active} />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Roles</CardTitle>
                    <CardDescription>
                        You can only assign roles whose permissions you hold.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    <fieldset className="space-y-3">
                        <legend className="sr-only">Roles</legend>
                        {availableRoles.map((role) => (
                            <div
                                key={role.id}
                                className="flex items-start gap-2"
                            >
                                <Checkbox
                                    id={`role-${role.id}`}
                                    checked={form.data.roles.includes(role.id)}
                                    onCheckedChange={(checked) =>
                                        toggleRole(role.id, checked === true)
                                    }
                                    className="mt-0.5"
                                />
                                <Label
                                    htmlFor={`role-${role.id}`}
                                    className="grid gap-0.5 font-normal"
                                >
                                    <span className="font-medium">
                                        {role.name}
                                    </span>
                                    {role.description && (
                                        <span className="text-xs text-muted-foreground">
                                            {role.description}
                                        </span>
                                    )}
                                </Label>
                            </div>
                        ))}
                        {availableRoles.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No roles available.
                            </p>
                        )}
                    </fieldset>
                    <InputError message={form.errors.roles} />
                </CardContent>
            </Card>

            <div className="flex gap-2 lg:col-span-3">
                <Button type="submit" disabled={form.processing}>
                    {submitLabel}
                </Button>
                <Button variant="ghost" asChild>
                    <Link href={index()}>Cancel</Link>
                </Button>
            </div>
        </form>
    );
}
