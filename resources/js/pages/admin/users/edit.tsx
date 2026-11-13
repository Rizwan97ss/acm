import type { UserFormOptions } from '@/components/admin/user-form';
import UserForm from '@/components/admin/user-form';
import PageHeader from '@/components/page-header';
import { index, update } from '@/routes/admin/users';
import type { ManagedUser } from '@/types';

type Props = UserFormOptions & {
    user: ManagedUser & { school_id: number | null; role_ids: number[] };
};

export default function EditUser({ user, ...options }: Props) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader title={`Edit ${user.name}`} description={user.email} />
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
