import { router } from '@inertiajs/react';
import { IdCard, RefreshCcw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { print, reissue } from '@/routes/people/id-cards';
import PhotoUploader from './photo-uploader';

/**
 * Photo plus ID-card actions for a student, parent or staff member.
 */
export default function IdentityPanel({
    type,
    id,
    name,
    photoUrl,
    canEdit,
}: {
    type: 'students' | 'parents' | 'staff';
    id: number;
    name: string;
    photoUrl: string | null | undefined;
    canEdit: boolean;
}) {
    const photoKind = type === 'parents' ? 'guardians' : type;

    return (
        <section
            className="flex flex-col gap-4 rounded-2xl border bg-card p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between"
            aria-label="Photo and ID card"
        >
            <PhotoUploader
                kind={photoKind}
                id={id}
                name={name}
                photoUrl={photoUrl ?? null}
                canEdit={canEdit}
            />
            <div className="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    onClick={() =>
                        window.open(
                            print.url({ query: { type, 'ids[]': String(id) } }),
                            '_blank',
                            'noopener',
                        )
                    }
                >
                    <IdCard aria-hidden /> Print ID card
                </Button>
                {canEdit && (
                    <Button
                        variant="ghost"
                        onClick={() => {
                            if (
                                window.confirm(
                                    'Issue a new card? The QR code on the current card will stop working.',
                                )
                            ) {
                                router.post(
                                    reissue.url({ type, id }),
                                    {},
                                    { preserveScroll: true },
                                );
                            }
                        }}
                    >
                        <RefreshCcw aria-hidden /> Reissue card
                    </Button>
                )}
            </div>
        </section>
    );
}
