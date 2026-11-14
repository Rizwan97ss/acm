import { Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    ClipboardCheck,
    GraduationCap,
    ShieldCheck,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard, login } from '@/routes';

const features = [
    {
        icon: Users,
        title: 'Students & families',
        text: 'Admissions, enrolment, guardians and portal access in one place.',
        tone: 'from-emerald-500 to-teal-500',
    },
    {
        icon: CalendarDays,
        title: 'Timetables',
        text: 'Clash-free schedules for every section, teacher and room.',
        tone: 'from-amber-500 to-orange-500',
    },
    {
        icon: ClipboardCheck,
        title: 'Attendance & results',
        text: 'Daily attendance, examinations, grading and report cards.',
        tone: 'from-sky-500 to-cyan-500',
    },
    {
        icon: Wallet,
        title: 'Fees & finance',
        text: 'Fee structures, invoices, payments and payroll.',
        tone: 'from-pink-500 to-fuchsia-500',
    },
    {
        icon: GraduationCap,
        title: 'Academics',
        text: 'Academic years, classes, subjects and assignments.',
        tone: 'from-violet-500 to-indigo-500',
    },
    {
        icon: ShieldCheck,
        title: 'Secure by design',
        text: 'Role-based access, two-factor sign-in, encryption and audit trail.',
        tone: 'from-rose-500 to-orange-500',
    },
];

export default function Welcome() {
    const { auth, branding, name } = usePage().props;
    const title = branding?.name ?? name;

    return (
        <>
            <Head title="Welcome" />
            <div className="min-h-screen bg-background">
                <div className="relative overflow-hidden bg-gradient-to-br from-indigo-700 via-violet-700 to-fuchsia-600 text-white">
                    <div
                        className="pointer-events-none absolute -top-32 -left-24 size-[28rem] rounded-full bg-fuchsia-400/30 blur-3xl"
                        aria-hidden
                    />
                    <div
                        className="pointer-events-none absolute -right-32 bottom-0 size-[30rem] rounded-full bg-sky-400/25 blur-3xl"
                        aria-hidden
                    />

                    <header className="relative mx-auto flex max-w-6xl items-center justify-between px-4 py-5 sm:px-6">
                        <div className="flex items-center gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/30 backdrop-blur">
                                <AppLogoIcon className="size-6 fill-current" />
                            </span>
                            <span className="font-semibold tracking-tight">
                                {title}
                            </span>
                        </div>
                        <Link
                            href={auth.user ? dashboard() : login()}
                            className="rounded-full bg-white px-5 py-2 text-sm font-semibold text-indigo-700 shadow-lg shadow-indigo-950/30 transition hover:bg-indigo-50"
                        >
                            {auth.user ? 'Open dashboard' : 'Sign in'}
                        </Link>
                    </header>

                    <section className="relative mx-auto max-w-6xl px-4 pt-12 pb-24 sm:px-6 md:pt-20 md:pb-32">
                        <p className="mb-4 inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-medium ring-1 ring-white/25">
                            School management, made simple
                        </p>
                        <h1 className="max-w-3xl text-4xl font-bold tracking-tight md:text-6xl">
                            Everything your school runs on,{' '}
                            <span className="bg-gradient-to-r from-amber-200 via-pink-200 to-sky-200 bg-clip-text text-transparent">
                                in one place.
                            </span>
                        </h1>
                        <p className="mt-5 max-w-2xl text-base text-white/85 md:text-lg">
                            Students, staff, timetables, attendance, results and
                            fees — for administrators, teachers, students and
                            parents.
                        </p>
                        <div className="mt-8">
                            <Link
                                href={auth.user ? dashboard() : login()}
                                className="inline-flex rounded-full bg-gradient-to-r from-amber-400 to-orange-500 px-6 py-3 font-semibold text-indigo-950 shadow-xl shadow-orange-900/30 transition hover:brightness-105"
                            >
                                {auth.user
                                    ? 'Go to your dashboard'
                                    : 'Sign in to continue'}
                            </Link>
                        </div>
                    </section>
                </div>

                <section className="mx-auto -mt-16 grid max-w-6xl gap-4 px-4 pb-16 sm:grid-cols-2 sm:px-6 lg:grid-cols-3">
                    {features.map((feature) => (
                        <div
                            key={feature.title}
                            className="relative rounded-2xl border bg-card p-6 shadow-lg shadow-indigo-500/5"
                        >
                            <span
                                className={`mb-4 inline-flex size-11 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-md ${feature.tone}`}
                            >
                                <feature.icon className="size-5" aria-hidden />
                            </span>
                            <h2 className="font-semibold">{feature.title}</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {feature.text}
                            </p>
                        </div>
                    ))}
                </section>
            </div>
        </>
    );
}
