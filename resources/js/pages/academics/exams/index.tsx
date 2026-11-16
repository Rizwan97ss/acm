import { Link } from '@inertiajs/react';
import { FileText, Plus } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useFormat } from '@/hooks/use-format';
import { create, index, show } from '@/routes/academics/exams';
import type { ExamSummary } from '@/types';

export default function ExamsIndex({
    exams,
    can,
}: {
    exams: ExamSummary[];
    can: { create: boolean };
}) {
    const format = useFormat();
    const day = (value: string): string => format.date(`${value}T12:00:00Z`);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Exams & results"
                description="Set up exams, enter marks and publish results to students and parents."
                actions={
                    can.create && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus aria-hidden /> New exam
                            </Link>
                        </Button>
                    )
                }
            />
            {exams.length === 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>No exams yet</CardTitle>
                        <CardDescription>
                            Create an exam, then add its papers for each class.
                        </CardDescription>
                    </CardHeader>
                </Card>
            )}
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {exams.map((exam, i) => (
                    <Link
                        key={exam.id}
                        href={show(exam.id)}
                        className="group overflow-hidden rounded-2xl border bg-card shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
                    >
                        <div
                            className={`h-2 bg-gradient-to-r ${['from-navy-500 to-crimson-500', 'from-emerald-500 to-sky-500', 'from-orange-500 to-gold-500'][i % 3]}`}
                            aria-hidden
                        />
                        <div className="space-y-3 p-5">
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <p className="font-semibold group-hover:underline">
                                        {exam.name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {exam.academic_year}
                                        {exam.term ? ` · ${exam.term}` : ''}
                                    </p>
                                </div>
                                <FileText
                                    className="size-5 text-navy-500"
                                    aria-hidden
                                />
                            </div>
                            <p className="text-sm">
                                {day(exam.starts_on)} – {day(exam.ends_on)}
                            </p>
                            <div className="flex items-center justify-between">
                                <span className="text-xs text-muted-foreground">
                                    {exam.papers_count} papers
                                </span>
                                {exam.published_at ? (
                                    <TonePill tone="success">
                                        Published
                                    </TonePill>
                                ) : (
                                    <TonePill tone="warning">Draft</TonePill>
                                )}
                            </div>
                        </div>
                    </Link>
                ))}
            </div>
        </div>
    );
}

ExamsIndex.layout = {
    breadcrumbs: [{ title: 'Exams & results', href: index() }],
};
