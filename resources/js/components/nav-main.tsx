import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { usePermissions } from '@/hooks/use-permissions';
import type { NavItem } from '@/types';

/**
 * A labelled sidebar group. Items the user lacks permission for are hidden,
 * and the group disappears entirely when nothing in it is visible.
 */
export function NavMain({
    items,
    label = 'Platform',
}: {
    items: NavItem[];
    label?: string;
}) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { canAny } = usePermissions();
    const visible = items.filter(
        (item) => !item.permissions || canAny(item.permissions),
    );

    if (visible.length === 0) {
        return null;
    }

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {visible.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentOrParentUrl(item.href)}
                            tooltip={{ children: item.title }}
                        >
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
