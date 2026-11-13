import type { RoleFormOptions } from '@/components/admin/role-form';
import RoleForm from '@/components/admin/role-form';
import PageHeader from '@/components/page-header';
import { create, index, store } from '@/routes/admin/roles';

export default function CreateRole(props: RoleFormOptions) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="New role"
                description="Choose exactly what members of this role can see and do."
            />
            <RoleForm
                {...props}
                method="post"
                submitUrl={store.url()}
                submitLabel="Create role"
            />
        </div>
    );
}

CreateRole.layout = {
    breadcrumbs: [
        { title: 'Roles & permissions', href: index() },
        { title: 'New role', href: create() },
    ],
};
