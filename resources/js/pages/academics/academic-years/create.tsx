import AcademicYearForm from '@/components/academics/academic-year-form';
import PageHeader from '@/components/page-header';
import { create, index, store } from '@/routes/academics/academic-years';

export default function CreateAcademicYear() {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="New academic year"
                description="Define the year and its terms."
            />
            <AcademicYearForm
                method="post"
                submitUrl={store.url()}
                submitLabel="Create year"
            />
        </div>
    );
}

CreateAcademicYear.layout = {
    breadcrumbs: [
        { title: 'Academic years', href: index() },
        { title: 'New', href: create() },
    ],
};
