import AcademicYearForm from '@/components/academics/academic-year-form';
import PageHeader from '@/components/page-header';
import { index, update } from '@/routes/academics/academic-years';
import type { AcademicYear } from '@/types';

export default function EditAcademicYear({ year }: { year: AcademicYear }) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader title={`Edit ${year.name}`} />
            <AcademicYearForm
                method="put"
                submitUrl={update.url(year.id)}
                submitLabel="Save changes"
                initial={{
                    name: year.name,
                    starts_on: year.starts_on,
                    ends_on: year.ends_on,
                    terms: year.terms ?? [],
                }}
            />
        </div>
    );
}

EditAcademicYear.layout = {
    breadcrumbs: [{ title: 'Academic years', href: index() }],
};
