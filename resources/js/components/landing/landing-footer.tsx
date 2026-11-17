import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard, help, login } from '@/routes';
import { navLinks, owner, productMenu } from './content';
import { Container } from './sections';

export default function LandingFooter({ brand }: { brand: string }) {
    const { auth } = usePage().props;
    const year = new Date().getFullYear();

    const columns: {
        title: string;
        links: { label: string; href: string; internal?: boolean }[];
    }[] = [
        {
            title: 'Product',
            links: productMenu.map((item) => ({
                label: item.label,
                href: item.href,
            })),
        },
        {
            title: 'Explore',
            links: [...navLinks, { label: 'Contact', href: '#contact' }],
        },
        {
            title: 'Account',
            links: auth.user
                ? [
                      {
                          label: 'Dashboard',
                          href: dashboard().url,
                          internal: true,
                      },
                      {
                          label: 'Help & guide',
                          href: help().url,
                          internal: true,
                      },
                  ]
                : [
                      { label: 'Sign in', href: login().url, internal: true },
                      { label: 'Book a demo', href: '#contact' },
                  ],
        },
    ];

    return (
        <footer className="border-t bg-navy-950 text-white/80">
            <Container className="grid gap-12 py-16 md:grid-cols-[1.4fr_repeat(3,1fr)]">
                <div>
                    <a href="#top" className="inline-flex items-center gap-2.5">
                        <span className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-gold-300 via-gold-400 to-gold-600 text-navy-950">
                            <AppLogoIcon className="size-5 fill-current" />
                        </span>
                        <span className="font-semibold text-white">
                            {brand}
                        </span>
                    </a>
                    <p className="mt-4 max-w-xs text-sm leading-relaxed text-white/60">
                        The connected school management system for admissions,
                        academics, finance and communication.
                    </p>
                    {owner.social.length > 0 && (
                        <ul className="mt-6 flex flex-wrap gap-3 text-sm">
                            {owner.social.map((link) => (
                                <li key={link.href}>
                                    <a
                                        href={link.href}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="rounded-full bg-white/10 px-3 py-1.5 hover:bg-white/20"
                                    >
                                        {link.label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {columns.map((column) => (
                    <nav key={column.title} aria-label={column.title}>
                        <h2 className="text-sm font-semibold tracking-wider text-gold-300 uppercase">
                            {column.title}
                        </h2>
                        <ul className="mt-4 space-y-2.5 text-sm">
                            {column.links.map((link) => (
                                <li key={link.label}>
                                    {link.internal ? (
                                        <Link
                                            href={link.href}
                                            className="hover:text-white"
                                        >
                                            {link.label}
                                        </Link>
                                    ) : (
                                        <a
                                            href={link.href}
                                            className="hover:text-white"
                                        >
                                            {link.label}
                                        </a>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </nav>
                ))}
            </Container>
            <div className="border-t border-white/10">
                <Container className="flex flex-col gap-3 py-6 text-xs text-white/50 sm:flex-row sm:items-center sm:justify-between">
                    <p>
                        © {year} {brand}. All rights reserved.
                    </p>
                    {owner.legal.length > 0 && (
                        <ul className="flex flex-wrap gap-4">
                            {owner.legal.map((link) => (
                                <li key={link.href}>
                                    <a
                                        href={link.href}
                                        className="hover:text-white"
                                    >
                                        {link.label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    )}
                </Container>
            </div>
        </footer>
    );
}
