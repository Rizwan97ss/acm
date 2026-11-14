import type { StudentFormOptions } from '@/components/people/student-form';
import StudentForm from '@/components/people/student-form';
import PageHeader from '@/components/page-header';
import { create, index, store } from '@/routes/people/students';

export default function CreateStudent(props: StudentFormOptions) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="New student"
                description="Add guardians and a portal account from the student's profile afterwards."
            />
            <StudentForm
                {...props}
                action={store.form()}
                submitLabel="Create student"
            />
        </div>
    );
}

CreateStudent.layout = {
    breadcrumbs: [
        { title: 'Students', href: index() },
        { title: 'New', href: create() },
    ],
};
