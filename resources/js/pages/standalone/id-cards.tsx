import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Card = {
    key: string;
    kind: 'student' | 'staff' | 'parent';
    name: string;
    number: string;
    role: string;
    subtitle: string | null;
    photo_url: string | null;
    initials: string;
    fields: Record<string, string>;
    emergency: { name: string; phone: string | null } | null;
    children?: { name: string; admission_no: string; class: string | null }[];
    valid_until: string;
    verify_url: string;
    qr_svg: string;
};

type School = {
    name: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    logo_url: string | null;
};

const accent: Record<
    Card['kind'],
    { ribbon: string; bar: string; ring: string }
> = {
    student: {
        ribbon: 'bg-gold-400 text-navy-950',
        bar: 'bg-gold-400',
        ring: 'ring-gold-400',
    },
    staff: {
        ribbon: 'bg-emerald-500 text-white',
        bar: 'bg-emerald-500',
        ring: 'ring-emerald-500',
    },
    parent: {
        ribbon: 'bg-crimson-600 text-white',
        bar: 'bg-crimson-600',
        ring: 'ring-crimson-500',
    },
};

const formatDate = (iso: string): string =>
    new Date(`${iso}T12:00:00Z`).toLocaleDateString(undefined, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });

function SchoolMark({
    school,
    light = false,
}: {
    school: School;
    light?: boolean;
}) {
    const [logoFailed, setLogoFailed] = useState(false);

    return (
        <div className="flex min-w-0 items-center gap-[1.6mm]">
            {school.logo_url && !logoFailed ? (
                <img
                    src={school.logo_url}
                    alt=""
                    onError={() => setLogoFailed(true)}
                    ref={(image) => {
                        if (image?.complete && image.naturalWidth === 0) {
                            setLogoFailed(true);
                        }
                    }}
                    className="size-[7mm] shrink-0 rounded-[1.2mm] bg-white object-contain p-[0.4mm]"
                />
            ) : (
                <span className="flex size-[7mm] shrink-0 items-center justify-center rounded-[1.2mm] bg-gold-400 text-[9pt] font-bold text-navy-950">
                    {school.name.charAt(0)}
                </span>
            )}
            <p
                className={cn(
                    'line-clamp-2 min-w-0 font-display text-[8pt] leading-[1.1] break-words',
                    light ? 'text-white' : 'text-navy-950',
                )}
            >
                {school.name}
            </p>
        </div>
    );
}

/**
 * The photo sits in its own row below the header (never overlapping it), at a fixed
 * passport ratio so every card lines up the same.
 */
function Photo({ card }: { card: Card }) {
    const style = accent[card.kind];
    const [photoFailed, setPhotoFailed] = useState(false);
    const frame = cn(
        'h-[24mm] w-[20mm] shrink-0 rounded-[2mm] ring-[0.7mm] ring-offset-[0.6mm] ring-offset-white',
        style.ring,
    );

    return card.photo_url && !photoFailed ? (
        <img
            src={card.photo_url}
            onError={() => setPhotoFailed(true)}
            ref={(image) => {
                if (image?.complete && image.naturalWidth === 0) {
                    setPhotoFailed(true);
                }
            }}
            alt=""
            className={cn(frame, 'bg-navy-50 object-cover')}
        />
    ) : (
        <span
            className={cn(
                frame,
                'flex items-center justify-center bg-navy-50 font-display text-[16pt] text-navy-800',
            )}
        >
            {card.initials}
        </span>
    );
}

const faceClass =
    'card-face relative flex h-[85.6mm] w-[54mm] shrink-0 flex-col overflow-hidden rounded-[3mm] bg-white text-navy-950 shadow-lg print:rounded-none print:shadow-none';

