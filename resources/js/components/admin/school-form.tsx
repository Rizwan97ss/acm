import { Form, Link } from '@inertiajs/react';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/admin/schools';
import type { Option, School } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

type SchoolFormProps = {
    school?: School;
    parents: Option[];
    action: RouteFormDefinition<'post'>;
    submitLabel: string;
};

export default function SchoolForm({
    school,
    parents,
    action,
    submitLabel,
}: SchoolFormProps) {
    return (
        <Form
            {...action}
            options={{ preserveScroll: true }}
            className="space-y-6"
        >
            {({ errors, processing, progress }) => (
                <>
                    <Card>
                        <CardHeader>
                            <CardTitle>School details</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-5 md:grid-cols-2">
                            <FormField
                                id="name"
                                label="Name"
                                error={errors.name}
                                required
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={school?.name}
                                    required
                                    maxLength={255}
                                />
                            </FormField>
                            <FormField
                                id="code"
                                label="Code"
                                error={errors.code}
                                required
                                description="Short unique identifier, e.g. GHS."
                            >
                                <Input
                                    id="code"
                                    name="code"
                                    defaultValue={school?.code}
                                    required
                                    maxLength={32}
                                    className="uppercase"
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
                                    defaultValue={school?.email ?? ''}
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
                                    defaultValue={school?.phone ?? ''}
                                />
                            </FormField>
                            <FormField
                                id="address"
                                label="Address"
                                error={errors.address}
                                className="md:col-span-2"
                            >
                                <Textarea
                                    id="address"
                                    name="address"
                                    defaultValue={school?.address ?? ''}
                                    rows={2}
                                />
                            </FormField>
                            {parents.length > 0 && (
                                <FormField
                                    id="parent_id"
                                    label="Branch of"
                                    error={errors.parent_id}
                                    description="Leave empty for an independent school."
                                >
                                    <NativeSelect
                                        id="parent_id"
                                        name="parent_id"
                                        defaultValue={school?.parent_id ?? ''}
                                    >
                                        <option value="">
                                            Independent school
                                        </option>
                                        {parents.map((parent) => (
                                            <option
                                                key={parent.id}
                                                value={parent.id}
                                            >
                                                {parent.name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>
                            )}
                            <div className="flex items-center gap-2 self-end pb-2">
                                <input
                                    type="hidden"
                                    name="is_active"
                                    value="0"
                                />
                                <input
                                    id="is_active"
                                    name="is_active"
                                    type="checkbox"
                                    value="1"
                                    defaultChecked={school?.is_active ?? true}
                                    className="size-4 rounded border-input accent-primary"
                                />
                                <label
                                    htmlFor="is_active"
                                    className="text-sm font-medium"
                                >
                                    School is active
                                </label>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Logo</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-center">
                            {school?.logo_url && (
                                <img
                                    src={school.logo_url}
                                    alt={`${school.name} logo`}
                                    className="size-16 rounded-lg border object-cover"
                                />
                            )}
                            <FormField
                                id="logo"
                                label="Upload"
                                error={errors.logo}
                                description="PNG, JPG or WebP, up to 1 MB."
                                className="flex-1"
                            >
                                <Input
                                    id="logo"
                                    name="logo"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                />
                            </FormField>
                            {school?.logo_url && (
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="hidden"
                                        name="remove_logo"
                                        value="0"
                                    />
                                    <input
                                        type="checkbox"
                                        name="remove_logo"
                                        value="1"
                                        className="size-4 accent-primary"
                                    />
                                    Remove current logo
                                </label>
                            )}
                        </CardContent>
                        {progress && (
                            <progress
                                value={progress.percentage}
                                max={100}
                                className="mx-6 mb-4 h-1 w-auto"
                            />
                        )}
                    </Card>

                    {errors.school && (
                        <p className="text-sm text-destructive">
                            {errors.school}
                        </p>
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
