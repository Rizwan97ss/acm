import type { ClassFormOptions } from '@/components/academics/class-form';
import ClassForm from '@/components/academics/class-form';
import PageHeader from '@/components/page-header';
import { index, update } from '@/routes/academics/classes';
import type { SchoolClass } from '@/types';

type Props = ClassFormOptions & { schoolClass: SchoolClass };

export default function EditClass({ schoolClass, ...options }: Props) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader title={`Edit ${schoolClass.name}`} />
            <ClassForm
                {...options}
                method="put"
                submitUrl={update.url(schoolClass.id)}
                submitLabel="Save changes"
                initial={{
                    name: schoolClass.name,
                    level: schoolClass.level,
                    description: schoolClass.description ?? '',
                    sections: (schoolClass.sections ?? []).map((section) => ({
                        id: section.id,
                        name: section.name,
                        capacity: section.capacity,
                        room: section.room,
                        class_teacher_id: section.class_teacher_id,
                    })),
                    subjects: (schoolClass.subjects ?? []).map((subject) => ({
                        id: subject.id,
                        periods_per_week: subject.periods_per_week,
                    })),
                }}
            />
        </div>
    );
}

EditClass.layout = {
    breadcrumbs: [{ title: 'Classes & sections', href: index() }],
};
