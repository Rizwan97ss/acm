import PageHeader from '@/components/page-header';
import type { AdmissionFormOptions } from '@/components/people/admission-form';
import AdmissionForm from '@/components/people/admission-form';
import { index, update } from '@/routes/people/admissions';
import type { AdmissionApplication } from '@/types';

export default function EditAdmission({
    application,
    ...options
}: AdmissionFormOptions & { application: AdmissionApplication }) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader title={`Edit ${application.application_no}`} />
            <AdmissionForm
                {...options}
                application={application}
                action={update.form(application.id)}
                submitLabel="Save changes"
            />
        </div>
    );
}

EditAdmission.layout = {
    breadcrumbs: [{ title: 'Admissions', href: index() }],
};
