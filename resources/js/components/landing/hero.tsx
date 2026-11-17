import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { cn } from '@/lib/utils';
import { dashboard, login } from '@/routes';
import { Reveal } from './motion';

/* -------------------------------------------------------------- hand-drawn bits */

/** A teacher's red-pen tick. */
function PenTick({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden
            className={cn('size-5 shrink-0', className)}
        >
            <path
                d="M4 13.5c2 1.2 3.4 2.8 4.6 5C11 12 15 7 20.5 4"
                stroke="currentColor"
                strokeWidth="2.6"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/** Highlighter stroke drawn under the key word. */
function MarkerUnderline() {
    return (
        <svg
            viewBox="0 0 300 24"
            preserveAspectRatio="none"
            aria-hidden
            className="absolute -bottom-2 left-0 -z-10 h-4 w-full text-gold-400 md:-bottom-3 md:h-5 dark:text-gold-500/80"
        >
            <path
                className="landing-draw"
                d="M4 15c48-7 98-10 150-8 50 2 96 4 142-3"
                stroke="currentColor"
                strokeWidth="9"
                strokeLinecap="round"
                fill="none"
            />
        </svg>
    );
}

/** A gold merit star sticker. */
function StarSticker({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 48 48" aria-hidden className={className}>
            <circle cx="24" cy="24" r="22" className="fill-gold-400" />
            <circle
                cx="24"
                cy="24"
                r="19"
                className="fill-none stroke-gold-200"
                strokeWidth="1.5"
                strokeDasharray="2 3"
            />
            <path
                d="M24 11l3.8 8 8.7 1.1-6.4 6 1.7 8.6L24 30.4l-7.8 4.3 1.7-8.6-6.4-6 8.7-1.1z"
                className="fill-white"
            />
        </svg>
    );
}

/* ---------------------------------------------------------------- desk collage */

const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

const subjectTone = {
    Maths: 'bg-navy-100 text-navy-800 dark:bg-navy-500/25 dark:text-navy-100',
    English:
        'bg-crimson-100 text-crimson-800 dark:bg-crimson-500/25 dark:text-crimson-100',
    Science:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/25 dark:text-emerald-100',
    History: 'bg-gold-100 text-gold-900 dark:bg-gold-500/25 dark:text-gold-100',
    Art: 'bg-sky-100 text-sky-800 dark:bg-sky-500/25 dark:text-sky-100',
    PE: 'bg-orange-100 text-orange-800 dark:bg-orange-500/25 dark:text-orange-100',
} as const;

type Subject = keyof typeof subjectTone;

const timetable: { time: string; lessons: Subject[] }[] = [
    { time: '08:00', lessons: ['Maths', 'English', 'Science', 'Maths', 'Art'] },
    {
        time: '08:45',
        lessons: ['English', 'Maths', 'History', 'Science', 'English'],
    },
    { time: '09:30', lessons: ['Science', 'PE', 'Maths', 'English', 'Maths'] },
    { time: '10:30', lessons: ['History', 'Art', 'English', 'PE', 'Science'] },
];

const register: ('P' | 'A' | 'L')[] = ['P', 'P', 'A', 'P', 'L', 'P'];

function DeskCollage() {
    return (
        <figure className="relative mx-auto h-[33rem] w-full max-w-[34rem] sm:h-[35rem]">
            {/* Timetable sheet */}
            <Reveal
                delay={120}
                className="absolute top-16 left-0 w-[86%] sm:top-20 sm:w-[80%]"
            >
                <div className="-rotate-2 rounded-2xl border border-navy-900/10 bg-white p-4 shadow-[0_30px_60px_-25px_rgba(16,24,64,0.35)] transition duration-500 hover:rotate-0 sm:p-5 dark:border-white/10 dark:bg-navy-900">
                    <div className="mb-3 flex items-baseline justify-between">
                        <p className="font-display text-2xl text-navy-950 dark:text-white">
                            Timetable
                        </p>
                        <p className="text-[11px] font-medium tracking-wider text-muted-foreground uppercase">
                            Week A
                        </p>
                    </div>
                    <div className="grid grid-cols-[2.6rem_repeat(5,1fr)] gap-1 text-center text-[10px] sm:gap-1.5 sm:text-[11px]">
                        <span />
                        {days.map((day) => (
                            <span
                                key={day}
                                className="pb-1 font-semibold text-muted-foreground"
                            >
                                {day}
                            </span>
                        ))}
                        {timetable.map((row) => (
                            <div key={row.time} className="contents">
                                <span className="self-center text-left text-muted-foreground tabular-nums">
                                    {row.time}
                                </span>
                                {row.lessons.map((lesson, index) => (
                                    <span
                                        key={index}
                                        className={cn(
                                            'truncate rounded-md px-1 py-2 font-medium sm:py-2.5',
                                            subjectTone[lesson],
                                        )}
                                    >
                                        {lesson}
                                    </span>
                                ))}
                            </div>
                        ))}
                    </div>
                </div>
            </Reveal>

            {/* Sticky note */}
            <Reveal
                delay={420}
                className="absolute -top-4 right-0 w-36 sm:-top-6 sm:-right-4 sm:w-40"
            >
                <div className="landing-sway relative rotate-[4deg] bg-gold-200 p-4 pt-5 shadow-[0_18px_30px_-15px_rgba(120,80,0,0.45)] dark:bg-gold-300">
                    <span
                        className="absolute -top-2.5 left-1/2 h-5 w-14 -translate-x-1/2 -rotate-3 bg-white/60 dark:bg-white/40"
                        aria-hidden
                    />
                    <p className="font-display text-xl leading-snug text-navy-950 italic">
                        Parents’ evening, Thursday
                    </p>
                    <p className="mt-2 text-[11px] font-medium text-navy-900/70">
                        Announced to Class 7
                    </p>
                </div>
            </Reveal>

            {/* Register strip */}
            <Reveal
                delay={300}
                className="absolute right-0 bottom-36 w-52 sm:-right-4 sm:bottom-40 sm:w-56"
            >
                <div className="rotate-3 rounded-xl border border-navy-900/10 bg-white p-3.5 shadow-[0_24px_40px_-20px_rgba(16,24,64,0.4)] transition duration-500 hover:rotate-0 dark:border-white/10 dark:bg-navy-900">
                    <div className="flex items-center justify-between">
                        <p className="text-xs font-semibold">Register</p>
                        <p className="text-[10px] text-muted-foreground">
                            Period 1
                        </p>
                    </div>
                    <div className="mt-2.5 grid grid-cols-6 gap-1.5">
                        {register.map((mark, index) => (
                            <div key={index} className="text-center">
                                <p className="text-[9px] text-muted-foreground tabular-nums">
                                    {String(index + 1).padStart(2, '0')}
                                </p>
                                <span
                                    className={cn(
                                        'mt-0.5 flex aspect-square items-center justify-center rounded-full text-[11px] font-bold',
                                        mark === 'P' &&
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200',
                                        mark === 'A' &&
                                            'bg-crimson-100 text-crimson-700 dark:bg-crimson-500/20 dark:text-crimson-200',
                                        mark === 'L' &&
                                            'bg-gold-100 text-gold-800 dark:bg-gold-500/20 dark:text-gold-200',
                                    )}
                                >
                                    {mark}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </Reveal>

            {/* Report card slip */}
            <Reveal
                delay={520}
                className="absolute bottom-0 left-0 w-52 sm:left-2 sm:w-56"
            >
                <div className="relative -rotate-[5deg] rounded-xl border border-navy-900/10 bg-[oklch(0.99_0.012_88)] p-4 shadow-[0_24px_40px_-20px_rgba(16,24,64,0.4)] transition duration-500 hover:rotate-0 dark:border-white/10 dark:bg-navy-800">
                    <StarSticker className="absolute -top-5 -right-4 size-12 rotate-12 drop-shadow-md" />
                    <p className="font-display text-lg text-navy-950 dark:text-white">
                        Term report
                    </p>
                    <dl className="mt-2 space-y-1.5 text-xs">
                        {[
                            ['Mathematics', 'A'],
                            ['English', 'B+'],
                            ['Science', 'A−'],
                        ].map(([subject, grade]) => (
                            <div
                                key={subject}
                                className="flex items-center justify-between border-b border-dashed border-navy-900/15 pb-1 dark:border-white/15"
                            >
                                <dt className="text-muted-foreground">
                                    {subject}
                                </dt>
                                <dd className="font-display text-base text-crimson-600 italic dark:text-crimson-300">
                                    {grade}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>
            </Reveal>

            <figcaption className="sr-only">
                Illustration: a weekly timetable, a class register, a sticky
                note about parents’ evening and a term report, the everyday
                paperwork the system brings together.
            </figcaption>
        </figure>
    );
}

/* ---------------------------------------------------------------------- hero */

const promises = [
    'Encrypted student records',
    'Two-factor sign-in',
    'Bring your data from Excel',
];

export default function Hero({ modules }: { modules: number }) {
    const { auth } = usePage().props;

    return (
        <section
            id="top"
            aria-labelledby="hero-title"
            className="relative overflow-hidden bg-[oklch(0.985_0.014_88)] pt-28 pb-24 md:pt-36 md:pb-32 dark:bg-[oklch(0.2_0.035_200)]"
        >
            {/* Exercise-book paper (a chalkboard in dark mode) */}
            <div aria-hidden className="pointer-events-none absolute inset-0">
                <div className="absolute inset-0 bg-[repeating-linear-gradient(to_bottom,transparent_0,transparent_39px,color-mix(in_oklch,var(--color-sky-500)_20%,transparent)_39px,color-mix(in_oklch,var(--color-sky-500)_20%,transparent)_40px)] [mask-image:linear-gradient(to_bottom,black_55%,transparent)] dark:bg-[repeating-linear-gradient(to_bottom,transparent_0,transparent_39px,rgb(255_255_255/0.05)_39px,rgb(255_255_255/0.05)_40px)]" />
                <div className="absolute inset-y-0 left-10 w-px bg-crimson-400/45 sm:left-16 lg:left-24 dark:bg-crimson-300/25" />
                <div className="absolute inset-y-0 left-11 w-px bg-crimson-400/25 sm:left-[4.25rem] lg:left-[6.25rem] dark:bg-crimson-300/15" />
                <div className="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-b from-transparent to-background" />
                <div className="absolute top-40 left-3 hidden flex-col gap-28 sm:flex lg:left-7">
                    {[0, 1, 2].map((hole) => (
                        <span
                            key={hole}
                            className="size-5 rounded-full bg-background shadow-[inset_0_2px_4px_rgba(0,0,0,0.12)] dark:bg-navy-950"
                        />
                    ))}
                </div>
            </div>

            <div className="relative mx-auto grid max-w-7xl items-center gap-16 pr-4 pl-14 sm:pr-6 sm:pl-24 lg:grid-cols-[1.15fr_1fr] lg:pr-8 lg:pl-36">
                <Reveal>
                    <p className="inline-flex items-center gap-2 text-sm font-semibold text-crimson-700 dark:text-crimson-300">
                        <span className="inline-block h-6 w-3 bg-crimson-600 [clip-path:polygon(0_0,100%_0,100%_100%,50%_75%,0_100%)] dark:bg-crimson-400" />
                        School management ERP · {modules} modules
                    </p>

                    <h1
                        id="hero-title"
                        className="mt-6 font-display text-[3.4rem] leading-[0.95] tracking-tight text-navy-950 sm:text-7xl lg:text-[4.6rem] xl:text-[5.4rem] dark:text-white"
                    >
                        <span className="whitespace-nowrap">
                            Less paperwork.
                        </span>
                        <br />
                        More{' '}
                        <span className="relative isolate inline-block italic">
                            teaching.
                            <MarkerUnderline />
                        </span>
                    </h1>

                    <p className="mt-8 max-w-lg text-lg leading-relaxed text-pretty text-navy-900/75 dark:text-white/75">
                        One connected system for admissions, registers,
                        timetables, exams, fees and parent messages, so your
                        staff spend their day with students instead of
                        spreadsheets.
                    </p>

                    <div className="mt-10 flex flex-wrap items-center gap-x-8 gap-y-5">
                        <a
                            href="#contact"
                            className="group inline-flex items-center gap-2 rounded-lg bg-navy-900 px-7 py-3.5 font-semibold text-white shadow-[5px_5px_0_var(--color-gold-400)] transition hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-[3px_3px_0_var(--color-gold-400)] focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none dark:bg-gold-300 dark:text-navy-950 dark:shadow-[5px_5px_0_var(--color-crimson-500)]"
                        >
                            Book a demo
                            <ArrowRight
                                className="size-4 transition group-hover:translate-x-0.5"
                                aria-hidden
                            />
                        </a>
                        <Link
                            href={auth.user ? dashboard() : login()}
                            className="font-semibold text-navy-900 underline decoration-gold-400 decoration-2 underline-offset-[6px] transition hover:decoration-navy-900 dark:text-white"
                        >
                            {auth.user ? 'Open your dashboard' : 'Sign in'}
                        </Link>
                    </div>

                    <ul className="mt-12 grid gap-2.5 text-sm text-navy-900/80 sm:grid-cols-3 sm:gap-4 dark:text-white/80">
                        {promises.map((promise) => (
                            <li
                                key={promise}
                                className="flex items-start gap-2"
                            >
                                <PenTick className="text-crimson-600 dark:text-crimson-300" />
                                {promise}
                            </li>
                        ))}
                    </ul>
                </Reveal>

                <DeskCollage />
            </div>
        </section>
    );
}
