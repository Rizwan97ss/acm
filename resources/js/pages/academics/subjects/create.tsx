import SubjectForm from '@/components/academics/subject-form';
import PageHeader from '@/components/page-header';
import { create, index, store } from '@/routes/academics/subjects';

export default function CreateSubject({
    types,
}: {
    types: { value: string; label: string }[];
}) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader title="New subject" />
            <SubjectForm
                types={types}
                action={store.form()}
                submitLabel="Create subject"
            />
        </div>
    );
}

CreateSubject.layout = {
    breadcrumbs: [
        { title: 'Subjects', href: index() },
        { title: 'New', href: create() },
    ],
};
