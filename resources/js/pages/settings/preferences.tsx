import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import SettingsForm from '@/components/settings/settings-form';
import { edit, update } from '@/routes/preferences';
import type { SettingField } from '@/types';

type Props = {
    groups: {
        key: string;
        label: string;
        description: string | null;
        fields: SettingField[];
    }[];
};

export default function Preferences({ groups }: Props) {
    return (
        <>
            <Head title="Preferences" />
            <h1 className="sr-only">Preferences</h1>

            {groups.map((group) => (
                <div key={group.key} className="space-y-6">
                    <Heading
                        variant="small"
                        title={group.label}
                        description="Personal overrides. Unticked fields follow your school's settings."
                    />
                    <SettingsForm
                        fields={group.fields}
                        submitUrl={update.url(group.key)}
                        allowInherit
                        inheritLabel="Use school setting"
                    />
                </div>
            ))}
        </>
    );
}

Preferences.layout = {
    breadcrumbs: [{ title: 'Preferences', href: edit() }],
};
