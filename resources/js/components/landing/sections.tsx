import { CheckCircle2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import {
    audiences,
    modules,
    reasons,
    steps,
    toneSoft,
    toneTile,
    useCases,
    values,
} from './content';
import { Reveal } from './motion';

export function SectionHeading({
    id,
    eyebrow,
    title,
    children,
    align = 'center',
}: {
    id: string;
    eyebrow: string;
    title: ReactNode;
    children?: ReactNode;
    align?: 'center' | 'left';
}) {
    return (
        <Reveal
            className={cn(
                'max-w-3xl',
                align === 'center' && 'mx-auto text-center',
            )}
        >
            <p className="text-sm font-semibold tracking-wider text-royal-600 uppercase dark:text-royal-300">
                {eyebrow}
            </p>
            <h2
                id={id}
                className="mt-3 text-3xl font-bold tracking-tight text-balance text-navy-950 sm:text-4xl dark:text-white"
            >
                {title}
            </h2>
            {children && (
                <p className="mt-4 text-lg leading-relaxed text-pretty text-muted-foreground">
                    {children}
                </p>
            )}
        </Reveal>
    );
}

export function Container({
    className,
    children,
}: {
    className?: string;
    children: ReactNode;
}) {
    return (
        <div
            className={cn('mx-auto max-w-7xl px-4 sm:px-6 lg:px-8', className)}
        >
            {children}
        </div>
    );
}

export function About() {
    return (
        <section aria-labelledby="about-title" className="py-20 md:py-28">
            <Container className="grid gap-12 lg:grid-cols-[1fr_1.2fr] lg:items-center">
                <SectionHeading
                    id="about-title"
                    eyebrow="Why we built it"
                    title="Schools deserve software as thoughtful as their teaching."
                    align="left"
                >
                    Most schools juggle separate spreadsheets, registers and
                    ledgers that never quite agree. This ERP replaces them with
                    one connected system, so leaders see the whole picture,
                    staff spend their time on students, and families always know
                    where they stand.
                </SectionHeading>
                <div className="grid gap-4">
                    {values.map((value, index) => (
                        <Reveal
                            key={value.title}
                            delay={index * 100}
                            className="flex gap-4 rounded-2xl border bg-card/80 p-5 shadow-sm backdrop-blur transition hover:shadow-md"
                        >
                            <span
                                className={cn(
                                    'flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-lg',
                                    toneTile[value.tone],
                                )}
                            >
                                <value.icon className="size-5" aria-hidden />
                            </span>
                            <div>
                                <h3 className="font-semibold">{value.title}</h3>
                                <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                                    {value.text}
                                </p>
                            </div>
                        </Reveal>
                    ))}
                </div>
            </Container>
        </section>
    );
}

export function Modules({ count }: { count: number }) {
    return (
        <section
            id="modules"
            aria-labelledby="modules-title"
            className="scroll-mt-20 bg-gradient-to-b from-navy-50/60 to-transparent py-20 md:py-28 dark:from-navy-500/5"
        >
            <Container>
                <SectionHeading
                    id="modules-title"
                    eyebrow="Modules"
                    title="Everything your school runs on, already connected."
                >
                    {count} permission-controlled modules share one set of
                    records, so an admission becomes an enrolment, a fee becomes
                    a ledger entry, and an absence reaches the right parent
                    automatically.
                </SectionHeading>
                <div className="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {modules.map((module, index) => (
                        <Reveal
                            key={module.title}
                            as="article"
                            delay={(index % 3) * 90}
                            className="group relative overflow-hidden rounded-3xl border bg-card p-6 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-navy-900/5"
                        >
                            <div
                                className="pointer-events-none absolute -top-16 -right-16 size-40 rounded-full bg-gradient-to-br from-royal-100 to-transparent opacity-0 transition group-hover:opacity-100 dark:from-royal-500/15"
                                aria-hidden
                            />
                            <span
                                className={cn(
                                    'relative flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br text-white shadow-lg',
                                    toneTile[module.tone],
                                )}
                            >
                                <module.icon className="size-5" aria-hidden />
                            </span>
                            <h3 className="relative mt-5 text-lg font-semibold">
                                {module.title}
                            </h3>
                            <p className="relative mt-1 text-sm text-muted-foreground">
                                {module.text}
                            </p>
                            <ul className="relative mt-4 space-y-2 text-sm">
                                {module.points.map((point) => (
                                    <li key={point} className="flex gap-2">
                                        <CheckCircle2
                                            className="mt-0.5 size-4 shrink-0 text-emerald-500"
                                            aria-hidden
                                        />
                                        <span>{point}</span>
                                    </li>
                                ))}
                            </ul>
                        </Reveal>
                    ))}
                </div>
            </Container>
        </section>
    );
}

