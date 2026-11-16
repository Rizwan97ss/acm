import { router, useForm } from '@inertiajs/react';
import { Download, FileText, Upload } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import AudienceFields from '@/components/audience-fields';
import ConfirmDelete from '@/components/confirm-delete';
import Pagination from '@/components/data/pagination';
import SearchInput from '@/components/data/search-input';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useFormat } from '@/hooks/use-format';
import { destroy, download, index, store } from '@/routes/records/documents';
import type { Option } from '@/types';
import type { PaginatedList } from '@/types/communication';
import type { EnumOption } from '@/types/people';

type DocumentItem = {
    id: number;
    title: string;
    category: string | null;
    original_name: string;
    mime_type: string;
    size: number;
    audience_label: string;
    school_class: string | null;
    uploaded_at: string | null;
};

type Props = {
    documents: PaginatedList<DocumentItem>;
    filters: { search?: string; category?: string };
    categories: string[];
    audiences: EnumOption[];
    classes: Option[];
    allowed: string[];
    can: { create: boolean; delete: boolean };
};

const size = (bytes: number): string =>
    bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / 1024 / 1024).toFixed(1)} MB`;

function UploadDialog({
    audiences,
    classes,
    categories,
    allowed,
    onClose,
}: Pick<Props, 'audiences' | 'classes' | 'categories' | 'allowed'> & {
    onClose: () => void;
}) {
    const form = useForm({
        title: '',
        category: '',
        audience: 'everyone',
        school_class_id: '' as number | '',
        file: null as File | null,
    });

    const submit = (e: FormEvent): void => {
        e.preventDefault();
        form.post(store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle>Upload a document</DialogTitle>
                <DialogDescription>
                    Up to 10 MB. Allowed: {allowed.join(', ')}.
                </DialogDescription>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="doc-title"
                        label="Title"
                        error={form.errors.title}
                        required
                        className="sm:col-span-2"
                    >
                        <Input
                            id="doc-title"
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="doc-file"
                        label="File"
                        error={form.errors.file}
                        required
                        className="sm:col-span-2"
                    >
                        <Input
                            id="doc-file"
                            type="file"
                            accept={allowed.map((ext) => `.${ext}`).join(',')}
                            onChange={(e) =>
                                form.setData(
                                    'file',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="doc-category"
                        label="Category"
                        error={form.errors.category}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="doc-category"
                            list="doc-categories"
                            value={form.data.category}
                            onChange={(e) =>
                                form.setData('category', e.target.value)
                            }
                            placeholder="e.g. Policies"
                        />
                        <datalist id="doc-categories">
                            {categories.map((category) => (
                                <option key={category} value={category} />
                            ))}
                        </datalist>
                    </FormField>
                    <AudienceFields
                        idPrefix="doc"
                        audiences={audiences}
                        classes={classes}
                        audience={form.data.audience}
                        classId={form.data.school_class_id}
                        errors={form.errors}
                        onAudienceChange={(value) =>
                            form.setData('audience', value)
                        }
                        onClassChange={(value) =>
                            form.setData('school_class_id', value)
                        }
                    />
                    {form.progress && (
                        <progress
                            className="w-full sm:col-span-2"
                            value={form.progress.percentage}
                            max={100}
                        />
                    )}
                    <div className="flex justify-end sm:col-span-2">
                        <Button type="submit" disabled={form.processing}>
                            <Upload aria-hidden /> Upload
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Documents({
    documents,
    filters,
    categories,
    audiences,
    classes,
    allowed,
    can,
}: Props) {
    const format = useFormat();
    const [search, setSearch] = useState(filters.search ?? '');
    const [uploading, setUploading] = useState(false);

    useEffect(() => {
        if (search === (filters.search ?? '')) {
            return;
        }

        const timeout = setTimeout(
            () =>
                router.get(
                    index.url(),
                    { ...filters, search: search || undefined },
                    { preserveState: true, replace: true },
                ),
            300,
        );

        return () => clearTimeout(timeout);
    }, [search, filters]);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Documents"
                description="Policies, forms and handouts shared with the school community."
                actions={
                    can.create ? (
                        <Button onClick={() => setUploading(true)}>
                            <Upload aria-hidden /> Upload
                        </Button>
                    ) : null
                }
            />
            <div className="flex flex-col gap-3 sm:flex-row">
                <SearchInput
                    value={search}
                    onChange={setSearch}
                    placeholder="Search documents…"
                />
                <NativeSelect
                    aria-label="Category"
                    value={filters.category ?? ''}
                    onChange={(e) =>
                        router.get(
                            index.url(),
                            {
                                search: search || undefined,
                                category: e.target.value || undefined,
                            },
                            { preserveState: true, replace: true },
                        )
                    }
                    className="sm:w-48"
                >
                    <option value="">All categories</option>
                    {categories.map((category) => (
                        <option key={category} value={category}>
                            {category}
                        </option>
                    ))}
                </NativeSelect>
            </div>
            {documents.data.length === 0 ? (
                <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                    <FileText className="size-8 text-primary" aria-hidden />
                    No documents.
                </div>
            ) : (
                <Card className="divide-y p-0">
                    {documents.data.map((document) => (
                        <div
                            key={document.id}
                            className="flex flex-wrap items-center gap-3 px-4 py-3"
                        >
                            <span
                                className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-sky-500 to-navy-500 text-[10px] font-bold text-white uppercase"
                                aria-hidden
                            >
                                {document.original_name.split('.').pop()}
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-medium">
                                    {document.title}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {[
                                        document.category,
                                        size(document.size),
                                        format.date(document.uploaded_at),
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                            </div>
                            <TonePill tone="info">
                                {document.audience_label}
                                {document.school_class &&
                                    ` · ${document.school_class}`}
                            </TonePill>
                            <Button size="sm" variant="outline" asChild>
                                <a href={download.url(document.id)}>
                                    <Download aria-hidden /> Download
                                </a>
                            </Button>
                            {can.delete && (
                                <ConfirmDelete
                                    action={destroy(document.id)}
                                    title="Delete document?"
                                    description={`“${document.title}” will be removed for everyone.`}
                                />
                            )}
                        </div>
                    ))}
                </Card>
            )}
            <Pagination meta={documents.meta} />
            {uploading && (
                <UploadDialog
                    audiences={audiences}
                    classes={classes}
                    categories={categories}
                    allowed={allowed}
                    onClose={() => setUploading(false)}
                />
            )}
        </div>
    );
}

Documents.layout = {
    breadcrumbs: [{ title: 'Documents', href: index() }],
};
