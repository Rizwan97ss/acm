import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

type Props = {
    certificate: {
        serial_no: string;
        type: string;
        title: string;
        body: string;
        student: string;
        status: 'draft' | 'issued' | 'revoked';
        issued_on: string | null;
        verify_url: string;
        qr_svg: string;
    };
    school: {
        name: string;
        logo_url: string | null;
        address: string | null;
        phone: string | null;
        email: string | null;
    };
};

const eyebrow: Record<string, string> = {
    bonafide: 'Bonafide Certificate',
    character: 'Character Certificate',
    transfer: 'Transfer Certificate',
    achievement: 'Award of Excellence',
};

/** Decorative corner flourish, rotated into each corner. */
function Corner({ className }: { className: string }) {
    return (
        <svg
            viewBox="0 0 100 100"
            aria-hidden
            className={`absolute size-[26mm] text-gold-500 ${className}`}
        >
            <path
                d="M6 94V36C6 18 18 6 36 6h58"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.4"
            />
            <path
                d="M14 94V40c0-14 12-26 26-26h54"
                fill="none"
                stroke="currentColor"
                strokeWidth="0.9"
            />
            <path
                d="M22 30c6-10 16-12 24-8-10 2-16 8-18 18-4-2-6-6-6-10z"
                fill="currentColor"
                opacity="0.85"
            />
            <circle cx="22" cy="22" r="3.4" fill="currentColor" />
        </svg>
    );
}

/** A gold rosette seal with the school's initials. */
function Seal({ school }: { school: string }) {
    const initials = school
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 3)
        .map((word) => word[0])
        .join('')
        .toUpperCase();
    const points = Array.from({ length: 36 }, (_, index) => {
        const angle = (index / 36) * Math.PI * 2;
        const radius = index % 2 === 0 ? 48 : 43;

        return `${50 + radius * Math.cos(angle)},${50 + radius * Math.sin(angle)}`;
    }).join(' ');

    return (
        <svg
            viewBox="0 0 100 100"
            className="size-[34mm] drop-shadow-md"
            aria-hidden
        >
            <defs>
                <radialGradient id="seal-gold" cx="40%" cy="35%" r="70%">
                    <stop offset="0%" stopColor="#fde68a" />
                    <stop offset="55%" stopColor="#f59e0b" />
                    <stop offset="100%" stopColor="#b45309" />
                </radialGradient>
                <path
                    id="seal-ring"
                    d="M50 50 m-30 0 a30 30 0 1 1 60 0 a30 30 0 1 1 -60 0"
                />
            </defs>
            <polygon points={points} fill="url(#seal-gold)" />
            <circle
                cx="50"
                cy="50"
                r="38"
                fill="none"
                stroke="#fff7ed"
                strokeWidth="0.8"
            />
            <circle
                cx="50"
                cy="50"
                r="24"
                fill="#fffbeb"
                stroke="#b45309"
                strokeWidth="0.8"
            />
            <text
                fontSize="5.4"
                fontWeight="700"
                letterSpacing="1.4"
                fill="#78350f"
            >
                <textPath href="#seal-ring" startOffset="2%">
                    OFFICIAL SEAL ★ {school.toUpperCase().slice(0, 34)} ★
                </textPath>
            </text>
            <text
                x="50"
                y="55"
                textAnchor="middle"
                fontSize="13"
                fontFamily="'Instrument Serif', Georgia, serif"
                fill="#1e3a8a"
            >
                {initials}
            </text>
        </svg>
    );
}

/** Puts the student's name in the body text in gold italic. */
function emphasise(body: string, name: string): ReactNode[] {
    if (!name || !body.includes(name)) {
        return [body];
    }

    return body.split(name).flatMap((part, index, parts) =>
        index < parts.length - 1
            ? [
                  part,
                  <span
                      key={index}
                      className="font-display text-[1.25em] text-navy-900 italic"
                  >
                      {name}
                  </span>,
              ]
            : [part],
    );
}

/**
 * Printable certificate on A4 landscape. Drafts and revoked certificates carry a
 * watermark; the QR code opens the public verification page.
 */
