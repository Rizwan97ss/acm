import { Form, Link } from '@inertiajs/react';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/academics/subjects';
import type { Subject } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

type Props = {
    subject?: Subject;
    types: { value: string; label: string }[];
    action: RouteFormDefinition<'post'>;
    submitLabel: string;
};

export default function SubjectForm({
    subject,
    types,
    action,
    submitLabel,
}: Props) {
    return (
        <Form
            {...action}
            options={{ preserveScroll: true }}
            className="max-w-2xl space-y-6"
        >
            {({ errors, processing }) => (
                <>
                    <Card>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            <FormField
                                id="name"
                                label="Name"
                                error={errors.name}
                                required
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={subject?.name}
                                    required
                                    maxLength={100}
                                />
                            </FormField>
                            <FormField
                                id="code"
                                label="Code"
                                error={errors.code}
                                required
                                description="Unique within the school, e.g. MATH."
                            >
                                <Input
                                    id="code"
                                    name="code"
                                    defaultValue={subject?.code}
                                    required
                                    maxLength={20}
                                    className="uppercase"
                                />
                            </FormField>
                            <FormField
                                id="type"
                                label="Type"
                                error={errors.type}
                                required
                            >
                                <NativeSelect
                                    id="type"
                                    name="type"
                                    defaultValue={subject?.type ?? 'core'}
                                >
                                    {types.map((type) => (
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
                                id="color"
                                label="Timetable colour"
                                error={errors.color}
                            >
                                <input
                                    id="color"
                                    name="color"
                                    type="color"
                                    defaultValue={subject?.color ?? '#2563eb'}
                                    className="h-9 w-16 cursor-pointer rounded-md border bg-transparent p-1"
                                />
                            </FormField>
                            <FormField
                                id="description"
                                label="Description"
                                error={errors.description}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    id="description"
                                    name="description"
                                    defaultValue={subject?.description ?? ''}
                                    rows={3}
                                />
                            </FormField>
                            <div className="flex items-center gap-2">
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
                                    defaultChecked={subject?.is_active ?? true}
                                    className="size-4 accent-primary"
                                />
                                <label
                                    htmlFor="is_active"
                                    className="text-sm font-medium"
                                >
                                    Active (available for classes and
                                    timetables)
                                </label>
                            </div>
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
