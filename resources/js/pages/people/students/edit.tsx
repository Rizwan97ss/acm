import type { StudentFormOptions } from '@/components/people/student-form';
import StudentForm from '@/components/people/student-form';
import PageHeader from '@/components/page-header';
import { index, update } from '@/routes/people/students';
import type { Student } from '@/types';

export default function EditStudent({
    student,
    ...options
}: StudentFormOptions & { student: Student }) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={`Edit ${student.full_name}`}
                description={student.admission_no}
            />
            <StudentForm
                {...options}
                student={student}
                action={update.form(student.id)}
                submitLabel="Save changes"
            />
        </div>
    );
}

EditStudent.layout = {
    breadcrumbs: [{ title: 'Students', href: index() }],
};
