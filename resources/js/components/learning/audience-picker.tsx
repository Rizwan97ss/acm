import FormField from '@/components/form-field';
import { NativeSelect } from '@/components/ui/native-select';
import type { TeachingOption } from '@/types/learning';
import StudentChooser from './student-chooser';

export type AudienceValue = {
    school_class_id: number | '';
    section_id: number | '';
    subject_id: number | '';
    student_ids?: number[];
};

/**
 * Class → optional section → subject, limited to what the teacher may teach, with an
 * optional "only these students" chooser.
 */
export default function AudiencePicker({
    options,
    value,
    onChange,
    errors,
    allowStudents = false,
    rosterUrl,
}: {
    options: TeachingOption[];
    value: AudienceValue;
    onChange: (next: AudienceValue) => void;
    errors: Partial<Record<string, string>>;
    allowStudents?: boolean;
    rosterUrl?: (query: Record<string, string>) => string;
}) {
    const selectedClass = options.find(
        (option) => option.id === value.school_class_id,
    );
    if (options.length === 0) {
        return (
            <p className="rounded-xl border border-dashed p-4 text-sm text-muted-foreground sm:col-span-2">
                You are not timetabled to teach any class yet. Ask an
                administrator to add you to the timetable or make you a class
                teacher.
            </p>
        );
    }

    return (
        <>
            <FormField
                id="audience-class"
                label="Class"
                required
                error={errors.school_class_id}
            >
                <NativeSelect
                    id="audience-class"
                    value={value.school_class_id}
                    onChange={(event) =>
                        onChange({
                            ...value,
                            school_class_id:
                                event.target.value === ''
                                    ? ''
                                    : Number(event.target.value),
                            section_id: '',
                            subject_id: '',
                            student_ids: [],
                        })
                    }
                >
                    <option value="">Choose…</option>
                    {options.map((option) => (
                        <option key={option.id} value={option.id}>
                            {option.name}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <FormField
                id="audience-section"
                label="Section"
                error={errors.section_id}
                description="Leave on “All sections” to share with the whole class."
            >
                <NativeSelect
                    id="audience-section"
                    value={value.section_id}
                    disabled={!selectedClass}
                    onChange={(event) =>
                        onChange({
                            ...value,
                            section_id:
                                event.target.value === ''
                                    ? ''
                                    : Number(event.target.value),
                            student_ids: [],
                        })
                    }
                >
                    <option value="">All sections</option>
                    {selectedClass?.sections.map((section) => (
                        <option key={section.id} value={section.id}>
                            {section.name}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <FormField
                id="audience-subject"
                label="Subject"
                required
                error={errors.subject_id}
            >
                <NativeSelect
                    id="audience-subject"
                    value={value.subject_id}
                    disabled={!selectedClass}
                    onChange={(event) =>
                        onChange({
                            ...value,
                            subject_id:
                                event.target.value === ''
                                    ? ''
                                    : Number(event.target.value),
                        })
                    }
                >
                    <option value="">Choose…</option>
                    {selectedClass?.subjects.map((subject) => (
                        <option key={subject.id} value={subject.id}>
                            {subject.name}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>

            {allowStudents && (
                <div className="sm:col-span-2">
                    <StudentChooser
                        classId={value.school_class_id}
                        sectionId={value.section_id}
                        value={value.student_ids ?? []}
                        onChange={(ids) =>
                            onChange({ ...value, student_ids: ids })
                        }
                        error={errors.student_ids}
                        rosterUrl={rosterUrl}
                    />
                </div>
            )}
        </>
    );
}
