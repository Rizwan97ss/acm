import {
    Download,
    ExternalLink,
    FileAudio,
    FileImage,
    FileSpreadsheet,
    FileText,
    FileVideo,
    Link2,
    Presentation,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { MaterialKind } from '@/types/learning';
import { fileSize } from './format';

export type StudentMaterial = {
    id: number;
    kind: MaterialKind;
    kind_label: string;
    title: string;
    description: string | null;
    original_name: string | null;
    size: number;
    preview_url: string | null;
    download_url: string | null;
    link_url: string | null;
};

export const materialIcons: Record<MaterialKind, LucideIcon> = {
    pdf: FileText,
    document: FileText,
    presentation: Presentation,
    spreadsheet: FileSpreadsheet,
    audio: FileAudio,
    video: FileVideo,
    image: FileImage,
    link: Link2,
};

/**
 * Shows a material the way it works best: PDFs in the browser's viewer, video and audio
 * in native players (seeking uses HTTP ranges), images inline, links in a new tab, and
 * office files as a download when the teacher allows it.
 */
export default function MaterialViewer({
    material,
}: {
    material: StudentMaterial;
}) {
    const Icon = materialIcons[material.kind];

    return (
        <article className="overflow-hidden rounded-2xl border bg-card shadow-sm">
            <header className="flex flex-wrap items-start gap-3 border-b p-4">
                <span className="rounded-xl bg-navy-50 p-2.5 text-navy-700 dark:bg-navy-500/15 dark:text-navy-200">
                    <Icon className="size-5" aria-hidden />
                </span>
                <div className="min-w-0 flex-1">
                    <h3 className="font-semibold">{material.title}</h3>
                    <p className="text-xs text-muted-foreground">
                        {material.kind_label}
                        {material.size > 0 && ` · ${fileSize(material.size)}`}
                    </p>
                    {material.description && (
                        <p className="mt-1 text-sm text-muted-foreground">
                            {material.description}
                        </p>
                    )}
                </div>
                {material.link_url && (
                    <Button asChild size="sm" variant="outline">
                        <a
                            href={material.link_url}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <ExternalLink aria-hidden /> Open link
                        </a>
                    </Button>
                )}
                {material.download_url && (
                    <Button asChild size="sm" variant="outline">
                        <a href={material.download_url}>
                            <Download aria-hidden /> Download
                        </a>
                    </Button>
                )}
            </header>

            {material.preview_url && material.kind === 'pdf' && (
                <iframe
                    src={material.preview_url}
                    title={material.title}
                    className="h-[70vh] min-h-96 w-full bg-muted"
                    loading="lazy"
                />
            )}
            {material.preview_url && material.kind === 'video' && (
                <video
                    controls
                    preload="metadata"
                    controlsList={
                        material.download_url ? undefined : 'nodownload'
                    }
                    className="aspect-video w-full bg-black"
                    src={material.preview_url}
                >
                    Your browser cannot play this video.
                </video>
            )}
            {material.preview_url && material.kind === 'audio' && (
                <div className="p-4">
                    <audio
                        controls
                        preload="metadata"
                        controlsList={
                            material.download_url ? undefined : 'nodownload'
                        }
                        className="w-full"
                        src={material.preview_url}
                    >
                        Your browser cannot play this audio.
                    </audio>
                </div>
            )}
            {material.preview_url && material.kind === 'image' && (
                <img
                    src={material.preview_url}
                    alt={material.title}
                    loading="lazy"
                    className="max-h-[70vh] w-full bg-muted object-contain"
                />
            )}
            {!material.preview_url &&
                !material.link_url &&
                !material.download_url && (
                    <p className="p-4 text-sm text-muted-foreground">
                        This file can only be opened in class. Ask your teacher
                        if you need a copy.
                    </p>
                )}
            {!material.preview_url && material.download_url && (
                <p className="p-4 text-sm text-muted-foreground">
                    Download the file to open it in{' '}
                    {material.kind === 'presentation'
                        ? 'PowerPoint, Keynote or Google Slides'
                        : material.kind === 'spreadsheet'
                          ? 'Excel or Google Sheets'
                          : 'Word, Pages or Google Docs'}
                    .
                </p>
            )}
        </article>
    );
}
