import PageHeader from '@/components/page-header';
import type { EmployeeFormOptions } from '@/components/people/employee-form';
import EmployeeForm from '@/components/people/employee-form';
import { create, index, store } from '@/routes/people/staff';

export default function CreateEmployee(props: EmployeeFormOptions) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader title="New staff member" />
            <EmployeeForm
                {...props}
                action={store.form()}
                submitLabel="Add staff member"
            />
        </div>
    );
}

CreateEmployee.layout = {
    breadcrumbs: [
        { title: 'Staff', href: index() },
        { title: 'New', href: create() },
    ],
};
