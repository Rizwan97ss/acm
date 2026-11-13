import { router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Globe } from 'lucide-react';
import SchoolSwitchController from '@/actions/App/Http/Controllers/Admin/SchoolSwitchController';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';

/**
 * Lets platform users pick the school they are working in. School-bound users
 * never see it; their context is fixed server-side.
 */
export default function SchoolSwitcher() {
    const { tenant } = usePage().props;

    if (!tenant?.canSwitch) {
        return null;
    }

    const current = tenant.current;

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            className="border border-sidebar-border/70 data-[state=open]:bg-sidebar-accent"
                            tooltip={{
                                children: current?.name ?? 'All schools',
                            }}
                        >
                            <Globe aria-hidden />
                            <span className="truncate">
                                {current?.name ?? 'All schools'}
                            </span>
                            <ChevronsUpDown className="ml-auto" aria-hidden />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56"
                        align="start"
                    >
                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                            Working in
                        </DropdownMenuLabel>
                        <DropdownMenuItem
                            onSelect={() =>
                                router.visit(SchoolSwitchController.destroy())
                            }
                        >
                            All schools
                            {!current && (
                                <Check className="ml-auto" aria-hidden />
                            )}
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        {tenant.schools.map((school) => (
                            <DropdownMenuItem
                                key={school.id}
                                onSelect={() =>
                                    router.visit(
                                        SchoolSwitchController.store(school.id),
                                    )
                                }
                            >
                                <span className="truncate">{school.name}</span>
                                <span className="ml-auto text-xs text-muted-foreground">
                                    {school.code}
                                </span>
                                {current?.id === school.id && (
                                    <Check aria-hidden />
                                )}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
