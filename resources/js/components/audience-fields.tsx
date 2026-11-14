import FormField from '@/components/form-field';
import { NativeSelect } from '@/components/ui/native-select';
import type { Option } from '@/types';
import type { EnumOption } from '@/types/people';

type AudienceFieldsProps = {
    idPrefix: string;
    audiences: EnumOption[];
    classes: Option[];
    audience: string;
    classId: number | '';
    errors: { audience?: string; school_class_id?: string };
    onAudienceChange: (audience: string) => void;
    onClassChange: (classId: number | '') => void;
};

/**
 * Audience picker shared by announcements and events. A class can only be chosen
 * for students or guardians, matching the server-side rule.
 */
export default function AudienceFields({
    idPrefix,
    audiences,
    classes,
    audience,
    classId,
    errors,
    onAudienceChange,
    onClassChange,
}: AudienceFieldsProps) {
    const classAllowed = audience === 'students' || audience === 'guardians';

    return (
        <>
            <FormField
                id={`${idPrefix}-audience`}
                label="Audience"
                error={errors.audience}
                required
            >
                <NativeSelect
                    id={`${idPrefix}-audience`}
                    value={audience}
                    onChange={(e) => {
                        onAudienceChange(e.target.value);

                        if (
                            e.target.value !== 'students' &&
                            e.target.value !== 'guardians'
                        ) {
                            onClassChange('');
                        }
                    }}
                >
                    {audiences.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            <FormField
                id={`${idPrefix}-class`}
                label="Class"
                error={errors.school_class_id}
                description={
                    classAllowed ? undefined : 'Only for students or guardians.'
                }
            >
                <NativeSelect
                    id={`${idPrefix}-class`}
                    value={classId}
                    disabled={!classAllowed}
                    onChange={(e) =>
                        onClassChange(
                            e.target.value === '' ? '' : Number(e.target.value),
                        )
                    }
                >
                    <option value="">Every class</option>
                    {classes.map((schoolClass) => (
                        <option key={schoolClass.id} value={schoolClass.id}>
                            {schoolClass.name}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
        </>
    );
}
