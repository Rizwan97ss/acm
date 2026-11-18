import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    Award,
    Banknote,
    CalendarX,
    Coins,
    FileSpreadsheet,
    FileUp,
    HandCoins,
    Landmark,
    LineChart,
    Megaphone,
    Library,
    LifeBuoy,
    Bus,
    Webhook,
    Boxes,
    FolderOpen,
    BookMarked,
    MessagesSquare,
    Download,
    Plane,
    ReceiptText,
    Wallet,
    Bell,
    ClipboardCheck,
    FileText,
    NotebookPen,
    Sparkles,
    BookOpen,
    Briefcase,
    Building2,
    CalendarDays,
    CalendarRange,
    ClipboardList,
    GraduationCap,
    HeartHandshake,
    History,
    Inbox,
    LayoutGrid,
    Settings2,
    ShieldCheck,
    UserRound,
    Users,
    BookOpenText,
    ListChecks,
    Video,
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
import { dashboard, help } from '@/routes';
import { index as learn } from '@/routes/learn';
import { dashboard as learningDashboard } from '@/routes/learning';
import { index as learningCourses } from '@/routes/learning/courses';
import { index as liveClasses } from '@/routes/learning/live-classes';
import { index as quizzes } from '@/routes/learning/quizzes';
import { insights } from '@/routes/analytics';
import { index as imports } from '@/routes/imports';
import { index as announcements } from '@/routes/communication/announcements';
import { index as events } from '@/routes/communication/events';
import { index as messages } from '@/routes/communication/messages';
import { index as libraryBooks } from '@/routes/library/books';
import { index as libraryLoans } from '@/routes/library/loans';
import { index as inventory } from '@/routes/operations/inventory';
import { index as transport } from '@/routes/operations/transport';
import { index as certificates } from '@/routes/records/certificates';
import { index as documents } from '@/routes/records/documents';
import { index as reports } from '@/routes/reports';
import { progress } from '@/routes/academics';
import { overview as accounting } from '@/routes/finance/accounting';
import { index as expenses } from '@/routes/finance/expenses';
import { index as feeItems } from '@/routes/finance/fee-items';
import { index as invoices } from '@/routes/finance/invoices';
import { index as payments } from '@/routes/finance/payments';
import { mine as myLeave, requests as leaveRequests } from '@/routes/hr/leave';
import { index as payroll, mine as myPayslips } from '@/routes/hr/payroll';
import { index as academicYears } from '@/routes/academics/academic-years';
import { index as assignments } from '@/routes/academics/assignments';
import { index as attendance } from '@/routes/academics/attendance';
import { index as exams } from '@/routes/academics/exams';
import { index as gradeScales } from '@/routes/academics/grade-scales';
import { index as classes } from '@/routes/academics/classes';
import { edit as periods } from '@/routes/academics/periods';
import { index as subjects } from '@/routes/academics/subjects';
import { index as timetable } from '@/routes/academics/timetable';
import { index as activityLogs } from '@/routes/admin/activity-logs';
import { index as inquiries } from '@/routes/admin/inquiries';
import { index as auditLogs } from '@/routes/admin/audit-logs';
import { index as roles } from '@/routes/admin/roles';
import { index as schools } from '@/routes/admin/schools';
import { index as settings } from '@/routes/admin/settings';
import { index as users } from '@/routes/admin/users';
import { index as webhooks } from '@/routes/admin/webhooks';
import { index as admissions } from '@/routes/people/admissions';
import { index as guardians } from '@/routes/people/guardians';
import { index as staff } from '@/routes/people/staff';
import { index as students } from '@/routes/people/students';
import type { NavGroup } from '@/types';

/**
 * Sidebar navigation. Each module adds its group here; items declare the
 * permissions that reveal them, and NavMain hides what the user cannot use.
 */
