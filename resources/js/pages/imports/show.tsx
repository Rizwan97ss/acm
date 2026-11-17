import { Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Download,
    FileSpreadsheet,
    Upload,
    X,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import {
    discard,
    index,
    preview as previewRoute,
    store,
    template,
} from '@/routes/imports';

type Column = {
    key: string;
    label: string;
    required: boolean;
    format: string;
    example: string;
};

type Preview = {
    token: string;
    file_name: string;
    total: number;
    valid: number;
    invalid: number;
    errors: { line: number; messages: string[] }[];
    errors_truncated: boolean;
    sample: { line: number; values: Record<string, string | null> }[];
};

type Props = {
    importer: {
        key: string;
        title: string;
        description: string;
        columns: Column[];
    };
    preview: Preview | null;
};

function Step({
    number,
    title,
    done,
    children,
}: {
    number: number;
    title: string;
    done?: boolean;
    children: ReactNode;
}) {
    return (
        <section className="rounded-2xl border bg-card p-5 shadow-sm">
            <div className="mb-3 flex items-center gap-3">
                <span
                    className={cn(
                        'flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold',
                        done
                            ? 'bg-emerald-600 text-white'
                            : 'bg-gradient-to-br from-navy-700 to-royal-500 text-white',
                    )}
                    aria-hidden
                >
                    {done ? <CheckCircle2 className="size-4" /> : number}
                </span>
                <h2 className="font-semibold">{title}</h2>
            </div>
            {children}
        </section>
    );
}

function ReviewStep({
    importerKey,
    preview,
    columns,
}: {
    importerKey: string;
    preview: Preview;
    columns: Column[];
}) {
    const [processing, setProcessing] = useState(false);
    const shown = columns.filter((column) =>
        preview.sample.some((row) => column.key in row.values),
    );
    const ready = preview.invalid === 0;

    const runImport = (): void => {
        router.post(
            store.url(importerKey),
            { token: preview.token },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Step number={3} title="Review and import">
            <div className="flex flex-wrap items-center gap-2 text-sm">
                <span className="inline-flex items-center gap-1.5 font-medium">
                    <FileSpreadsheet className="size-4" aria-hidden />
                    {preview.file_name}
                </span>
                <TonePill tone="info">{preview.total} rows</TonePill>
                <TonePill tone="success">{preview.valid} ready</TonePill>
                {preview.invalid > 0 && (
                    <TonePill tone="danger">
                        {preview.invalid} with problems
                    </TonePill>
                )}
            </div>

            {ready ? (
                <p className="mt-4 flex items-start gap-2 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-200">
                    <CheckCircle2
                        className="mt-0.5 size-4 shrink-0"
                        aria-hidden
                    />
                    Every row passed the checks. Importing saves all{' '}
                    {preview.total} records together; if anything fails while
                    saving, nothing is saved.
                </p>
            ) : (
                <div className="mt-4 space-y-3">
                    <p className="flex items-start gap-2 rounded-lg bg-rose-50 p-3 text-sm text-rose-900 dark:bg-rose-500/10 dark:text-rose-200">
                        <AlertTriangle
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden
                        />
                        Fix these rows in your file, save it, and upload it
                        again. Nothing has been saved yet.
                    </p>
                    <div className="max-h-96 overflow-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <caption className="sr-only">
                                Rows with problems
                            </caption>
                            <thead className="sticky top-0 bg-muted text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th scope="col" className="w-20 px-3 py-2">
                                        Row
                                    </th>
                                    <th scope="col" className="px-3 py-2">
                                        Problem
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {preview.errors.map((error) => (
                                    <tr key={error.line}>
                                        <td className="px-3 py-2 align-top font-mono tabular-nums">
                                            {error.line}
                                        </td>
                                        <td className="px-3 py-2">
                                            <ul className="list-disc space-y-0.5 pl-4">
                                                {error.messages.map(
                                                    (message) => (
                                                        <li key={message}>
                                                            {message}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {preview.errors_truncated && (
                        <p className="text-xs text-muted-foreground">
                            Only the first {preview.errors.length} problem rows
                            are listed.
                        </p>
                    )}
                </div>
            )}

            {shown.length > 0 && (
                <div className="mt-4">
                    <p className="mb-2 text-xs font-medium text-muted-foreground uppercase">
                        First rows in the file
                    </p>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <caption className="sr-only">
                                First rows of the uploaded file
                            </caption>
                            <thead className="bg-muted text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th scope="col" className="px-3 py-2">
                                        Row
                                    </th>
                                    {shown.map((column) => (
                                        <th
                                            key={column.key}
                                            scope="col"
                                            className="px-3 py-2 whitespace-nowrap"
                                        >
                                            {column.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {preview.sample.map((row) => (
                                    <tr key={row.line}>
                                        <td className="px-3 py-2 font-mono text-muted-foreground tabular-nums">
                                            {row.line}
                                        </td>
                                        {shown.map((column) => (
                                            <td
                                                key={column.key}
                                                className="px-3 py-2 whitespace-nowrap"
                                            >
                                                {row.values[column.key] ?? (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            <div className="mt-5 flex flex-wrap gap-2">
                {ready && (
                    <Button onClick={runImport} disabled={processing}>
                        {processing ? <Spinner /> : <Upload aria-hidden />}
                        Import {preview.total} records
                    </Button>
                )}
                <Button
                    variant="outline"
                    disabled={processing}
                    onClick={() =>
                        router.visit(discard(importerKey), {
                            preserveScroll: true,
                        })
                    }
                >
                    <X aria-hidden />
                    Discard this file
                </Button>
            </div>
        </Step>
    );
}

export default function ImportShow({ importer, preview }: Props) {
    const { errors } = usePage().props;
    const form = useForm<{ file: File | null }>({ file: null });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.post(previewRoute.url(importer.key), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const fileError = form.errors.file ?? errors.file;

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={`Import ${importer.title.toLowerCase()}`}
                description={importer.description}
                actions={
                    <Button asChild variant="outline">
                        <Link href={index()}>
                            <ArrowLeft aria-hidden />
                            All imports
                        </Link>
                    </Button>
                }
            />

            <div className="grid gap-4 lg:grid-cols-2">
                <Step number={1} title="Download the template">
                    <p className="text-sm text-muted-foreground">
                        Fill one row per record on the “Data” sheet. Columns
                        marked * are required. The “Instructions” sheet explains
                        every column.
                    </p>
                    <Button asChild variant="outline" className="mt-4">
                        <a href={template.url(importer.key)} download>
                            <Download aria-hidden />
                            Download Excel template
                        </a>
                    </Button>
                </Step>

                <Step number={2} title="Upload your file" done={!!preview}>
                    <form onSubmit={submit} className="space-y-3">
                        <div className="grid gap-2">
                            <Label htmlFor="import-file">
                                Excel (.xlsx) or CSV file, up to 5 MB
                            </Label>
                            <Input
                                id="import-file"
                                type="file"
                                accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
                                aria-invalid={!!fileError}
                                onChange={(event) =>
                                    form.setData(
                                        'file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <InputError message={fileError} />
                        </div>
                        <Button
                            type="submit"
                            disabled={!form.data.file || form.processing}
                        >
                            {form.processing ? (
                                <Spinner />
                            ) : (
                                <Upload aria-hidden />
                            )}
                            Check file
                        </Button>
                    </form>
                </Step>
            </div>

            {preview && (
                <ReviewStep
                    importerKey={importer.key}
                    preview={preview}
                    columns={importer.columns}
                />
            )}

            <section className="rounded-2xl border bg-card shadow-sm">
                <h2 className="border-b px-5 py-3 font-semibold">Columns</h2>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <caption className="sr-only">
                            Columns accepted in the file
                        </caption>
                        <thead className="bg-gradient-to-r from-navy-50 via-royal-50 to-gold-50 text-left text-xs text-navy-950/70 uppercase dark:from-navy-500/10 dark:via-royal-500/10 dark:to-gold-500/10 dark:text-navy-100/80">
                            <tr>
                                <th scope="col" className="px-4 py-2">
                                    Column
                                </th>
                                <th scope="col" className="px-4 py-2">
                                    What to enter
                                </th>
                                <th scope="col" className="px-4 py-2">
                                    Example
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {importer.columns.map((column) => (
                                <tr key={column.key}>
                                    <td className="px-4 py-2 font-medium whitespace-nowrap">
                                        {column.label}
                                        {column.required && (
                                            <span
                                                className="ml-1 text-rose-600 dark:text-rose-400"
                                                title="Required"
                                            >
                                                *
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-2 text-muted-foreground">
                                        {column.format || '—'}
                                    </td>
                                    <td className="px-4 py-2 font-mono text-xs whitespace-nowrap">
                                        {column.example || '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    );
}

ImportShow.layout = {
    breadcrumbs: [{ title: 'Bulk import', href: index() }],
};
