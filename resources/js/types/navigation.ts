import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /** Show the item only when the user holds at least one of these permissions. */
    permissions?: string[];
    /** Only for students and guardians (portal users), or only for staff. */
    audience?: 'portal' | 'staff';
};

export type NavTone = 'sky' | 'amber' | 'emerald' | 'pink' | 'violet';

export type NavGroup = {
    title: string;
    /** Icon colour for the group, so each area of the app is recognisable at a glance. */
    tone?: NavTone;
    items: NavItem[];
};