function navGroups(singleInstitute: boolean, learning: boolean): NavGroup[] {
    return [
        {
            title: 'Overview',
            tone: 'sky',
            items: [
                { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
                {
                    title: 'My progress',
                    href: progress(),
                    icon: Sparkles,
                    audience: 'portal',
                },
                { title: 'Help & guide', href: help(), icon: LifeBuoy },
            ],
        },
        ...(learning
            ? [
                  {
                      title: 'Learning',
                      tone: 'amber' as const,
                      items: [
                          {
                              title: 'My learning',
                              href: learn(),
                              icon: GraduationCap,
                              audience: 'portal' as const,
                              permissions: ['courses.view'],
                          },
                          {
                              title: 'Learning overview',
                              href: learningDashboard(),
                              icon: LineChart,
                              audience: 'staff' as const,
                              permissions: ['courses.create'],
                          },
                          {
                              title: 'Courses',
                              href: learningCourses(),
                              icon: BookOpenText,
                              audience: 'staff' as const,
                              permissions: ['courses.create'],
                          },
                          {
                              title: 'Live classes',
                              href: liveClasses(),
                              icon: Video,
                              audience: 'staff' as const,
                              permissions: ['live_classes.create'],
                          },
                          {
                              title: 'Quizzes & tests',
                              href: quizzes(),
                              icon: ListChecks,
                              audience: 'staff' as const,
                              permissions: ['quizzes.create'],
                          },
                      ],
                  },
              ]
            : []),
        {
            title: 'People',
            tone: 'emerald',
            items: [
                {
                    title: 'Students',
                    href: students(),
                    icon: UserRound,
                    permissions: ['students.view'],
                },
                {
                    title: 'Guardians',
                    href: guardians(),
                    icon: HeartHandshake,
                    permissions: ['guardians.view'],
                },
                {
                    title: 'Admissions',
                    href: admissions(),
                    icon: ClipboardList,
                    permissions: ['admissions.view'],
                },
                {
                    title: 'Staff',
                    href: staff(),
                    icon: Briefcase,
                    permissions: ['employees.view', 'teachers.view'],
                },
                {
                    title: 'Bulk import',
                    href: imports(),
                    icon: FileUp,
                    permissions: [
                        'students.create',
                        'employees.create',
                        'teachers.create',
                        'admissions.create',
                        'library.create',
                    ],
                },
            ],
        },
        {
            title: 'Academics',
            tone: 'amber',
            items: [
                {
                    title: 'Timetable',
                    href: timetable(),
                    icon: CalendarDays,
                    permissions: ['timetables.view'],
                },
                {
                    title: 'Attendance',
                    href: attendance(),
                    icon: ClipboardCheck,
                    permissions: ['attendance.create', 'attendance.approve'],
                    audience: 'staff',
                },
                {
                    title: 'Assignments',
                    href: assignments(),
                    icon: NotebookPen,
                    permissions: ['assignments.view'],
                },
                {
                    title: 'Exams & results',
                    href: exams(),
                    icon: FileText,
                    permissions: ['exams.view'],
                    audience: 'staff',
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
                    audience: 'staff',
                },
                {
                    title: 'Grading scales',
                    href: gradeScales(),
                    icon: Award,
                    permissions: ['grades.approve'],
                    audience: 'staff',
                },
            ],
        },
        {
            title: 'Finance',
            tone: 'emerald',
            items: [
                {
                    title: 'Invoices',
                    href: invoices(),
                    icon: ReceiptText,
                    permissions: ['invoices.view'],
                },
                {
                    title: 'Payments',
                    href: payments(),
                    icon: Banknote,
                    permissions: ['payments.view'],
                    audience: 'staff',
                },
                {
                    title: 'Fee structure',
                    href: feeItems(),
                    icon: Coins,
                    permissions: ['fees.view'],
                    audience: 'staff',
                },
                {
                    title: 'Expenses',
                    href: expenses(),
                    icon: Wallet,
                    permissions: ['expenses.view'],
                    audience: 'staff',
                },
                {
                    title: 'Accounting',
                    href: accounting(),
                    icon: Landmark,
                    permissions: ['accounting.view'],
                    audience: 'staff',
                },
            ],
        },
        {
            title: 'HR',
            tone: 'violet',
            items: [
                {
                    title: 'My leave',
                    href: myLeave(),
                    icon: Plane,
                    audience: 'staff',
                },
                {
                    title: 'My payslips',
                    href: myPayslips(),
                    icon: FileSpreadsheet,
                    audience: 'staff',
                },
                {
                    title: 'Leave requests',
                    href: leaveRequests(),
                    icon: CalendarX,
                    permissions: ['leaves.approve'],
                    audience: 'staff',
                },
                {
                    title: 'Payroll',
                    href: payroll(),
                    icon: HandCoins,
                    permissions: ['payroll.view'],
                    audience: 'staff',
                },
            ],
        },
        {
            title: 'Library',
            tone: 'amber',
            items: [
                {
                    title: 'Catalogue',
                    href: libraryBooks(),
                    icon: Library,
                    permissions: ['library.view'],
                },
                {
                    title: 'Loans',
                    href: libraryLoans(),
                    icon: BookMarked,
                    permissions: ['library.view'],
                },
            ],
        },
        {
            title: 'Operations',
            tone: 'sky',
            items: [
                {
                    title: 'Transport',
                    href: transport(),
                    icon: Bus,
                    permissions: ['transport.view'],
                },
                {
                    title: 'Inventory',
                    href: inventory(),
                    icon: Boxes,
                    permissions: ['inventory.view'],
                    audience: 'staff',
                },
                {
                    title: 'Documents',
                    href: documents(),
                    icon: FolderOpen,
                    permissions: ['documents.view'],
                },
                {
                    title: 'Certificates',
                    href: certificates(),
                    icon: Award,
                    permissions: ['certificates.view'],
                },
            ],
        },
        {
            title: 'Communication',
            tone: 'pink',
            items: [
                {
                    title: 'Announcements',
                    href: announcements(),
                    icon: Megaphone,
                    permissions: ['announcements.view'],
                },
                {
                    title: 'Calendar',
                    href: events(),
                    icon: CalendarDays,
                    permissions: ['events.view'],
                },
                {
                    title: 'Messages',
                    href: messages(),
                    icon: MessagesSquare,
                    permissions: ['messages.view'],
                },
            ],
        },
        {
            title: 'Insights',
            tone: 'sky',
            items: [
                {
                    title: 'Insights',
                    href: insights(),
                    icon: LineChart,
                    permissions: ['analytics.view'],
                    audience: 'staff',
                },
                {
                    title: 'Reports & exports',
                    href: reports(),
                    icon: Download,
                    permissions: ['reports.export'],
                    audience: 'staff',
                },
            ],
        },
        {
            title: 'Administration',
            tone: 'pink',
            items: [
                {
                    title: singleInstitute ? 'Institute profile' : 'Schools',
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
                {
                    title: 'Activity log',
                    href: activityLogs(),
                    icon: Activity,
                    permissions: ['audit_logs.view'],
                },
                {
                    title: 'Website enquiries',
                    href: inquiries(),
                    icon: Inbox,
                    permissions: ['inquiries.view'],
                },
                {
                    title: 'Webhooks',
                    href: webhooks(),
                    icon: Webhook,
                    permissions: ['integrations.view'],
                },
            ],
        },
    ];
}

export function AppSidebar() {
    const { tenant, features } = usePage().props;

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
                {navGroups(
                    tenant?.mode !== 'multi',
                    features?.learning ?? false,
                ).map((group) => (
                    <NavMain
                        key={group.title}
                        label={group.title}
                        tone={group.tone}
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