export function WhyUs() {
    return (
        <section aria-labelledby="why-title" className="py-20 md:py-28">
            <Container>
                <SectionHeading
                    id="why-title"
                    eyebrow="Why choose us"
                    title="Designed around how schools actually work."
                >
                    Approvals, privacy and the people involved in every task are
                    built in from the start, not added later.
                </SectionHeading>
                <div className="mt-14 grid gap-x-10 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                    {reasons.map((reason, index) => (
                        <Reveal
                            key={reason.title}
                            delay={(index % 3) * 90}
                            className="relative pl-14"
                        >
                            <span className="absolute top-0 left-0 flex size-10 items-center justify-center rounded-xl bg-navy-50 text-navy-700 ring-1 ring-navy-100 dark:bg-navy-500/15 dark:text-navy-200 dark:ring-navy-500/20">
                                <reason.icon className="size-5" aria-hidden />
                            </span>
                            <h3 className="font-semibold">{reason.title}</h3>
                            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                                {reason.text}
                            </p>
                        </Reveal>
                    ))}
                </div>
            </Container>
        </section>
    );
}

export function HowItWorks() {
    return (
        <section
            id="how-it-works"
            aria-labelledby="how-title"
            className="relative scroll-mt-20 overflow-hidden bg-navy-950 py-20 text-white md:py-28"
        >
            <div aria-hidden className="pointer-events-none absolute inset-0">
                <div className="absolute -top-40 left-1/4 size-[36rem] rounded-full bg-royal-600/30 blur-3xl" />
                <div className="absolute -right-20 bottom-0 size-[28rem] rounded-full bg-gold-500/15 blur-3xl" />
            </div>
            <Container className="relative">
                <Reveal className="mx-auto max-w-3xl text-center">
                    <p className="text-sm font-semibold tracking-wider text-gold-300 uppercase">
                        How it works
                    </p>
                    <h2
                        id="how-title"
                        className="mt-3 text-3xl font-bold tracking-tight text-balance sm:text-4xl"
                    >
                        Up and running in four clear steps.
                    </h2>
                    <p className="mt-4 text-lg text-white/75">
                        A built-in setup checklist tracks your progress, so
                        nothing important is missed.
                    </p>
                </Reveal>
                <ol className="mt-16 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    {steps.map((step, index) => (
                        <Reveal
                            key={step.title}
                            as="li"
                            delay={index * 120}
                            className="relative rounded-3xl border border-white/10 bg-white/[0.06] p-6 backdrop-blur-md"
                        >
                            <div className="flex items-center justify-between">
                                <span className="flex size-11 items-center justify-center rounded-2xl bg-gradient-to-br from-gold-300 to-gold-500 text-navy-950 shadow-lg shadow-gold-900/30">
                                    <step.icon className="size-5" aria-hidden />
                                </span>
                                <span
                                    className="text-4xl font-bold text-white/10"
                                    aria-hidden
                                >
                                    0{index + 1}
                                </span>
                            </div>
                            <h3 className="mt-5 font-semibold">
                                <span className="sr-only">
                                    Step {index + 1}:{' '}
                                </span>
                                {step.title}
                            </h3>
                            <p className="mt-2 text-sm leading-relaxed text-white/70">
                                {step.text}
                            </p>
                        </Reveal>
                    ))}
                </ol>
            </Container>
        </section>
    );
}

export function UseCases() {
    return (
        <section
            id="use-cases"
            aria-labelledby="use-cases-title"
            className="scroll-mt-20 py-20 md:py-28"
        >
            <Container>
                <SectionHeading
                    id="use-cases-title"
                    eyebrow="Who it’s for"
                    title="One system, shaped to every school and every role."
                />
                <div className="mt-14 grid gap-5 md:grid-cols-3">
                    {useCases.map((useCase, index) => (
                        <Reveal
                            key={useCase.title}
                            delay={index * 100}
                            className="rounded-3xl border bg-card p-7 shadow-sm"
                        >
                            <span
                                className={cn(
                                    'inline-flex rounded-xl p-2.5',
                                    toneSoft[useCase.tone],
                                )}
                            >
                                <useCase.icon className="size-5" aria-hidden />
                            </span>
                            <h3 className="mt-4 text-lg font-semibold">
                                {useCase.title}
                            </h3>
                            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                                {useCase.text}
                            </p>
                        </Reveal>
                    ))}
                </div>
                <Reveal className="mt-8 overflow-hidden rounded-3xl border bg-card shadow-sm">
                    <table className="w-full text-left text-sm">
                        <caption className="sr-only">
                            What each role gets
                        </caption>
                        <thead className="bg-gradient-to-r from-navy-50 via-royal-50 to-gold-50 text-xs tracking-wider text-navy-900/70 uppercase dark:from-navy-500/10 dark:via-royal-500/10 dark:to-gold-500/10 dark:text-navy-100/80">
                            <tr>
                                <th scope="col" className="px-6 py-3">
                                    Role
                                </th>
                                <th scope="col" className="px-6 py-3">
                                    What they get
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {audiences.map((audience) => (
                                <tr key={audience.role}>
                                    <th
                                        scope="row"
                                        className="px-6 py-4 font-semibold whitespace-nowrap"
                                    >
                                        {audience.role}
                                    </th>
                                    <td className="px-6 py-4 text-muted-foreground">
                                        {audience.gets}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Reveal>
            </Container>
        </section>
    );
}