function Front({ card, school }: { card: Card; school: School }) {
    const style = accent[card.kind];

    return (
        <section className={faceClass} aria-label={`${card.name} front`}>
            <header className="relative flex h-[14mm] shrink-0 items-center overflow-hidden bg-gradient-to-br from-navy-950 via-navy-800 to-royal-700 px-[3mm]">
                <div
                    className="pointer-events-none absolute -top-[7mm] -right-[7mm] size-[16mm] rounded-full bg-gold-400/20"
                    aria-hidden
                />
                <div className="relative min-w-0">
                    <SchoolMark school={school} light />
                </div>
            </header>
            <div
                className={cn(
                    'flex h-[4.6mm] shrink-0 items-center justify-center truncate px-[3mm] text-[5.6pt] font-bold tracking-[0.16em] uppercase',
                    style.ribbon,
                )}
            >
                {card.role}
            </div>

            <div className="flex shrink-0 justify-center pt-[3mm]">
                <Photo card={card} />
            </div>

            <div className="flex min-h-0 flex-1 flex-col overflow-hidden px-[3.5mm] pt-[2.2mm] text-center">
                <p className="line-clamp-2 font-display text-[10.5pt] leading-[1.15] break-words">
                    {card.name}
                </p>
                {card.subtitle && (
                    <p className="mt-[0.5mm] truncate text-[6.2pt] font-medium text-navy-700">
                        {card.subtitle}
                    </p>
                )}
                <p className="mx-auto mt-[1.2mm] max-w-full truncate rounded-full bg-navy-50 px-[2.4mm] py-[0.4mm] font-mono text-[6.8pt] font-semibold tracking-wide">
                    {card.number}
                </p>

                <dl className="mt-[1.8mm] min-h-0 space-y-[0.6mm] overflow-hidden text-left text-[5.8pt] leading-[1.25]">
                    {Object.entries(card.fields)
                        .slice(0, 3)
                        .map(([label, value]) => (
                            <div
                                key={label}
                                className="flex justify-between gap-[2mm] border-b border-dashed border-navy-100 pb-[0.4mm]"
                            >
                                <dt className="shrink-0 text-navy-500">
                                    {label}
                                </dt>
                                <dd className="min-w-0 truncate text-right font-medium">
                                    {value}
                                </dd>
                            </div>
                        ))}
                </dl>
            </div>

            <footer className="flex shrink-0 items-center justify-between px-[3.5mm] pt-[1mm] pb-[2mm] text-[5.5pt] text-navy-600">
                <span>Valid until</span>
                <span className="font-semibold text-navy-950">
                    {formatDate(card.valid_until)}
                </span>
            </footer>
            <div className={cn('h-[1.6mm] shrink-0', style.bar)} aria-hidden />
        </section>
    );
}

const MAX_CHILDREN = 5;

