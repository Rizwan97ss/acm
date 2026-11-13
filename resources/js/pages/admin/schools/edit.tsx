import SchoolForm from '@/components/admin/school-form';
import PageHeader from '@/components/page-header';
import { index, update } from '@/routes/admin/schools';
import type { Option, School } from '@/types';

export default function EditSchool({
    school,
    parents,
}: {
    school: School;
    parents: Option[];
}) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={`Edit ${school.name}`}
                description={school.code}
            />
            <SchoolForm
                school={school}
                parents={parents}
                action={update.form(school.id)}
                submitLabel="Save changes"
            />
        </div>
    );
}

EditSchool.layout = {
    breadcrumbs: [{ title: 'Schools', href: index() }],
};
