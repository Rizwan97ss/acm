import { router } from '@inertiajs/react';
import { Camera, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { destroy, update } from '@/routes/people/photos';

type Kind = 'students' | 'guardians' | 'staff' | 'members';

/**
 * Shows a person's photo (or initials) and lets permitted users upload or remove it.
 * Uploads are cropped to a square on the server for ID cards.
 */
export default function PhotoUploader({
    kind,
    id,
    name,
    photoUrl,
    canEdit,
    size = 'md',
}: {
    kind: Kind;
    id: number;
    name: string;
    photoUrl: string | null;
    canEdit: boolean;
    size?: 'sm' | 'md' | 'lg';
}) {
    const input = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const initials = name
        .split(/\s+/)
        .filter(Boolean)
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
    const box = {
        sm: 'size-14 text-lg',
        md: 'size-24 text-2xl',
        lg: 'size-32 text-3xl',
    }[size];

    const upload = (file: File | undefined): void => {
        if (!file) {
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            setError('Choose a photo under 5 MB.');

            return;
        }

        setError(null);
        router.post(
            update.url({ kind, id }),
            { photo: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setBusy(true),
                onError: (errors) =>
                    setError(
                        errors.photo ?? 'The photo could not be uploaded.',
                    ),
                onFinish: () => {
                    setBusy(false);

                    if (input.current) {
                        input.current.value = '';
                    }
                },
            },
        );
    };

    return (
        <div className="flex items-center gap-4">
            <div
                className={cn(
                    'relative shrink-0 overflow-hidden rounded-2xl bg-gradient-to-br from-navy-100 to-royal-100 ring-2 ring-gold-300 dark:from-navy-500/20 dark:to-royal-500/20',
                    box,
                )}
            >
                {photoUrl ? (
                    <img
                        src={photoUrl}
                        alt={`Photo of ${name}`}
                        className="size-full object-cover"
                    />
                ) : (
                    <span
                        className="flex size-full items-center justify-center font-display text-navy-700 dark:text-navy-100"
                        aria-hidden
                    >
                        {initials}
                    </span>
                )}
                {busy && (
                    <span className="absolute inset-0 flex items-center justify-center bg-white/70 dark:bg-black/50">
                        <Spinner />
                    </span>
                )}
            </div>
            {canEdit && (
                <div className="space-y-2">
                    <input
                        ref={input}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="sr-only"
                        id={`photo-${kind}-${id}`}
                        onChange={(event) => upload(event.target.files?.[0])}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={busy}
                            onClick={() => input.current?.click()}
                        >
                            <Camera aria-hidden />{' '}
                            {photoUrl ? 'Change photo' : 'Upload photo'}
                        </Button>
                        {photoUrl && (
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                className="text-destructive"
                                disabled={busy}
                                onClick={() =>
                                    router.delete(destroy.url({ kind, id }), {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                <Trash2 aria-hidden /> Remove
                            </Button>
                        )}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        JPG, PNG or WebP. A clear, front-facing photo works best
                        on ID cards.
                    </p>
                    {error && (
                        <p className="text-xs text-destructive">{error}</p>
                    )}
                </div>
            )}
        </div>
    );
}
