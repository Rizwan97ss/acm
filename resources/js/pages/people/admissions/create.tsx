import PageHeader from '@/components/page-header';
import type { AdmissionFormOptions } from '@/components/people/admission-form';
import AdmissionForm from '@/components/people/admission-form';
import { create, index, store } from '@/routes/people/admissions';

export default function CreateAdmission(props: AdmissionFormOptions) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="New application"
                description="Record an admission enquiry for review."
            />
            <AdmissionForm
                {...props}
                action={store.form()}
                submitLabel="Record application"
            />
        </div>
    );
}

CreateAdmission.layout = {
    breadcrumbs: [
        { title: 'Admissions', href: index() },
        { title: 'New', href: create() },
    ],
};
