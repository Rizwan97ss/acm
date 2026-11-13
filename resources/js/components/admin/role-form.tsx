import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import PermissionMatrix from '@/components/admin/permission-matrix';
import FormField from '@/components/form-field';
import InputError from '@/components/input-error';
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
import { index } from '@/routes/admin/roles';
import type { PermissionModule } from '@/types';

export type RoleFormOptions = {
    modules: PermissionModule[];
    parents: { id: number; name: string; permissions: string[] }[];
    grantable: string[];
};

type RoleFormData = {
    name: string;
    description: string;
    parent_id: number | null;
    permissions: string[];
};

type RoleFormProps = RoleFormOptions & {
    initial?: Partial<RoleFormData>;
    submitUrl: string;
    method: 'post' | 'put';
    submitLabel: string;
};

export default function RoleForm({
    modules,
    parents,
    grantable,
    initial,
    submitUrl,
    method,
    submitLabel,
}: RoleFormProps) {
    const form = useForm<RoleFormData>({
        name: '',
        description: '',
        parent_id: null,
        permissions: [],
        ...initial,
    });

    const inherited =
        parents.find((parent) => parent.id === form.data.parent_id)
            ?.permissions ?? [];
    const effectiveCount = new Set([...form.data.permissions, ...inherited])
        .size;

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.submit(method, submitUrl, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Role details</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-5 md:grid-cols-2">
                    <FormField
                        id="name"
                        label="Name"
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
                            maxLength={100}
                        />
                    </FormField>
                    <FormField
                        id="parent_id"
                        label="Inherits from"
                        error={form.errors.parent_id}
                        description="The role receives every permission of the parent, now and in future."
                    >
                        <NativeSelect
                            id="parent_id"
                            value={form.data.parent_id ?? ''}
                            onChange={(e) =>
                                form.setData(
                                    'parent_id',
                                    e.target.value === ''
                                        ? null
                                        : Number(e.target.value),
                                )
                            }
                        >
                            <option value="">No parent</option>
                            {parents.map((parent) => (
                                <option key={parent.id} value={parent.id}>
                                    {parent.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField
                        id="description"
                        label="Description"
                        error={form.errors.description}
                        className="md:col-span-2"
                    >
                        <Textarea
                            id="description"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                            rows={2}
                            maxLength={255}
                        />
                    </FormField>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Permissions</CardTitle>
                    <CardDescription>
                        {effectiveCount} effective permissions
                        {inherited.length > 0 &&
                            `, ${inherited.length} inherited`}
                        . Click a module or action heading to toggle a whole row
                        or column.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-2">
                    <PermissionMatrix
                        modules={modules}
                        selected={form.data.permissions}
                        inherited={inherited}
                        grantable={grantable}
                        onChange={(permissions) =>
                            form.setData('permissions', permissions)
                        }
                    />
                    <InputError message={form.errors.permissions} />
                </CardContent>
            </Card>

            <div className="flex gap-2">
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
