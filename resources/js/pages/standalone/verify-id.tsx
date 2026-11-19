import { Head } from '@inertiajs/react';
import { CircleAlert, CircleCheck, CircleX } from 'lucide-react';

type Props = {
    result: {
        valid: boolean;
        type: string;
        name: string;
        number: string;
        school: string;
        valid_until: string | null;
    } | null;
};

/**
 * Public page opened by the QR code on an ID card. Shows only what is printed on the card.
 */
export default function VerifyId({ result }: Props) {
    const state =
        result === null ? 'unknown' : result.valid ? 'valid' : 'inactive';
    const styles = {
        valid: {
            icon: CircleCheck,
            title: 'This ID card is valid',
            className: 'from-emerald-500 to-teal-600',
        },
        inactive: {
            icon: CircleX,
            title: 'This ID card is no longer active',
            className: 'from-rose-500 to-red-600',
        },
        unknown: {
            icon: CircleAlert,
            title: 'ID card not recognised',
            className: 'from-slate-500 to-slate-700',
        },
    }[state];
    const Icon = styles.icon;

    return (
        <main className="flex min-h-screen items-center justify-center bg-gradient-to-br from-navy-50 via-white to-royal-50 p-4 dark:from-slate-950 dark:via-slate-900 dark:to-navy-950">
            <Head title="ID card verification" />
            <div className="w-full max-w-md overflow-hidden rounded-2xl bg-card text-card-foreground shadow-xl">
                <div
                    className={`flex items-center gap-3 bg-gradient-to-r p-5 text-white ${styles.className}`}
                >
                    <Icon className="size-8 shrink-0" aria-hidden />
                    <h1 className="text-lg font-semibold">{styles.title}</h1>
                </div>
                {result ? (
                    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 p-5 text-sm">
                        <dt className="text-muted-foreground">Name</dt>
                        <dd className="font-medium">{result.name}</dd>
                        <dt className="text-muted-foreground">Card holder</dt>
                        <dd>{result.type}</dd>
                        <dt className="text-muted-foreground">ID number</dt>
                        <dd className="font-mono">{result.number}</dd>
                        <dt className="text-muted-foreground">Issued by</dt>
                        <dd>{result.school}</dd>
                        {result.valid_until && (
                            <>
                                <dt className="text-muted-foreground">
                                    Valid until
                                </dt>
                                <dd>{result.valid_until}</dd>
                            </>
                        )}
                    </dl>
                ) : (
                    <p className="p-5 text-sm text-muted-foreground">
                        This card has been replaced or the code is not genuine.
                        If you found this card, please hand it to the school
                        office.
                    </p>
                )}
            </div>
        </main>
    );
}
