import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, Circle, Lightbulb } from 'lucide-react';
import { useState } from 'react';
import SearchInput from '@/components/data/search-input';
import type {
    GuideSection,
    GuideTone,
    GuideTopic,
    SetupKey,
} from '@/components/help/guide-content';
import {
    guideSections,
    setupIcon as SetupIcon,
    setupSteps,
} from '@/components/help/guide-content';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { cn } from '@/lib/utils';
import { help } from '@/routes';

type Props = {
    setup: Record<SetupKey, boolean> | null;
};

const toneTiles: Record<GuideTone, string> = {
    sky: 'from-sky-500 to-cyan-600 shadow-sky-500/25',
    emerald: 'from-emerald-500 to-teal-600 shadow-emerald-500/25',
    amber: 'from-amber-500 to-orange-600 shadow-orange-500/25',
    pink: 'from-gold-500 to-crimson-600 shadow-gold-500/25',
    violet: 'from-royal-500 to-navy-600 shadow-navy-500/25',
};

const toneAccents: Record<GuideTone, string> = {
    sky: 'bg-sky-500',
    emerald: 'bg-emerald-500',
    amber: 'bg-amber-500',
    pink: 'bg-gold-500',
    violet: 'bg-royal-500',
};

function topicMatches(topic: GuideTopic, query: string): boolean {
    const haystack = [topic.title, topic.tip ?? '', ...topic.steps]
        .join(' ')
        .toLowerCase();

    return query
        .toLowerCase()
        .split(/\s+/)
        .filter(Boolean)
        .every((word) => haystack.includes(word));
}

