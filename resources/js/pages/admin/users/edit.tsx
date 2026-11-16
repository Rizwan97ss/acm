import { router } from '@inertiajs/react';
import { ShieldOff } from 'lucide-react';
import { useState } from 'react';
import type { UserFormOptions } from '@/components/admin/user-form';
import UserForm from '@/components/admin/user-form';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { index, update } from '@/routes/admin/users';
import { destroy as resetTwoFactor } from '@/routes/admin/users/two-factor';
import type { ManagedUser } from '@/types';

type Props = UserFormOptions & {
    user: ManagedUser & { school_id: number | null; role_ids: number[] };
    canResetTwoFactor: boolean;
};

function ResetTwoFactor({ user }: { user: Props['user'] }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const confirm = (): void => {
        router.visit(resetTwoFactor(user.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setOpen(false);
            },
        });
    };

    return (
        <section className="flex max-w-3xl flex-col gap-3 rounded-xl border border-amber-300/60 bg-amber-50 p-4 sm:flex-row sm:items-center dark:border-amber-500/30 dark:bg-amber-500/10">
            <div className="min-w-0 flex-1">
                <h2 className="font-semibold">Two-factor sign-in is on</h2>
                <p className="text-sm text-muted-foreground">
                    If {user.name} has lost their phone and recovery codes, turn
                    it off so they can sign in with their password and set it up
                    again. They will be signed out everywhere.
                </p>
            </div>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogTrigger asChild>
                    <Button variant="outline" className="self-start">
                        <ShieldOff aria-hidden />
                        Turn off two-factor
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>
                        Turn off two-factor sign-in for {user.name}?
                    </DialogTitle>
                    <DialogDescription>
                        Only do this after confirming who is asking, for example
                        in person. Their sessions and API tokens will end and
                        the change is recorded in the audit log.
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            disabled={processing}
                            onClick={confirm}
                        >
                            Turn off two-factor
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}

export default function EditUser({
    user,
    canResetTwoFactor,
    ...options
}: Props) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader title={`Edit ${user.name}`} description={user.email} />
            {canResetTwoFactor && user.two_factor_enabled && (
                <ResetTwoFactor user={user} />
            )}
            <UserForm
                {...options}
                method="put"
                submitUrl={update.url(user.id)}
                submitLabel="Save changes"
                initial={{
                    name: user.name,
                    email: user.email,
                    phone: user.phone ?? '',
                    school_id: user.school_id,
                    is_active: user.is_active,
                    roles: user.role_ids,
                }}
            />
        </div>
    );
}

EditUser.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
