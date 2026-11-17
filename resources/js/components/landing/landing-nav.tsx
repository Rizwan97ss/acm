import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, Menu } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import { dashboard, login } from '@/routes';
import { navLinks, productMenu } from './content';

/**
 * Sticky header: transparent over the hero, frosted glass once the page scrolls.
 */
export default function LandingNav({ brand }: { brand: string }) {
    const { auth } = usePage().props;
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        const update = (): void => setScrolled(window.scrollY > 24);

        update();
        window.addEventListener('scroll', update, { passive: true });

        return () => window.removeEventListener('scroll', update);
    }, []);

    const account = auth.user
        ? { label: 'Open dashboard', href: dashboard() }
        : { label: 'Sign in', href: login() };

    return (
        <header
            className={cn(
                'fixed inset-x-0 top-0 z-50 transition-all duration-300',
                scrolled
                    ? 'border-b border-border/60 bg-background/80 shadow-sm backdrop-blur-xl'
                    : 'bg-transparent',
            )}
        >
            <nav
                aria-label="Main"
                className="mx-auto flex h-16 max-w-7xl items-center gap-6 px-4 sm:px-6 lg:px-8"
            >
                <a
                    href="#top"
                    className="flex items-center gap-2.5 rounded-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <span className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-gold-300 via-gold-400 to-gold-600 text-navy-950 shadow-md shadow-gold-900/20">
                        <AppLogoIcon className="size-5 fill-current" />
                    </span>
                    <span className="text-base font-semibold tracking-tight">
                        {brand}
                    </span>
                </a>

                <ul className="ml-4 hidden items-center gap-1 text-sm font-medium lg:flex">
                    <li>
                        <DropdownMenu>
                            <DropdownMenuTrigger className="inline-flex items-center gap-1 rounded-full px-3 py-2 text-foreground/75 transition hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                                Product
                                <ChevronDown className="size-4" aria-hidden />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="start"
                                className="w-80 rounded-2xl p-2"
                            >
                                {productMenu.map((item) => (
                                    <DropdownMenuItem
                                        key={item.href}
                                        asChild
                                        className="items-start gap-3 rounded-xl p-3"
                                    >
                                        <a href={item.href}>
                                            <span className="mt-0.5 rounded-lg bg-navy-50 p-2 text-navy-700 dark:bg-navy-500/15 dark:text-navy-200">
                                                <item.icon
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                            </span>
                                            <span>
                                                <span className="block font-medium">
                                                    {item.label}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {item.description}
                                                </span>
                                            </span>
                                        </a>
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </li>
                    {navLinks.map((link) => (
                        <li key={link.href}>
                            <a
                                href={link.href}
                                className="rounded-full px-3 py-2 text-foreground/75 transition hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                {link.label}
                            </a>
                        </li>
                    ))}
                </ul>

                <div className="ml-auto hidden items-center gap-2 lg:flex">
                    <Button asChild variant="ghost" className="rounded-full">
                        <Link href={account.href}>{account.label}</Link>
                    </Button>
                    <Button
                        asChild
                        className="rounded-lg bg-navy-900 px-5 text-white shadow-[3px_3px_0_var(--color-gold-400)] transition hover:translate-x-px hover:translate-y-px hover:bg-navy-800 hover:shadow-[2px_2px_0_var(--color-gold-400)] dark:bg-gold-300 dark:text-navy-950"
                    >
                        <a href="#contact">Book a demo</a>
                    </Button>
                </div>

                <Sheet>
                    <SheetTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="ml-auto lg:hidden"
                            aria-label="Open menu"
                        >
                            <Menu aria-hidden />
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="right" className="w-80 p-6">
                        <SheetTitle>{brand}</SheetTitle>
                        <SheetDescription className="sr-only">
                            Site navigation
                        </SheetDescription>
                        <nav aria-label="Mobile" className="mt-6 grid gap-1">
                            <p className="px-3 pb-1 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Product
                            </p>
                            {[...productMenu, ...navLinks].map((item) => (
                                <SheetClose asChild key={item.href}>
                                    <a
                                        href={item.href}
                                        className="rounded-lg px-3 py-2.5 font-medium hover:bg-muted"
                                    >
                                        {item.label}
                                    </a>
                                </SheetClose>
                            ))}
                        </nav>
                        <div className="mt-6 grid gap-2">
                            <SheetClose asChild>
                                <Button asChild className="rounded-full">
                                    <a href="#contact">Book a demo</a>
                                </Button>
                            </SheetClose>
                            <Button
                                asChild
                                variant="outline"
                                className="rounded-full"
                            >
                                <Link href={account.href}>{account.label}</Link>
                            </Button>
                        </div>
                    </SheetContent>
                </Sheet>
            </nav>
        </header>
    );
}