export default function CertificatePrint({ certificate, school }: Props) {
    const watermark =
        certificate.status === 'draft'
            ? 'DRAFT'
            : certificate.status === 'revoked'
              ? 'REVOKED'
              : null;
    const issued = certificate.issued_on
        ? new Date(`${certificate.issued_on}T12:00:00Z`).toLocaleDateString(
              undefined,
              { day: 'numeric', month: 'long', year: 'numeric' },
          )
        : '—';

    return (
        <div className="min-h-screen bg-slate-200 py-8 print:bg-white print:py-0">
            <Head title={`${certificate.title} ${certificate.serial_no}`} />
            <style>{`
                @page { size: A4 landscape; margin: 0; }
                * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            `}</style>
            <div className="mx-auto mb-4 flex max-w-[297mm] items-center justify-between gap-3 px-4 print:hidden">
                <p className="text-sm text-slate-600">
                    Print on A4, landscape, margins None, Background graphics
                    on.
                </p>
                <Button onClick={() => window.print()}>
                    <Printer aria-hidden /> Print
                </Button>
            </div>

            <article className="relative mx-auto h-[210mm] w-[297mm] overflow-hidden bg-[radial-gradient(ellipse_at_center,#fffdf7_0%,#fbf5e6_70%,#f3e8cc_100%)] text-navy-950 shadow-2xl print:shadow-none">
                <div
                    className="absolute inset-[7mm] border-[2.5mm] border-double border-gold-500"
                    aria-hidden
                />
                <div
                    className="absolute inset-[11.5mm] border border-navy-800/70"
                    aria-hidden
                />
                <Corner className="top-[9mm] left-[9mm]" />
                <Corner className="top-[9mm] right-[9mm] rotate-90" />
                <Corner className="right-[9mm] bottom-[9mm] rotate-180" />
                <Corner className="bottom-[9mm] left-[9mm] -rotate-90" />

                {watermark && (
                    <div
                        className="pointer-events-none absolute inset-0 flex items-center justify-center"
                        aria-hidden
                    >
                        <span className="-rotate-[24deg] text-[120px] font-black tracking-[0.3em] text-crimson-500/15">
                            {watermark}
                        </span>
                    </div>
                )}

                <div className="relative flex h-full flex-col items-center px-[30mm] pt-[18mm] pb-[16mm] text-center">
                    <header className="flex flex-col items-center">
                        {school.logo_url && (
                            <img
                                src={school.logo_url}
                                alt=""
                                className="size-[16mm] object-contain"
                            />
                        )}
                        <p className="mt-[2mm] text-[15pt] font-semibold tracking-[0.18em] text-navy-900 uppercase">
                            {school.name}
                        </p>
                        {(school.address || school.phone) && (
                            <p className="mt-[0.6mm] text-[8.5pt] text-navy-700/80">
                                {[school.address, school.phone]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                        )}
                    </header>

                    <p className="mt-[7mm] text-[9pt] font-semibold tracking-[0.4em] text-gold-700 uppercase">
                        {eyebrow[certificate.type] ?? 'Certificate'}
                    </p>
                    <h1 className="mt-[1.5mm] font-display text-[40pt] leading-none text-navy-950">
                        {certificate.title}
                    </h1>
                    <div
                        className="mt-[3mm] flex items-center gap-[3mm] text-gold-500"
                        aria-hidden
                    >
                        <span className="h-px w-[28mm] bg-current" />
                        <span className="text-[10pt]">◆</span>
                        <span className="h-px w-[28mm] bg-current" />
                    </div>

                    <p className="mt-[6mm] text-[9pt] tracking-[0.3em] text-navy-700 uppercase">
                        Presented to
                    </p>
                    <p className="mt-[1mm] border-b-2 border-gold-400 px-[10mm] pb-[1mm] font-display text-[32pt] leading-tight text-navy-900 italic">
                        {certificate.student}
                    </p>

                    <p className="mt-[6mm] max-w-[205mm] font-display text-[14pt] leading-[1.55] text-navy-900/90">
                        {emphasise(certificate.body, certificate.student)}
                    </p>

                    <footer className="mt-auto grid w-full grid-cols-[1fr_auto_1fr] items-end gap-[8mm]">
                        <div className="flex items-end gap-[4mm] text-left">
                            <div
                                className="size-[24mm] shrink-0 rounded-[1.5mm] bg-white p-[1mm] shadow-sm [&>svg]:size-full"
                                dangerouslySetInnerHTML={{
                                    __html: certificate.qr_svg,
                                }}
                            />
                            <div className="text-[8pt] leading-snug text-navy-800">
                                <p>
                                    Serial no.{' '}
                                    <span className="font-mono font-semibold">
                                        {certificate.serial_no}
                                    </span>
                                </p>
                                <p>Date of issue: {issued}</p>
                                <p className="mt-[1mm] text-[7pt] text-navy-600">
                                    Scan the code to verify this certificate
                                </p>
                            </div>
                        </div>
                        <Seal school={school.name} />
                        <div className="grid grid-cols-2 gap-[8mm] text-[9pt]">
                            <div className="text-center">
                                <div className="mb-[1.5mm] h-px bg-navy-800/70" />
                                <p className="font-semibold">Class Teacher</p>
                            </div>
                            <div className="text-center">
                                <div className="mb-[1.5mm] h-px bg-navy-800/70" />
                                <p className="font-semibold">Principal</p>
                            </div>
                        </div>
                    </footer>
                </div>
            </article>
        </div>
    );
}
