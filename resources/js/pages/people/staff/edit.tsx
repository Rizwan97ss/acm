import PageHeader from '@/components/page-header';
import type { EmployeeFormOptions } from '@/components/people/employee-form';
import EmployeeForm from '@/components/people/employee-form';
import IdentityPanel from '@/components/people/identity-panel';
import { index, update } from '@/routes/people/staff';
import type { Employee } from '@/types';

export default function EditEmployee({
    employee,
    ...options
}: EmployeeFormOptions & { employee: Employee }) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={`Edit ${employee.full_name}`}
                description={employee.employee_no}
            />
            <IdentityPanel
                type="staff"
                id={employee.id}
                name={employee.full_name}
                photoUrl={employee.photo_url}
                canEdit
            />
            <EmployeeForm
                {...options}
                employee={employee}
                action={update.form(employee.id)}
                submitLabel="Save changes"
            />
        </div>
    );
}

EditEmployee.layout = {
    breadcrumbs: [{ title: 'Staff', href: index() }],
};