function Back({ card, school }: { card: Card; school: School }) {
    const style = accent[card.kind];
    const children = card.children ?? [];
    const isParent = card.kind === 'parent';

    return (
        <section className={faceClass} aria-label={`${card.name} back`}>
            <div className={cn('h-[1.6mm] shrink-0', style.bar)} aria-hidden />
            <div className="flex min-h-0 flex-1 flex-col px-[3.5mm] pt-[2.4mm]">
                <div className="shrink-0">
                    <SchoolMark school={school} />
                </div>

                <div className="mt-[2mm] min-h-0 flex-1 overflow-hidden">
                    {isParent ? (
                        <>
                            <p className="text-[5.3pt] font-bold tracking-[0.12em] text-navy-500 uppercase">
                                Children at this school
                            </p>
                            <ul className="mt-[0.8mm] space-y-[0.6mm]">
                                {children
                                    .slice(0, MAX_CHILDREN)
                                    .map((child) => (
                                        <li
                                            key={child.admission_no}
                                            className="flex items-baseline justify-between gap-[1.5mm] rounded-[1mm] bg-navy-50 px-[1.6mm] py-[0.6mm] text-[5.6pt] leading-tight"
                                        >
                                            <span className="min-w-0 truncate font-semibold">
                                                {child.name}
                                            </span>
                                            <span className="shrink-0 text-[5pt] text-navy-600">
                                                {child.class ??
                                                    child.admission_no}
                                            </span>
                                        </li>
                                    ))}
                                {children.length > MAX_CHILDREN && (
                                    <li className="text-[5.3pt] text-navy-600">
                                        + {children.length - MAX_CHILDREN} more
                                    </li>
                                )}
                                {children.length === 0 && (
                                    <li className="text-[5.5pt] text-navy-600">
                                        No children linked yet.
                                    </li>
                                )}
                            </ul>
                        </>
                    ) : card.emergency ? (
                        <div className="rounded-[1.6mm] bg-navy-50 px-[2mm] py-[1.2mm] text-[6pt] leading-snug">
                            <p className="text-[5.3pt] font-bold tracking-[0.12em] text-navy-500 uppercase">
                                In an emergency
                            </p>
                            <p className="truncate font-semibold">
                                {card.emergency.name}
                            </p>
                            {card.emergency.phone && (
                                <p className="truncate font-mono">
                                    {card.emergency.phone}
                                </p>
                            )}
                        </div>
                    ) : null}
                </div>

                <div className="flex shrink-0 flex-col items-center pt-[1.5mm]">
                    <div
                        className={cn(
                            'rounded-[1mm] bg-white p-[0.8mm] [&>svg]:block [&>svg]:size-full',
                            isParent ? 'size-[19mm]' : 'size-[23mm]',
                        )}
                        dangerouslySetInnerHTML={{ __html: card.qr_svg }}
                    />
                    <p className="mt-[0.4mm] text-[5.3pt] font-semibold tracking-wide text-navy-700 uppercase">
                        Scan to verify
                    </p>
                </div>

                <div className="mt-[1.5mm] shrink-0 border-t border-navy-100 pt-[1.2mm] text-center text-[5pt] leading-snug text-navy-600">
                    <p>If found, please return to</p>
                    <p className="truncate font-semibold text-navy-900">
                        {school.name}
                    </p>
                    {school.address && (
                        <p className="line-clamp-2 break-words">
                            {school.address}
                        </p>
                    )}
                    {school.phone && <p className="truncate">{school.phone}</p>}
                </div>
            </div>
            <div className="flex shrink-0 items-end justify-between gap-[2mm] px-[3.5mm] pt-[1.6mm] pb-[2.2mm]">
                <span className="min-w-0 truncate font-mono text-[5pt] text-navy-500">
                    {card.number}
                </span>
                <span className="w-[18mm] shrink-0 border-t border-navy-400 pt-[0.4mm] text-center text-[5pt] text-navy-600">
                    Principal
                </span>
            </div>
        </section>
    );
}

/**
 * Printable ID cards at real card size (54 × 85.6 mm). Each side prints on its own
 * card-sized page so the sheet can go straight to a card printer, or be cut and laminated.
 */
export default function IdCards({
    cards,
    school,
}: {
    cards: Card[];
    school: School;
}) {
    return (
        <div className="min-h-screen bg-slate-200 print:bg-white">
            <Head
                title={
                    cards.length === 1
                        ? `ID card – ${cards[0].name}`
                        : `${cards.length} ID cards`
                }
            />
            <style>{`
                @page { size: 54mm 85.6mm; margin: 0; }
                * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                @media print { .card-face { break-after: page; } .card-pair { display: contents; } }
                @media screen { .card-sheet { zoom: 1.55; } }
            `}</style>

            <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-5 print:hidden">
                <div>
                    <h1 className="text-lg font-semibold">
                        {cards.length === 1
                            ? 'ID card'
                            : `${cards.length} ID cards`}
                    </h1>
                    <p className="text-sm text-slate-600">
                        Printer settings: paper size “Custom 54 × 85.6 mm” (or a
                        CR80 card printer), margins <strong>None</strong>, scale{' '}
                        <strong>100%</strong>, and{' '}
                        <strong>Background graphics</strong> on.
                    </p>
                </div>
                <Button onClick={() => window.print()}>
                    <Printer aria-hidden /> Print
                </Button>
            </div>

            <div className="card-sheet mx-auto flex max-w-[160mm] flex-wrap justify-center gap-[6mm] pb-[10mm] print:block print:max-w-none print:gap-0 print:p-0">
                {cards.map((card) => (
                    <div key={card.key} className="card-pair flex gap-[3mm]">
                        <Front card={card} school={school} />
                        <Back card={card} school={school} />
                    </div>
                ))}
            </div>
        </div>
    );
}
