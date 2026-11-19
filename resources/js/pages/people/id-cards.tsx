import {
    BriefcaseBusiness,
    ExternalLink,
    GraduationCap,
    HeartHandshake,
    Printer,
    Search,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { index, print, search } from '@/routes/people/id-cards';
import type { SectionOption } from '@/types';

type CardType = 'students' | 'staff' | 'parents';

type Match = {
    id: number;
    name: string;
    number: string;
    detail: string | null;
};

type Props = {
    can: { students: boolean; staff: boolean; parents: boolean };
    sections: SectionOption[];
    staffCount: number;
};

function Panel({
    icon: Icon,
    title,
    text,
    tone,
    children,
}: {
    icon: LucideIcon;
    title: string;
    text: string;
    tone: string;
    children: React.ReactNode;
}) {
    return (
        <section className="flex flex-col rounded-2xl border bg-card p-6 shadow-sm">
            <span
                className={`mb-4 inline-flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br text-white shadow-lg ${tone}`}
            >
                <Icon className="size-6" aria-hidden />
            </span>
            <h2 className="text-lg font-semibold">{title}</h2>
            <p className="mt-1 mb-5 text-sm text-muted-foreground">{text}</p>
            <div className="mt-auto space-y-3">{children}</div>
        </section>
    );
}

function SectionPicker({
    id,
    sections,
    value,
    onChange,
}: {
    id: string;
    sections: SectionOption[];
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>Class and section</Label>
            <NativeSelect
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
            >
                <option value="">Choose…</option>
                {sections.map((section) => (
                    <option key={section.id} value={section.id}>
                        {section.name}
                    </option>
                ))}
            </NativeSelect>
        </div>
    );
}

const typeLabels: Record<CardType, string> = {
    students: 'Student',
    staff: 'Staff',
    parents: 'Parent',
};

const openCards = (query: Record<string, string>): void => {
    window.open(print.url({ query }), '_blank', 'noopener');
};

/**
 * Find one person by name or number and open just their card.
 */
function SingleCardFinder({ types }: { types: CardType[] }) {
    const [type, setType] = useState<CardType>(types[0]);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Match[]>([]);
    const [searching, setSearching] = useState(false);
    const term = query.trim();

    useEffect(() => {
        if (term.length < 2) {
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            setSearching(true);
            fetch(search.url({ query: { type, q: term } }), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then((response) => (response.ok ? response.json() : []))
                .then((data: Match[]) => setResults(data))
                .catch(() => undefined)
                .finally(() => setSearching(false));
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [term, type]);

    const shown = term.length < 2 ? [] : results;

    return (
        <section className="rounded-2xl border bg-card p-6 shadow-sm">
            <div className="flex flex-col gap-1">
                <h2 className="text-lg font-semibold">One person’s card</h2>
                <p className="text-sm text-muted-foreground">
                    Search by name or number and open that card on its own, to
                    check it or print a replacement.
                </p>
            </div>
            <div className="mt-4 flex flex-col gap-3 sm:flex-row">
                <div className="grid gap-1.5 sm:w-40">
                    <Label htmlFor="single-type">Card type</Label>
                    <NativeSelect
                        id="single-type"
                        value={type}
                        onChange={(event) => {
                            setType(event.target.value as CardType);
                            setResults([]);
                        }}
                    >
                        {types.map((value) => (
                            <option key={value} value={value}>
                                {typeLabels[value]}
                            </option>
                        ))}
                    </NativeSelect>
                </div>
                <div className="grid flex-1 gap-1.5">
                    <Label htmlFor="single-search">Name or number</Label>
                    <div className="relative">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden
                        />
                        <Input
                            id="single-search"
                            className="pl-9"
                            value={query}
                            placeholder={
                                type === 'students'
                                    ? 'e.g. Aiden or GHS250012'
                                    : type === 'staff'
                                      ? 'e.g. Lucas or staff number'
                                      : 'e.g. parent name or email'
                            }
                            onChange={(event) => setQuery(event.target.value)}
                            autoComplete="off"
                        />
                    </div>
                </div>
            </div>
            {term.length >= 2 && (
                <ul className="mt-4 divide-y rounded-xl border">
                    {shown.map((match) => (
                        <li
                            key={match.id}
                            className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                        >
                            <div className="min-w-0">
                                <p className="truncate font-medium">
                                    {match.name}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    <span className="font-mono">
                                        {match.number}
                                    </span>
                                    {match.detail ? ` · ${match.detail}` : ''}
                                </p>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    openCards({
                                        type,
                                        'ids[]': String(match.id),
                                    })
                                }
                            >
                                <ExternalLink aria-hidden /> Open card
                            </Button>
                        </li>
                    ))}
                    {shown.length === 0 && (
                        <li className="px-4 py-3 text-sm text-muted-foreground">
                            {searching ? 'Searching…' : 'No one matches that.'}
                        </li>
                    )}
                </ul>
            )}
        </section>
    );
}

export default function IdCardsIndex({ can, sections, staffCount }: Props) {
    const [studentSection, setStudentSection] = useState('');
    const [parentSection, setParentSection] = useState('');
    const [teachingOnly, setTeachingOnly] = useState(false);
    const open = openCards;
    const types = (['students', 'staff', 'parents'] as const).filter(
        (value) => can[value],
    );

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="ID cards"
                description="Print real-size ID cards (54 × 85.6 mm) with a QR code that anyone can scan to check the card is genuine. Print one person’s card from their profile, or a whole group here."
            />
            {types.length > 0 && <SingleCardFinder types={[...types]} />}
            <div className="grid gap-5 lg:grid-cols-3">
                {can.students && (
                    <Panel
                        icon={GraduationCap}
                        title="Students"
                        text="Photo, admission number, class, blood group and an emergency contact."
                        tone="from-navy-700 to-royal-500"
                    >
                        <SectionPicker
                            id="student-section"
                            sections={sections}
                            value={studentSection}
                            onChange={setStudentSection}
                        />
                        <Button
                            className="w-full"
                            disabled={studentSection === ''}
                            onClick={() =>
                                open({
                                    type: 'students',
                                    section_id: studentSection,
                                })
                            }
                        >
                            <Printer aria-hidden /> Print section’s cards
                        </Button>
                    </Panel>
                )}
                {can.parents && (
                    <Panel
                        icon={HeartHandshake}
                        title="Parents"
                        text="The parent’s details on the front and every child at the school on the back, for school pick-up."
                        tone="from-crimson-600 to-rose-500"
                    >
                        <SectionPicker
                            id="parent-section"
                            sections={sections}
                            value={parentSection}
                            onChange={setParentSection}
                        />
                        <Button
                            className="w-full"
                            disabled={parentSection === ''}
                            onClick={() =>
                                open({
                                    type: 'parents',
                                    section_id: parentSection,
                                })
                            }
                        >
                            <Printer aria-hidden /> Print parents’ cards
                        </Button>
                    </Panel>
                )}
                {can.staff && (
                    <Panel
                        icon={BriefcaseBusiness}
                        title="Staff"
                        text={`Designation, department and staff number for all ${staffCount} active staff.`}
                        tone="from-emerald-600 to-teal-500"
                    >
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="teaching-only"
                                checked={teachingOnly}
                                onCheckedChange={(checked) =>
                                    setTeachingOnly(checked === true)
                                }
                            />
                            <Label
                                htmlFor="teaching-only"
                                className="font-normal"
                            >
                                Teaching staff only
                            </Label>
                        </div>
                        <Button
                            className="w-full"
                            onClick={() =>
                                open({
                                    type: 'staff',
                                    ...(teachingOnly ? { teaching: '1' } : {}),
                                })
                            }
                        >
                            <Printer aria-hidden /> Print staff cards
                        </Button>
                    </Panel>
                )}
            </div>
            <p className="text-sm text-muted-foreground">
                Lost a card? Use <strong>Reissue card</strong> on the person’s
                page: the old card’s QR code stops verifying straight away.
            </p>
        </div>
    );
}

IdCardsIndex.layout = {
    breadcrumbs: [{ title: 'ID cards', href: index() }],
};
