import { Link, router } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import PageHeader from '@/components/page-header';
import GuardianForm from '@/components/people/guardian-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index, update } from '@/routes/people/guardians';
import { store as invite } from '@/routes/people/guardians/portal-account';
import { show as showStudent } from '@/routes/people/students';
import type { Guardian } from '@/types';

export default function EditGuardian({ guardian }: { guardian: Guardian }) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={`Edit ${guardian.full_name}`}
                actions={
                    guardian.can.invite &&
                    !guardian.has_portal_account && (
                        <Button
                            variant="outline"
                            disabled={!guardian.email}
                            title={
                                guardian.email
                                    ? undefined
                                    : 'Add an email address first'
                            }
                            onClick={() =>
                                router.visit(invite(guardian.id), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            <Mail aria-hidden /> Invite to portal
                        </Button>
                    )
                }
            />
            <div className="grid gap-6 xl:grid-cols-[1fr_20rem]">
                <GuardianForm
                    guardian={guardian}
                    action={update.form(guardian.id)}
                    submitLabel="Save changes"
                />
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Children</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="space-y-2 text-sm">
                            {(guardian.students ?? []).map((student) => (
                                <li
                                    key={student.id}
                                    className="flex justify-between gap-2"
                                >
                                    <Link
                                        href={showStudent(student.id)}
                                        className="font-medium hover:underline"
                                    >
                                        {student.full_name}
                                    </Link>
                                    <span className="text-muted-foreground capitalize">
                                        {student.relationship}
                                    </span>
                                </li>
                            ))}
                            {guardian.students?.length === 0 && (
                                <li className="text-muted-foreground">
                                    Not linked to any student.
                                </li>
                            )}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

EditGuardian.layout = {
    breadcrumbs: [{ title: 'Guardians', href: index() }],
};