function SetupChecklist({ setup }: { setup: Record<SetupKey, boolean> }) {
    const tracked = setupSteps.filter((step) => step.key !== null);
    const done = tracked.filter((step) => step.key && setup[step.key]).length;
    const percent = Math.round((done / tracked.length) * 100);

    return (
        <section
            id="setup"
            aria-labelledby="setup-title"
            className="scroll-mt-20 overflow-hidden rounded-2xl border bg-card shadow-sm"
        >
            <div className="bg-gradient-to-br from-navy-600 to-royal-600 p-5 text-white">
                <div className="flex items-start gap-3">
                    <div className="rounded-xl bg-white/15 p-2.5">
                        <SetupIcon className="size-6" aria-hidden />
                    </div>
                    <div className="min-w-0 flex-1">
                        <h2 id="setup-title" className="text-lg font-semibold">
                            Set up your school
                        </h2>
                        <p className="text-sm text-white/85">
                            Work through these steps in order. Each one is
                            ticked off automatically once its data exists.
                        </p>
                    </div>
                    <p className="text-2xl font-bold tabular-nums">
                        {done}/{tracked.length}
                    </p>
                </div>
                <div
                    className="mt-4 h-2 overflow-hidden rounded-full bg-white/20"
                    role="progressbar"
                    aria-valuenow={percent}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-label="Setup progress"
                >
                    <div
                        className="h-full rounded-full bg-white transition-all"
                        style={{ width: `${percent}%` }}
                    />
                </div>
            </div>
            <ol className="divide-y">
                {setupSteps.map((step, index) => {
                    const complete = step.key !== null && setup[step.key];

                    return (
                        <li
                            key={step.title}
                            className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center"
                        >
                            <div className="flex min-w-0 flex-1 items-start gap-3">
                                {complete ? (
                                    <CheckCircle2
                                        className="mt-0.5 size-5 shrink-0 text-emerald-600"
                                        aria-label="Done"
                                    />
                                ) : (
                                    <Circle
                                        className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                        aria-label={
                                            step.key === null
                                                ? 'Check manually'
                                                : 'Not done yet'
                                        }
                                    />
                                )}
                                <div className="min-w-0">
                                    <p
                                        className={cn(
                                            'font-medium',
                                            complete &&
                                                'text-muted-foreground line-through decoration-emerald-600/40',
                                        )}
                                    >
                                        {index + 1}. {step.title}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {step.description}
                                    </p>
                                </div>
                            </div>
                            <Button
                                asChild
                                size="sm"
                                variant={complete ? 'ghost' : 'outline'}
                                className="self-start sm:self-center"
                            >
                                <Link href={step.link.href}>
                                    {step.link.label}
                                    <ArrowRight aria-hidden />
                                </Link>
                            </Button>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}

function TopicCard({ topic, tone }: { topic: GuideTopic; tone: GuideTone }) {
    return (
        <article className="flex flex-col rounded-xl border bg-card p-4 shadow-sm">
            <div className="flex items-center gap-2">
                <span
                    className={cn('size-2 rounded-full', toneAccents[tone])}
                    aria-hidden
                />
                <h3 className="font-semibold">{topic.title}</h3>
            </div>
            <ol className="mt-3 list-decimal space-y-1.5 pl-5 text-sm text-muted-foreground marker:font-medium marker:text-foreground">
                {topic.steps.map((step) => (
                    <li key={step}>{step}</li>
                ))}
            </ol>
            {topic.tip && (
                <p className="mt-3 flex gap-2 rounded-lg bg-amber-50 p-2.5 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    <Lightbulb className="mt-0.5 size-4 shrink-0" aria-hidden />
                    {topic.tip}
                </p>
            )}
            {topic.link && (
                <div className="mt-auto pt-3">
                    <Button asChild size="sm" variant="ghost" className="-ml-2">
                        <Link href={topic.link.href}>
                            {topic.link.label}
                            <ArrowRight aria-hidden />
                        </Link>
                    </Button>
                </div>
            )}
        </article>
    );
}

function SectionBlock({
    section,
    topics,
}: {
    section: GuideSection;
    topics: GuideTopic[];
}) {
    const Icon = section.icon;

    return (
        <section
            id={section.id}
            aria-labelledby={`${section.id}-title`}
            className="scroll-mt-20 space-y-3"
        >
            <div className="flex items-center gap-3">
                <div
                    className={cn(
                        'rounded-xl bg-gradient-to-br p-2.5 text-white shadow-lg',
                        toneTiles[section.tone],
                    )}
                >
                    <Icon className="size-5" aria-hidden />
                </div>
                <div>
                    <h2
                        id={`${section.id}-title`}
                        className="text-lg font-semibold"
                    >
                        {section.title}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {section.description}
                    </p>
                </div>
            </div>
            <div className="grid gap-3 md:grid-cols-2">
                {topics.map((topic) => (
                    <TopicCard
                        key={topic.title}
                        topic={topic}
                        tone={section.tone}
                    />
                ))}
            </div>
        </section>
    );
}

export default function Help({ setup }: Props) {
    const { auth } = usePage().props;
    const { canAny } = usePermissions();
    const [query, setQuery] = useState('');

    const visibleSections = guideSections.filter((section) =>
        section.visible({ canAny, isPortalUser: auth.isPortalUser }),
    );

    const filtered = visibleSections
        .map((section) => ({
            section,
            topics: query.trim()
                ? section.topics.filter((topic) => topicMatches(topic, query))
                : section.topics,
        }))
        .filter(({ topics }) => topics.length > 0);

    const showSetup = setup !== null && query.trim() === '';

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Help & guide"
                description="How to use the system, shown for your role. Start at the top and work down."
            />

            <div className="max-w-md">
                <SearchInput
                    value={query}
                    onChange={setQuery}
                    placeholder="Search the guide, e.g. attendance"
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-[14rem_minmax(0,1fr)]">
                <nav aria-label="Guide sections" className="hidden lg:block">
                    <ul className="sticky top-20 space-y-1 text-sm">
                        {showSetup && (
                            <li>
                                <a
                                    href="#setup"
                                    className="block rounded-md px-3 py-1.5 font-medium text-navy-700 hover:bg-muted dark:text-navy-300"
                                >
                                    Set up your school
                                </a>
                            </li>
                        )}
                        {filtered.map(({ section }) => (
                            <li key={section.id}>
                                <a
                                    href={`#${section.id}`}
                                    className="flex items-center gap-2 rounded-md px-3 py-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                >
                                    <span
                                        className={cn(
                                            'size-2 shrink-0 rounded-full',
                                            toneAccents[section.tone],
                                        )}
                                        aria-hidden
                                    />
                                    {section.title}
                                </a>
                            </li>
                        ))}
                    </ul>
                </nav>

                <div className="min-w-0 space-y-8">
                    {showSetup && <SetupChecklist setup={setup} />}

                    {filtered.length === 0 ? (
                        <p className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                            Nothing in the guide matches “{query}”. Try another
                            word.
                        </p>
                    ) : (
                        filtered.map(({ section, topics }) => (
                            <SectionBlock
                                key={section.id}
                                section={section}
                                topics={topics}
                            />
                        ))
                    )}
                </div>
            </div>
        </div>
    );
}

Help.layout = {
    breadcrumbs: [{ title: 'Help & guide', href: help() }],
};
