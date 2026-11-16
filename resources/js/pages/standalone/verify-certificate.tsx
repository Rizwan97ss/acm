import { Head } from '@inertiajs/react';
import { CircleAlert, CircleCheck, CircleX } from 'lucide-react';

type Props = {
    result: {
        valid: boolean;
        revoked: boolean;
        serial_no: string;
        title: string;
        student: string;
        school: string;
        issued_on: string | null;
    } | null;
};

/**
 * Public page reached from the link printed on a certificate.
 */
export default function VerifyCertificate({ result }: Props) {
    const state =
        result === null ? 'unknown' : result.valid ? 'valid' : 'revoked';
    const styles = {
        valid: {
            icon: CircleCheck,
            title: 'This certificate is genuine',
            className: 'from-emerald-500 to-teal-600',
        },
        revoked: {
            icon: CircleX,
            title: 'This certificate has been revoked',
            className: 'from-rose-500 to-red-600',
        },
        unknown: {
            icon: CircleAlert,
            title: 'Certificate not found',
            className: 'from-slate-500 to-slate-700',
        },
    }[state];
    const Icon = styles.icon;

    return (
        <main className="flex min-h-screen items-center justify-center bg-gradient-to-br from-navy-50 via-white to-royal-50 p-4 dark:from-slate-950 dark:via-slate-900 dark:to-navy-950">
            <Head title="Certificate verification" />
            <div className="w-full max-w-md overflow-hidden rounded-2xl bg-card text-card-foreground shadow-xl">
                <div
                    className={`flex items-center gap-3 bg-gradient-to-r p-5 text-white ${styles.className}`}
                >
                    <Icon className="size-8 shrink-0" aria-hidden />
                    <h1 className="text-lg font-semibold">{styles.title}</h1>
                </div>
                {result ? (
                    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 p-5 text-sm">
                        <dt className="text-muted-foreground">Certificate</dt>
                        <dd className="font-medium">{result.title}</dd>
                        <dt className="text-muted-foreground">Serial no.</dt>
                        <dd className="font-mono">{result.serial_no}</dd>
                        <dt className="text-muted-foreground">Awarded to</dt>
                        <dd>{result.student}</dd>
                        <dt className="text-muted-foreground">Issued by</dt>
                        <dd>{result.school}</dd>
                        <dt className="text-muted-foreground">Date</dt>
                        <dd>{result.issued_on ?? '—'}</dd>
                    </dl>
                ) : (
                    <p className="p-5 text-sm text-muted-foreground">
                        Check that the link was typed exactly as printed. If it
                        still does not match, contact the school that issued the
                        certificate.
                    </p>
                )}
            </div>
        </main>
    );
}
