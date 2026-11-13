import SchoolForm from '@/components/admin/school-form';
import PageHeader from '@/components/page-header';
import { create, index, store } from '@/routes/admin/schools';
import type { Option } from '@/types';

export default function CreateSchool({ parents }: { parents: Option[] }) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="New school"
                description="Add an independent school or a branch campus."
            />
            <SchoolForm
                parents={parents}
                action={store.form()}
                submitLabel="Create school"
            />
        </div>
    );
}

CreateSchool.layout = {
    breadcrumbs: [
        { title: 'Schools', href: index() },
        { title: 'New school', href: create() },
    ],
};
