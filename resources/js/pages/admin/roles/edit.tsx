import type { RoleFormOptions } from '@/components/admin/role-form';
import RoleForm from '@/components/admin/role-form';
import PageHeader from '@/components/page-header';
import { index, update } from '@/routes/admin/roles';
import type { Role } from '@/types';

type Props = RoleFormOptions & {
    role: Role & { permissions: string[] };
};

export default function EditRole({ role, ...options }: Props) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={`Edit ${role.name}`}
                description={
                    role.is_system
                        ? 'System role — changes apply to every school.'
                        : role.school?.name
                }
            />
            <RoleForm
                {...options}
                method="put"
                submitUrl={update.url(role.id)}
                submitLabel="Save changes"
                initial={{
                    name: role.name,
                    description: role.description ?? '',
                    parent_id: role.parent_id,
                    permissions: role.permissions,
                }}
            />
        </div>
    );
}

EditRole.layout = {
    breadcrumbs: [{ title: 'Roles & permissions', href: index() }],
};
