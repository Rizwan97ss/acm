import { Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import ConfirmDelete from '@/components/confirm-delete';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { create, destroy, edit, index } from '@/routes/academics/grade-scales';
import type { GradeBandData } from '@/types';

type Scale = {
    id: number;
    name: string;
    is_default: boolean;
    bands: GradeBandData[];
    can: { update: boolean; delete: boolean };
};

const bandColors = [
    'from-emerald-500 to-teal-500',
    'from-sky-500 to-cyan-500',
    'from-indigo-500 to-violet-500',
    'from-violet-500 to-fuchsia-500',
    'from-amber-500 to-orange-500',
    'from-orange-500 to-rose-500',
];

export default function GradeScalesIndex({
    scales,
    can,
}: {
    scales: Scale[];
    can: { create: boolean };
}) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Grading scales"
                description="How percentages become grades on results and report cards."
                actions={
                    can.create && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus aria-hidden /> New scale
                            </Link>
                        </Button>
                    )
                }
            />
            <div className="grid gap-4 lg:grid-cols-2">
                {scales.map((scale) => (
                    <Card key={scale.id}>
                        <CardHeader className="flex flex-row items-center justify-between gap-2">
                            <CardTitle className="flex items-center gap-2">
                                {scale.name}
                                {scale.is_default && (
                                    <TonePill tone="info">Default</TonePill>
                                )}
                            </CardTitle>
                            <div className="flex">
                                {scale.can.update && (
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link
                                            href={edit(scale.id)}
                                            aria-label={`Edit ${scale.name}`}
                                        >
                                            <Pencil aria-hidden />
                                        </Link>
                                    </Button>
                                )}
                                {scale.can.delete && (
                                    <ConfirmDelete
                                        action={destroy(scale.id)}
                                        title={`Delete ${scale.name}?`}
                                        description="Scales used by an exam cannot be deleted."
                                    />
                                )}
                            </div>
                        </CardHeader>
                        <CardContent>
                            <ul className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                {scale.bands.map((band, index) => (
                                    <li
                                        key={band.grade}
                                        className="overflow-hidden rounded-xl border"
                                    >
                                        <div
                                            className={`bg-gradient-to-br px-3 py-2 text-white ${band.is_passing ? bandColors[index % bandColors.length] : 'from-rose-600 to-red-600'}`}
                                        >
                                            <p className="text-xl font-bold">
                                                {band.grade}
                                            </p>
                                            <p className="text-xs text-white/85">
                                                from {band.min_percentage}%
                                            </p>
                                        </div>
                                        <p className="truncate px-3 py-1.5 text-xs text-muted-foreground">
                                            {band.remark ?? '—'}
                                            {band.grade_point !== null &&
                                                ` · ${band.grade_point.toFixed(1)}`}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </div>
    );
}

GradeScalesIndex.layout = {
    breadcrumbs: [{ title: 'Grading scales', href: index() }],
};
