import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * Sign-in pages: a white card on a vivid gradient, with the institute's name.
 */
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { branding, name } = usePage().props;

    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-gradient-to-br from-indigo-700 via-violet-700 to-fuchsia-600 p-6 md:p-10">
            <div
                className="pointer-events-none absolute -top-24 -left-24 size-96 rounded-full bg-fuchsia-400/30 blur-3xl"
                aria-hidden
            />
            <div
                className="pointer-events-none absolute -right-24 -bottom-32 size-[28rem] rounded-full bg-sky-400/30 blur-3xl"
                aria-hidden
            />
            <div
                className="pointer-events-none absolute top-1/3 right-1/4 size-64 rounded-full bg-amber-300/20 blur-3xl"
                aria-hidden
            />

            <div className="relative w-full max-w-sm">
                <Link
                    href={home()}
                    className="mb-6 flex items-center justify-center gap-3 text-white"
                >
                    <span className="flex size-11 items-center justify-center rounded-xl bg-white/15 shadow-lg ring-1 ring-white/30 backdrop-blur">
                        {branding?.logoUrl ? (
                            <img
                                src={branding.logoUrl}
                                alt=""
                                className="size-full rounded-xl object-cover"
                            />
                        ) : (
                            <AppLogoIcon className="size-6 fill-current" />
                        )}
                    </span>
                    <span className="text-lg font-semibold tracking-tight">
                        {branding?.name ?? name}
                    </span>
                </Link>

                <div className="rounded-2xl bg-card p-6 text-card-foreground shadow-2xl shadow-indigo-950/40 sm:p-8">
                    <div className="mb-6 space-y-1.5 text-center">
                        <h1 className="text-xl font-semibold">{title}</h1>
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
