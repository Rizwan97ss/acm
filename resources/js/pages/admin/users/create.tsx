import type { UserFormOptions } from '@/components/admin/user-form';
import UserForm from '@/components/admin/user-form';
import PageHeader from '@/components/page-header';
import { create, index, store } from '@/routes/admin/users';

export default function CreateUser(props: UserFormOptions) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="New user"
                description="Create an account and assign its roles."
            />
            <UserForm
                {...props}
                method="post"
                submitUrl={store.url()}
                submitLabel="Create user"
            />
        </div>
    );
}

CreateUser.layout = {
    breadcrumbs: [
        { title: 'Users', href: index() },
        { title: 'New user', href: create() },
    ],
};
