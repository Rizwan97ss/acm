import { Link, usePage } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { usePermissions } from '@/hooks/use-permissions';
import { cn } from '@/lib/utils';
import type { NavItem, NavTone } from '@/types';

const toneClasses: Record<NavTone, string> = {
    sky: 'text-sky-300',
    amber: 'text-amber-300',
    emerald: 'text-emerald-300',
    pink: 'text-gold-300',
    violet: 'text-royal-300',
};

/**
 * A labelled sidebar group. Items the user lacks permission for are hidden,
 * and the group disappears entirely when nothing in it is visible.
 */
export function NavMain({
    items,
    label = 'Platform',
    tone = 'violet',
}: {
    items: NavItem[];
    label?: string;
    tone?: NavTone;
}) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { canAny } = usePermissions();
    const { auth } = usePage().props;
    const visible = items.filter(
        (item) =>
            (!item.permissions || canAny(item.permissions)) &&
            (item.audience === undefined ||
                (item.audience === 'portal') === auth.isPortalUser),
    );

    if (visible.length === 0) {
        return null;
    }

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel className="tracking-wider text-sidebar-foreground/55 uppercase">
                {label}
            </SidebarGroupLabel>
            <SidebarMenu>
                {visible.map((item) => {
                    const active = isCurrentOrParentUrl(item.href);

                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={active}
                                tooltip={{ children: item.title }}
                                className="data-[active=true]:bg-gradient-to-r data-[active=true]:from-sidebar-accent data-[active=true]:to-sidebar-accent/40 data-[active=true]:font-medium data-[active=true]:shadow-[inset_3px_0_0_var(--sidebar-primary)]"
                            >
                                <Link href={item.href} prefetch>
                                    {item.icon && (
                                        <item.icon
                                            className={cn(
                                                toneClasses[tone],
                                                active &&
                                                    'text-sidebar-primary',
                                            )}
                                        />
                                    )}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
