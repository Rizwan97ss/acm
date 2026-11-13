import type { ClassFormOptions } from '@/components/academics/class-form';
import ClassForm from '@/components/academics/class-form';
import PageHeader from '@/components/page-header';
import { create, index, store } from '@/routes/academics/classes';

export default function CreateClass(props: ClassFormOptions) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="New class"
                description="Add a grade level with its sections and subjects."
            />
            <ClassForm
                {...props}
                method="post"
                submitUrl={store.url()}
                submitLabel="Create class"
            />
        </div>
    );
}

CreateClass.layout = {
    breadcrumbs: [
        { title: 'Classes & sections', href: index() },
        { title: 'New', href: create() },
    ],
};
