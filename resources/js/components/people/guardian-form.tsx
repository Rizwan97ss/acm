import { Form, Link } from '@inertiajs/react';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/people/guardians';
import type { Guardian } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

type Props = {
    guardian?: Guardian;
    action: RouteFormDefinition<'post'>;
    submitLabel: string;
};

export default function GuardianForm({ guardian, action, submitLabel }: Props) {
    return (
        <Form
            {...action}
            options={{ preserveScroll: true }}
            className="max-w-3xl space-y-6"
        >
            {({ errors, processing }) => (
                <>
                    <Card>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            <FormField
                                id="first_name"
                                label="First name"
                                error={errors.first_name}
                                required
                            >
                                <Input
                                    id="first_name"
                                    name="first_name"
                                    defaultValue={guardian?.first_name}
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
                                    defaultValue={guardian?.last_name}
                                    required
                                    maxLength={100}
                                />
                            </FormField>
                            <FormField
                                id="phone"
                                label="Phone"
                                error={errors.phone}
                                required
                                description="Encrypted; searchable by exact number."
                            >
                                <Input
                                    id="phone"
                                    name="phone"
                                    type="tel"
                                    defaultValue={guardian?.phone ?? ''}
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
                                    defaultValue={guardian?.email ?? ''}
                                />
                            </FormField>
                            <FormField
                                id="occupation"
                                label="Occupation"
                                error={errors.occupation}
                            >
                                <Input
                                    id="occupation"
                                    name="occupation"
                                    defaultValue={guardian?.occupation ?? ''}
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
                                    defaultValue={guardian?.address ?? ''}
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
