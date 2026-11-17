import {
    CheckCircle2,
    FileSpreadsheet,
    KeyRound,
    PlugZap,
    ShieldCheck,
    Webhook,
} from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import { securityPoints, toneTile, tour } from './content';
import { CountUp, Reveal } from './motion';
import { Container, SectionHeading } from './sections';

export type Facts = {
    modules: number;
    roles: number;
    permissions: number;
    apiEndpoints: number;
    importers: number;
    webhookEvents: number;
};

/**
 * Tabs following the WAI-ARIA pattern: arrow keys, Home and End move between tabs.
 */
export function FeatureTour() {
    const [active, setActive] = useState(0);
    const tabs = useRef<(HTMLButtonElement | null)[]>([]);
    const current = tour[active];

    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>): void => {
        const last = tour.length - 1;
        const next =
            event.key === 'ArrowRight' || event.key === 'ArrowDown'
                ? active === last
                    ? 0
                    : active + 1
                : event.key === 'ArrowLeft' || event.key === 'ArrowUp'
                  ? active === 0
                      ? last
                      : active - 1
                  : event.key === 'Home'
                    ? 0
                    : event.key === 'End'
                      ? last
                      : null;

        if (next !== null) {
            event.preventDefault();
            setActive(next);
            tabs.current[next]?.focus();
        }
    };

    return (
        <section
            id="features"
            aria-labelledby="features-title"
            className="scroll-mt-20 py-20 md:py-28"
        >
            <Container>
                <SectionHeading
                    id="features-title"
                    eyebrow="Feature tour"
                    title="See the everyday workflows, end to end."
                >
                    Pick a workflow to see how one action flows through the
                    whole system.
                </SectionHeading>

                <Reveal className="mt-12 grid gap-8 lg:grid-cols-[18rem_1fr]">
                    <div
                        role="tablist"
                        aria-label="Workflows"
                        aria-orientation="vertical"
                        onKeyDown={onKeyDown}
                        className="flex gap-2 overflow-x-auto pb-2 lg:flex-col lg:overflow-visible lg:pb-0"
                    >
                        {tour.map((item, index) => {
                            const selected = index === active;

                            return (
                                <button
                                    key={item.id}
                                    ref={(element) => {
                                        tabs.current[index] = element;
                                    }}
                                    type="button"
                                    role="tab"
                                    id={`tour-tab-${item.id}`}
                                    aria-selected={selected}
                                    aria-controls={`tour-panel-${item.id}`}
                                    tabIndex={selected ? 0 : -1}
                                    onClick={() => setActive(index)}
                                    className={cn(
                                        'flex shrink-0 items-center gap-3 rounded-2xl border px-4 py-3 text-left text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        selected
                                            ? 'border-navy-200 bg-card shadow-md dark:border-white/15'
                                            : 'border-transparent text-muted-foreground hover:bg-muted',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'flex size-9 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow',
                                            toneTile[item.tone],
                                            !selected && 'opacity-70',
                                        )}
                                    >
                                        <item.icon
                                            className="size-4"
                                            aria-hidden
                                        />
                                    </span>
                                    {item.label}
                                </button>
                            );
                        })}
                    </div>

                    <div
                        role="tabpanel"
                        id={`tour-panel-${current.id}`}
                        aria-labelledby={`tour-tab-${current.id}`}
                        tabIndex={0}
                        className="relative overflow-hidden rounded-3xl border bg-card p-8 shadow-lg shadow-navy-900/5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none md:p-10"
                    >
                        <div
                            className={cn(
                                'pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-gradient-to-br opacity-15 blur-2xl',
                                toneTile[current.tone],
                            )}
                            aria-hidden
                        />
                        <div
                            key={current.id}
                            className="relative animate-in duration-500 fade-in slide-in-from-bottom-2"
                        >
                            <h3 className="text-2xl font-bold tracking-tight">
                                {current.title}
                            </h3>
                            <p className="mt-3 max-w-2xl text-muted-foreground">
                                {current.text}
                            </p>
                            <ul className="mt-6 grid gap-3 sm:grid-cols-2">
                                {current.points.map((point) => (
                                    <li
                                        key={point}
                                        className="flex gap-3 rounded-2xl bg-muted/60 p-4 text-sm"
                                    >
                                        <CheckCircle2
                                            className="mt-0.5 size-4 shrink-0 text-emerald-500"
                                            aria-hidden
                                        />
                                        {point}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </Reveal>
            </Container>
        </section>
    );
}

export function Security() {
    return (
        <section
            id="security"
            aria-labelledby="security-title"
            className="scroll-mt-20 py-20 md:py-28"
        >
            <Container>
                <Reveal className="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-navy-900 via-navy-800 to-royal-700 p-8 text-white shadow-2xl shadow-navy-900/25 md:p-14">
                    <div
                        className="pointer-events-none absolute -top-24 -right-24 size-96 rounded-full bg-gold-400/20 blur-3xl"
                        aria-hidden
                    />
                    <div className="relative grid gap-10 lg:grid-cols-2 lg:items-center">
                        <div>
                            <span className="inline-flex rounded-2xl bg-white/10 p-3 ring-1 ring-white/20">
                                <ShieldCheck
                                    className="size-6 text-gold-300"
                                    aria-hidden
                                />
                            </span>
                            <h2
                                id="security-title"
                                className="mt-5 text-3xl font-bold tracking-tight text-balance sm:text-4xl"
                            >
                                Children’s data handled with the care it
                                deserves.
                            </h2>
                            <p className="mt-4 text-lg text-white/75">
                                Security isn’t an add-on. It is part of every
                                screen, every record and every sign-in.
                            </p>
                        </div>
                        <ul className="grid gap-3 sm:grid-cols-2">
                            {securityPoints.map((point) => (
                                <li
                                    key={point}
                                    className="flex gap-3 rounded-2xl border border-white/10 bg-white/[0.07] p-4 text-sm backdrop-blur"
                                >
                                    <KeyRound
                                        className="mt-0.5 size-4 shrink-0 text-gold-300"
                                        aria-hidden
                                    />
                                    {point}
                                </li>
                            ))}
                        </ul>
                    </div>
                </Reveal>
            </Container>
        </section>
    );
}

export function Integrations({ facts }: { facts: Facts }) {
    const items = [
        {
            icon: FileSpreadsheet,
            title: 'Excel & CSV import',
            text: `${facts.importers} ready-made templates with row-by-row checks before anything is saved.`,
        },
        {
            icon: PlugZap,
            title: 'REST API',
            text: `A versioned API (${facts.apiEndpoints} endpoints) with scoped tokens for mobile and partner apps.`,
        },
        {
            icon: Webhook,
            title: 'Signed webhooks',
            text: `${facts.webhookEvents} real-time events, signed and retried automatically when a delivery fails.`,
        },
    ];

    return (
        <section
            id="integrations"
            aria-labelledby="integrations-title"
            className="scroll-mt-20 py-20 md:py-28"
        >
            <Container>
                <SectionHeading
                    id="integrations-title"
                    eyebrow="Integrations"
                    title="Works with the data and tools you already have."
                />
                <div className="mt-14 grid gap-5 md:grid-cols-3">
                    {items.map((item, index) => (
                        <Reveal
                            key={item.title}
                            delay={index * 100}
                            className="rounded-3xl border bg-gradient-to-b from-card to-navy-50/40 p-7 shadow-sm dark:to-navy-500/5"
                        >
                            <item.icon
                                className="size-7 text-royal-600 dark:text-royal-300"
                                aria-hidden
                            />
                            <h3 className="mt-4 text-lg font-semibold">
                                {item.title}
                            </h3>
                            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                                {item.text}
                            </p>
                        </Reveal>
                    ))}
                </div>
            </Container>
        </section>
    );
}

export function Stats({ facts }: { facts: Facts }) {
    const stats = [
        {
            value: facts.modules,
            label: 'Modules',
            hint: 'with their own permissions',
        },
        {
            value: facts.permissions,
            label: 'Permissions',
            hint: 'for fine-grained access',
        },
        {
            value: facts.roles,
            label: 'Built-in roles',
            hint: 'ready from day one',
        },
        {
            value: facts.apiEndpoints,
            label: 'API endpoints',
            hint: 'for apps and partners',
        },
    ];

    return (
        <section aria-labelledby="stats-title" className="py-10">
            <Container>
                <h2 id="stats-title" className="sr-only">
                    The platform in numbers
                </h2>
                <Reveal className="grid overflow-hidden rounded-3xl border bg-card shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                    {stats.map((stat) => (
                        <div
                            key={stat.label}
                            className="border-b p-8 text-center last:border-b-0 sm:border-r lg:border-b-0 lg:last:border-r-0 sm:[&:nth-child(2n)]:border-r-0 lg:[&:nth-child(2n)]:border-r"
                        >
                            <p className="bg-gradient-to-br from-navy-700 to-royal-500 bg-clip-text text-5xl font-bold tracking-tight text-transparent dark:from-royal-200 dark:to-sky-300">
                                <CountUp value={stat.value} />
                            </p>
                            <p className="mt-2 font-semibold">{stat.label}</p>
                            <p className="text-sm text-muted-foreground">
                                {stat.hint}
                            </p>
                        </div>
                    ))}
                </Reveal>
                <p className="mt-3 text-center text-xs text-muted-foreground">
                    Counted live from the software itself.
                </p>
            </Container>
        </section>
    );
}
