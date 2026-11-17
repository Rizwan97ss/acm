import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowRight,
    BookOpen,
    BriefcaseBusiness,
    ClipboardList,
    FileSpreadsheet,
    GraduationCap,
} from 'lucide-react';
import PageHeader from '@/components/page-header';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/imports';

type ImporterSummary = {
    key: string;
    title: string;
    description: string;
};

type Props = {
    importers: ImporterSummary[];
};

const looks: Record<string, { icon: LucideIcon; tile: string }> = {
    students: {
        icon: GraduationCap,
        tile: 'from-navy-700 to-royal-500 shadow-navy-500/25',
    },
    staff: {
        icon: BriefcaseBusiness,
        tile: 'from-emerald-600 to-teal-500 shadow-emerald-500/25',
    },
    admissions: {
        icon: ClipboardList,
        tile: 'from-gold-600 to-crimson-600 shadow-gold-500/25',
    },
    'library-books': {
        icon: BookOpen,
        tile: 'from-sky-600 to-cyan-500 shadow-sky-500/25',
    },
};

const fallback = {
    icon: FileSpreadsheet,
    tile: 'from-navy-700 to-royal-500 shadow-navy-500/25',
};

export default function ImportsIndex({ importers }: Props) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Bulk import"
                description="Add many records at once from an Excel (.xlsx) or CSV file. Every row is checked before anything is saved."
            />

            {importers.length === 0 ? (
                <p className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                    You don’t have permission to import any records.
                </p>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {importers.map((importer) => {
                        const look = looks[importer.key] ?? fallback;
                        const Icon = look.icon;

                        return (
                            <Link
                                key={importer.key}
                                href={show(importer.key)}
                                className="group flex flex-col rounded-2xl border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <span
                                    className={cn(
                                        'mb-4 inline-flex size-11 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-lg',
                                        look.tile,
                                    )}
                                >
                                    <Icon className="size-5" aria-hidden />
                                </span>
                                <h2 className="font-semibold">
                                    {importer.title}
                                </h2>
                                <p className="mt-1 flex-1 text-sm text-muted-foreground">
                                    {importer.description}
                                </p>
                                <span className="mt-4 inline-flex items-center gap-1 text-sm font-medium text-primary">
                                    Start import
                                    <ArrowRight
                                        className="size-4 transition group-hover:translate-x-0.5"
                                        aria-hidden
                                    />
                                </span>
                            </Link>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

ImportsIndex.layout = {
    breadcrumbs: [{ title: 'Bulk import', href: index() }],
};
