import { Link } from '@inertiajs/react';
import { Info } from 'lucide-react';
import PageHeader from '@/components/page-header';
import SettingsForm from '@/components/settings/settings-form';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { edit, index, update } from '@/routes/admin/settings';
import type { SettingField } from '@/types';

type Props = {
    groups: { key: string; label: string }[];
    group: { key: string; label: string; description: string | null };
    scope: 'global' | 'school';
    scopeLabel: string;
    fields: SettingField[];
    canEdit: boolean;
};

export default function SettingsEdit({
    groups,
    group,
    scope,
    scopeLabel,
    fields,
    canEdit,
}: Props) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Settings"
                description={`Configuring ${scopeLabel}.`}
            />

            <div className="flex flex-col gap-6 lg:flex-row">
                <nav
                    aria-label="Settings sections"
                    className="flex gap-1 overflow-x-auto lg:w-52 lg:flex-col"
                >
                    {groups.map((item) => (
                        <Link
                            key={item.key}
                            href={edit(item.key)}
                            aria-current={
                                item.key === group.key ? 'page' : undefined
                            }
                            className={cn(
                                'rounded-md px-3 py-2 text-sm whitespace-nowrap transition-colors hover:bg-muted',
                                item.key === group.key &&
                                    'bg-muted font-medium',
                            )}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>

                <Card className="flex-1 lg:max-w-3xl">
                    <CardHeader>
                        <CardTitle>{group.label}</CardTitle>
                        {group.description && (
                            <CardDescription>
                                {group.description}
                            </CardDescription>
                        )}
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {scope === 'school' && (
                            <p className="flex gap-2 rounded-md bg-muted/60 p-3 text-xs text-muted-foreground">
                                <Info className="size-4 shrink-0" aria-hidden />
                                Values marked “Use platform default” follow the
                                platform-wide setting until you override them
                                for this school.
                            </p>
                        )}
                        <SettingsForm
                            key={group.key}
                            fields={fields}
                            submitUrl={update.url(group.key)}
                            allowInherit={scope === 'school'}
                            inheritLabel="Use platform default"
                            disabled={!canEdit}
                        />
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

SettingsEdit.layout = {
    breadcrumbs: [{ title: 'Settings', href: index() }],
};
