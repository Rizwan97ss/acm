import { Link } from '@inertiajs/react';
import {
    BookOpen,
    Bell,
    Building2,
    CalendarDays,
    CalendarRange,
    GraduationCap,
    History,
    LayoutGrid,
    Settings2,
    ShieldCheck,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import SchoolSwitcher from '@/components/school-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as academicYears } from '@/routes/academics/academic-years';
import { index as classes } from '@/routes/academics/classes';
import { edit as periods } from '@/routes/academics/periods';
import { index as subjects } from '@/routes/academics/subjects';
import { index as timetable } from '@/routes/academics/timetable';
import { index as auditLogs } from '@/routes/admin/audit-logs';
import { index as roles } from '@/routes/admin/roles';
import { index as schools } from '@/routes/admin/schools';
import { index as settings } from '@/routes/admin/settings';
import { index as users } from '@/routes/admin/users';
import type { NavGroup } from '@/types';

/**
 * Sidebar navigation. Each module adds its group here; items declare the
 * permissions that reveal them, and NavMain hides what the user cannot use.
 */
const navGroups: NavGroup[] = [
    {
        title: 'Overview',
        items: [{ title: 'Dashboard', href: dashboard(), icon: LayoutGrid }],
    },
    {
        title: 'Academics',
        items: [
            {
                title: 'Timetable',
                href: timetable(),
                icon: CalendarDays,
                permissions: ['timetables.view'],
            },
            {
                title: 'Classes & sections',
                href: classes(),
                icon: GraduationCap,
                permissions: ['classes.view'],
            },
            {
                title: 'Subjects',
                href: subjects(),
                icon: BookOpen,
                permissions: ['subjects.view'],
            },
            {
                title: 'Academic years',
                href: academicYears(),
                icon: CalendarRange,
                permissions: ['academic_years.view'],
            },
            {
                title: 'Bell schedule',
                href: periods(),
                icon: Bell,
                permissions: ['timetables.view'],
            },
        ],
    },
    {
        title: 'Administration',
        items: [
            {
                title: 'Schools',
                href: schools(),
                icon: Building2,
                permissions: ['schools.view'],
            },
            {
                title: 'Users',
                href: users(),
                icon: Users,
                permissions: ['users.view'],
            },
            {
                title: 'Roles & permissions',
                href: roles(),
                icon: ShieldCheck,
                permissions: ['roles.view'],
            },
            {
                title: 'Settings',
                href: settings(),
                icon: Settings2,
                permissions: ['settings.view'],
            },
            {
                title: 'Audit log',
                href: auditLogs(),
                icon: History,
                permissions: ['audit_logs.view'],
            },
        ],
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SchoolSwitcher />
            </SidebarHeader>

            <SidebarContent className="gap-4">
                {navGroups.map((group) => (
                    <NavMain
                        key={group.title}
                        label={group.title}
                        items={group.items}
                    />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
