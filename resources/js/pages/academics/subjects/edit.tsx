import SubjectForm from '@/components/academics/subject-form';
import PageHeader from '@/components/page-header';
import { index, update } from '@/routes/academics/subjects';
import type { Subject } from '@/types';

export default function EditSubject({
    subject,
    types,
}: {
    subject: Subject;
    types: { value: string; label: string }[];
}) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader title={`Edit ${subject.name}`} />
            <SubjectForm
                subject={subject}
                types={types}
                action={update.form(subject.id)}
                submitLabel="Save changes"
            />
        </div>
    );
}

EditSubject.layout = {
    breadcrumbs: [{ title: 'Subjects', href: index() }],
};
