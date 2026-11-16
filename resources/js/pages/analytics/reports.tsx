import { Download, FileSpreadsheet } from 'lucide-react';
import { useState } from 'react';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { exportMethod, index } from '@/routes/reports';
import type { ExportableReport } from '@/types/analytics';

type Props = {
    reports: ExportableReport[];
    defaults: { from: string; to: string };
};

const tones = [
    'from-royal-500 to-navy-500',
    'from-emerald-500 to-teal-500',
    'from-sky-500 to-cyan-500',
    'from-orange-500 to-rose-500',
    'from-gold-500 to-crimson-500',
];

export default function Reports({ reports, defaults }: Props) {
    const [range, setRange] = useState(defaults);
    const invalid = range.from > range.to;

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Reports & exports"
                description="Download CSV files for spreadsheets. Every export is recorded in the audit log, and sensitive fields such as national IDs are never included."
            />
            <Card>
                <CardHeader>
                    <CardTitle>Date range</CardTitle>
                    <CardDescription>
                        Used by dated reports. Up to one year per file.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div className="grid gap-1.5">
                        <Label htmlFor="from">From</Label>
                        <Input
                            id="from"
                            type="date"
                            value={range.from}
                            onChange={(e) =>
                                setRange({ ...range, from: e.target.value })
                            }
                            className="sm:w-44"
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="to">To</Label>
                        <Input
                            id="to"
                            type="date"
                            value={range.to}
                            onChange={(e) =>
                                setRange({ ...range, to: e.target.value })
                            }
                            className="sm:w-44"
                        />
                    </div>
                    {invalid && (
                        <p className="text-sm text-destructive" role="alert">
                            “From” must be on or before “To”.
                        </p>
                    )}
                </CardContent>
            </Card>
            {reports.length === 0 ? (
                <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                    You don't have access to any exports.
                </p>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {reports.map((report, i) => (
                        <Card key={report.key} className="overflow-hidden">
                            <div
                                className={`h-1.5 bg-gradient-to-r ${tones[i % tones.length]}`}
                                aria-hidden
                            />
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileSpreadsheet
                                        className="size-4 text-primary"
                                        aria-hidden
                                    />
                                    {report.label}
                                </CardTitle>
                                <CardDescription>
                                    {report.description}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button
                                    variant="outline"
                                    asChild
                                    disabled={report.dated && invalid}
                                >
                                    <a
                                        href={exportMethod.url(report.key, {
                                            query: report.dated ? range : {},
                                        })}
                                        aria-disabled={report.dated && invalid}
                                        onClick={(e) =>
                                            report.dated &&
                                            invalid &&
                                            e.preventDefault()
                                        }
                                    >
                                        <Download aria-hidden /> Download CSV
                                    </a>
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}
        </div>
    );
}

Reports.layout = {
    breadcrumbs: [{ title: 'Reports & exports', href: index() }],
};
