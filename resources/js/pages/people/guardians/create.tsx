import PageHeader from '@/components/page-header';
import GuardianForm from '@/components/people/guardian-form';
import { create, index, store } from '@/routes/people/guardians';

export default function CreateGuardian() {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="New guardian"
                description="Link the guardian to students from each student's profile."
            />
            <GuardianForm action={store.form()} submitLabel="Create guardian" />
        </div>
    );
}

CreateGuardian.layout = {
    breadcrumbs: [
        { title: 'Guardians', href: index() },
        { title: 'New', href: create() },
    ],
};
