import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    certificate: {
        serial_no: string;
        title: string;
        body: string;
        status: 'draft' | 'issued' | 'revoked';
        issued_on: string | null;
        verify_url: string;
    };
    school: { name: string; logo_url: string | null };
};

/**
 * Printable A4 certificate. Drafts and revoked certificates carry a watermark so they
 * cannot be passed off as valid.
 */
export default function CertificatePrint({ certificate, school }: Props) {
    const watermark =
        certificate.status === 'draft'
            ? 'DRAFT'
            : certificate.status === 'revoked'
              ? 'REVOKED'
              : null;

    return (
        <div className="min-h-screen bg-slate-100 py-8 print:bg-white print:py-0">
            <Head title={`${certificate.title} ${certificate.serial_no}`} />
            <div className="mx-auto mb-4 flex max-w-[210mm] justify-end px-4 print:hidden">
                <Button onClick={() => window.print()}>
                    <Printer aria-hidden /> Print
                </Button>
            </div>
            <article className="relative mx-auto flex min-h-[297mm] max-w-[210mm] flex-col overflow-hidden bg-white p-[18mm] text-slate-900 shadow-xl print:shadow-none">
                <div
                    className="pointer-events-none absolute inset-[8mm] rounded-sm border-[3px] border-double border-amber-600"
                    aria-hidden
                />
                {watermark && (
                    <div
                        className="pointer-events-none absolute inset-0 flex items-center justify-center"
                        aria-hidden
                    >
                        <span className="-rotate-30 text-[110px] font-black tracking-widest text-rose-500/15">
                            {watermark}
                        </span>
                    </div>
                )}
                <header className="relative flex flex-col items-center gap-3 pt-6 text-center">
                    {school.logo_url && (
                        <img
                            src={school.logo_url}
                            alt=""
                            className="h-20 w-20 object-contain"
                        />
                    )}
                    <p className="text-2xl font-bold tracking-wide text-indigo-900">
                        {school.name}
                    </p>
                    <div
                        className="h-1 w-24 rounded-full bg-gradient-to-r from-amber-400 via-orange-500 to-rose-500"
                        aria-hidden
                    />
                </header>
                <h1 className="relative mt-14 text-center font-serif text-4xl text-indigo-950">
                    {certificate.title}
                </h1>
                <p className="relative mx-auto mt-12 max-w-[150mm] text-center font-serif text-lg leading-loose whitespace-pre-line">
                    {certificate.body}
                </p>
                <footer className="relative mt-auto grid grid-cols-2 items-end gap-8 pb-4 text-sm">
                    <div className="space-y-1">
                        <p>
                            Serial no.{' '}
                            <span className="font-mono font-semibold">
                                {certificate.serial_no}
                            </span>
                        </p>
                        <p>Date of issue: {certificate.issued_on ?? '—'}</p>
                        <p className="text-xs break-all text-slate-500">
                            Verify at {certificate.verify_url}
                        </p>
                    </div>
                    <div className="text-center">
                        <div className="mx-auto mb-2 h-px w-48 bg-slate-400" />
                        <p>Principal</p>
                    </div>
                </footer>
            </article>
        </div>
    );
}
