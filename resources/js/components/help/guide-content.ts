import type { LucideIcon } from 'lucide-react';
import {
    BarChart3,
    BookOpen,
    Building2,
    ClipboardList,
    Compass,
    GraduationCap,
    HelpCircle,
    Landmark,
    Megaphone,
    ShieldCheck,
    Truck,
    UserCog,
    Users,
    Wallet,
} from 'lucide-react';
import { dashboard, help } from '@/routes';
import { progress } from '@/routes/academics';
import { index as academicYears } from '@/routes/academics/academic-years';
import { index as assignments } from '@/routes/academics/assignments';
import { index as attendance } from '@/routes/academics/attendance';
import { index as classes } from '@/routes/academics/classes';
import { index as exams } from '@/routes/academics/exams';
import { index as gradeScales } from '@/routes/academics/grade-scales';
import { edit as periods } from '@/routes/academics/periods';
import { index as subjects } from '@/routes/academics/subjects';
import { index as timetable } from '@/routes/academics/timetable';
import { index as activityLogs } from '@/routes/admin/activity-logs';
import { index as auditLogs } from '@/routes/admin/audit-logs';
import { index as roles } from '@/routes/admin/roles';
import { index as settings } from '@/routes/admin/settings';
import { index as users } from '@/routes/admin/users';
import { index as webhooks } from '@/routes/admin/webhooks';
import { insights } from '@/routes/analytics';
import { index as apiTokens } from '@/routes/api-tokens';
import { index as announcements } from '@/routes/communication/announcements';
import { index as events } from '@/routes/communication/events';
import { index as messages } from '@/routes/communication/messages';
import { overview as accounting } from '@/routes/finance/accounting';
import { index as feeItems } from '@/routes/finance/fee-items';
import { index as invoices } from '@/routes/finance/invoices';
import { index as payments } from '@/routes/finance/payments';
import { mine as myLeave, requests as leaveRequests } from '@/routes/hr/leave';
import { index as payroll, mine as myPayslips } from '@/routes/hr/payroll';
import { index as imports } from '@/routes/imports';
import { index as libraryBooks } from '@/routes/library/books';
import { index as libraryLoans } from '@/routes/library/loans';
import { index as notifications } from '@/routes/notifications';
import { index as inventory } from '@/routes/operations/inventory';
import { index as transport } from '@/routes/operations/transport';
import { index as admissions } from '@/routes/people/admissions';
import { index as guardians } from '@/routes/people/guardians';
import { index as staff } from '@/routes/people/staff';
import { index as students } from '@/routes/people/students';
import { edit as preferences } from '@/routes/preferences';
import { edit as profile } from '@/routes/profile';
import { index as certificates } from '@/routes/records/certificates';
import { index as documents } from '@/routes/records/documents';
import { index as reports } from '@/routes/reports';
import { edit as security } from '@/routes/security';
import type { RouteDefinition } from '@/wayfinder';

export type GuideTone = 'sky' | 'emerald' | 'amber' | 'pink' | 'violet';

export type GuideLink = { label: string; href: RouteDefinition<'get'> };

export type GuideTopic = {
    title: string;
    steps: string[];
    tip?: string;
    link?: GuideLink;
};

export type GuideViewer = {
    canAny: (permissions: string[]) => boolean;
    isPortalUser: boolean;
};

export type GuideSection = {
    id: string;
    title: string;
    description: string;
    icon: LucideIcon;
    tone: GuideTone;
    visible: (viewer: GuideViewer) => boolean;
    topics: GuideTopic[];
};

/** Keys match the setup checklist sent by HelpController. */
export type SetupKey =
    | 'academic_year'
    | 'classes'
    | 'subjects'
    | 'periods'
    | 'grade_scales'
    | 'staff'
    | 'timetable'
    | 'students'
    | 'fees'
    | 'invoices';

export type SetupStep = {
    key: SetupKey | null;
    title: string;
    description: string;
    link: GuideLink;
};

const everyone = (): boolean => true;
const staffOnly = (viewer: GuideViewer): boolean => !viewer.isPortalUser;

/**
 * The order a new school should enter its data in. Steps with a key are ticked off
 * automatically once data exists.
 */
export const setupSteps: SetupStep[] = [
    {
        key: null,
        title: 'Check the school settings',
        description:
            'Name and colours, time zone, currency, date format, school week, pass percentage and security rules.',
        link: { label: 'Open settings', href: settings() },
    },
    {
        key: 'academic_year',
        title: 'Create the academic year and mark it current',
        description:
            'Add its terms too. Attendance, timetables, fees and reports all use the current year.',
        link: { label: 'Academic years', href: academicYears() },
    },
    {
        key: 'classes',
        title: 'Add classes and their sections',
        description:
            'For example Grade 1 with sections A and B, each with a room and class teacher.',
        link: { label: 'Classes & sections', href: classes() },
    },
    {
        key: 'subjects',
        title: 'Add subjects and attach them to classes',
        description:
            'Set how many periods a week each class has of each subject.',
        link: { label: 'Subjects', href: subjects() },
    },
    {
        key: 'periods',
        title: 'Set the bell schedule',
        description:
            'The periods of the school day, with start and end times and breaks.',
        link: { label: 'Bell schedule', href: periods() },
    },
    {
        key: 'grade_scales',
        title: 'Define a grading scale',
        description:
            'Grade bands (A = 90–100 …) used on results and report cards.',
        link: { label: 'Grading scales', href: gradeScales() },
    },
    {
        key: 'staff',
        title: 'Add staff and give them accounts',
        description:
            'Add employees under Staff, then create sign-in accounts with the right role under Users.',
        link: { label: 'Staff', href: staff() },
    },
    {
        key: 'timetable',
        title: 'Build the timetable',
        description:
            'Pick a section and place a subject and teacher in each period. Clashes are blocked.',
        link: { label: 'Timetable', href: timetable() },
    },
    {
        key: 'students',
        title: 'Add students and parents',
        description:
            'Enrol new intake through Admissions, or add students directly. Then invite them and their parents to the portal.',
        link: { label: 'Students', href: students() },
    },
    {
        key: 'fees',
        title: 'Set up the fee structure',
        description:
            'Fee items for the current year, for all classes or one class, plus any student discounts.',
        link: { label: 'Fee structure', href: feeItems() },
    },
    {
        key: 'invoices',
        title: 'Generate the first invoices',
        description: 'Bill whole classes at once from the fee items you chose.',
        link: { label: 'Invoices', href: invoices() },
    },
];

export const guideSections: GuideSection[] = [
    {
        id: 'getting-started',
        title: 'Getting started',
        description: 'Finding your way around. Read this first.',
        icon: Compass,
        tone: 'sky',
        visible: everyone,
        topics: [
            {
                title: 'Finding your way around',
                steps: [
                    'The menu on the left only shows what your role allows. If something you need is missing, ask an administrator for the permission.',
                    'The Dashboard shows the figures and lists that matter to you.',
                    'On a phone, open the menu with the button at the top left.',
                    'Collapse the menu to icons with the button next to the page title.',
                ],
                link: { label: 'Go to the dashboard', href: dashboard() },
            },
            {
                title: 'Notifications',
                steps: [
                    'The bell at the top of the page shows a number when something new arrives: announcements, messages and updates.',
                    'Open a notification to go straight to it. It is then marked as read.',
                    'Use “Mark all read” on the notifications page to clear the list.',
                ],
                link: { label: 'Notifications', href: notifications() },
            },
            {
                title: 'Lists, search and filters',
                steps: [
                    'Most pages have a search box and filters above the list. Results update as you type.',
                    'Long lists are split into pages. Use the page numbers at the bottom.',
                    'Buttons for adding, editing or deleting only appear if your role allows it.',
                ],
            },
            {
                title: 'Coming back to this guide',
                steps: [
                    'Open Help & guide from the Overview group in the menu at any time.',
                    'Use the search box at the top of this page to find a topic.',
                ],
                link: { label: 'Help & guide', href: help() },
            },
        ],
    },
    {
        id: 'teaching',
        title: 'Teaching',
        description: 'Attendance, homework, marks and your timetable.',
        icon: GraduationCap,
        tone: 'emerald',
        visible: (viewer) =>
            viewer.canAny([
                'attendance.create',
                'grades.create',
                'assignments.create',
            ]),
        topics: [
            {
                title: 'Take attendance',
                steps: [
                    'Open Attendance, choose your section and the date.',
                    'Mark each student present, absent, late or excused. Everyone starts as present, so you usually only change the exceptions.',
                    'Save. You can correct today and the past week; older days can only be changed by a supervisor.',
                ],
                tip: 'Parents see attendance in their portal straight away.',
                link: { label: 'Attendance', href: attendance() },
            },
            {
                title: 'Set homework',
                steps: [
                    'Open Assignments and choose Add.',
                    'Pick the section and subject, add the instructions and a due date.',
                    'Students and parents see it under My progress. Open the assignment to see who has submitted.',
                ],
                link: { label: 'Assignments', href: assignments() },
            },
            {
                title: 'Enter exam marks',
                steps: [
                    'Open Exams & results and choose the exam.',
                    'Pick your paper and section, then enter each student’s marks and save.',
                    'Marks stay private until the exam is published by the principal or an administrator.',
                ],
                link: { label: 'Exams & results', href: exams() },
            },
            {
                title: 'Your timetable',
                steps: [
                    'Timetable opens on your own teaching schedule.',
                    'Switch to a section view to see a whole class’s week.',
                ],
                link: { label: 'Timetable', href: timetable() },
            },
        ],
    },
    {
        id: 'leadership',
        title: 'Approvals and results',
        description: 'For the principal and school leaders.',
        icon: ClipboardList,
        tone: 'violet',
        visible: (viewer) =>
            viewer.canAny([
                'exams.approve',
                'admissions.approve',
                'leaves.approve',
                'expenses.approve',
                'announcements.approve',
            ]),
        topics: [
            {
                title: 'Run an exam and publish results',
                steps: [
                    'Create the exam with its dates, then generate papers for each class and subject.',
                    'Teachers enter marks. Check progress on the exam page.',
                    'Choose Publish results when marking is complete. Students and parents then see results, grades and ranks, and report cards can be printed.',
                    'Unpublish if you need to correct something.',
                ],
                link: { label: 'Exams & results', href: exams() },
            },
            {
                title: 'Decide admission applications',
                steps: [
                    'Open Admissions and pick a pending application.',
                    'Approve or reject it, with a note.',
                    'Approved applications can then be enrolled into a section.',
                ],
                link: { label: 'Admissions', href: admissions() },
            },
            {
                title: 'Approve leave and expenses',
                steps: [
                    'Leave requests and Expenses each list items waiting for a decision.',
                    'Approve or reject with a reason. The requester is notified.',
                ],
                link: { label: 'Leave requests', href: leaveRequests() },
            },
        ],
    },
    {
        id: 'people',
        title: 'Students, parents and admissions',
        description: 'Adding people and giving them portal access.',
        icon: Users,
        tone: 'emerald',
        visible: (viewer) =>
            viewer.canAny([
                'students.create',
                'guardians.create',
                'admissions.create',
                'students.view',
            ]),
        topics: [
            {
                title: 'Admit a new student',
                steps: [
                    'Open Admissions and record the application: the child, the class applied for and the parent’s contact details.',
                    'After it is approved, choose Enrol and pick a section.',
                    'The student record is created and enrolled, and the parent is linked. Siblings share the same parent when the phone or email matches.',
                ],
                link: { label: 'Admissions', href: admissions() },
            },
            {
                title: 'Add a student directly',
                steps: [
                    'Open Students and choose New student.',
                    'Fill in the details and pick a section to enrol them in.',
                    'Link parents on the student’s page.',
                ],
                tip: 'National ID, medical notes and address are encrypted and only shown to people who may edit the student.',
                link: { label: 'Students', href: students() },
            },
            {
                title: 'Import many records from Excel',
                steps: [
                    'Open Bulk import (or choose Import on the Students, Staff, Admissions or Library page) and pick what to import.',
                    'Download the Excel template and fill one row per record on the Data sheet. The Instructions sheet explains every column.',
                    'Upload the file and choose Check file. Every row is checked and problems are listed by row number; nothing is saved yet.',
                    'Fix any problems, upload again, then choose Import. All rows are saved together, or none are.',
                ],
                tip: 'For students, fill Class and Section to enrol them in the current year, and the Parent columns to add the parent. Children with the same parent phone or email share one parent record.',
                link: { label: 'Bulk import', href: imports() },
            },
            {
                title: 'Invite a student or parent to the portal',
                steps: [
                    'Make sure the person has an email address on their record.',
                    'On the student’s or parent’s page, choose Invite to portal.',
                    'They receive an email with a link to set their password. They only ever see their own (or their children’s) records.',
                ],
                link: { label: 'Parents & guardians', href: guardians() },
            },
        ],
    },
    {
        id: 'finance',
        title: 'Fees and finance',
        description: 'Invoices, payments, expenses and the accounts.',
        icon: Wallet,
        tone: 'amber',
        visible: (viewer) =>
            viewer.canAny([
                'fees.view',
                'invoices.create',
                'payments.create',
                'expenses.create',
                'accounting.view',
            ]),
        topics: [
            {
                title: 'Set up fees',
                steps: [
                    'Open Fee structure and add fee items for the current year: tuition, transport, lab fee …',
                    'Each item applies to every class or to one class, with a frequency.',
                    'For a discount, open the student’s page and set a concession percentage.',
                ],
                link: { label: 'Fee structure', href: feeItems() },
            },
            {
                title: 'Bill a class',
                steps: [
                    'Open Invoices and choose Generate invoices.',
                    'Give it a title and dates, then pick the fee items and classes.',
                    'Every enrolled student gets an invoice, with their discount applied.',
                ],
                link: { label: 'Invoices', href: invoices() },
            },
            {
                title: 'Record a payment',
                steps: [
                    'Open the invoice and choose Record payment.',
                    'Enter the amount, method and date. Part payments are fine.',
                    'Print or share the receipt from the payment.',
                    'A mistaken payment can be reversed from Payments, with a reason.',
                ],
                link: { label: 'Payments', href: payments() },
            },
            {
                title: 'Expenses and accounts',
                steps: [
                    'Record spending under Expenses. Approvers review it.',
                    'Invoices, payments, expenses and payroll post themselves to the accounts automatically.',
                    'Accounting shows the balances and every journal entry.',
                ],
                link: { label: 'Accounting', href: accounting() },
            },
        ],
    },
    {
        id: 'hr',
        title: 'Staff, leave and payroll',
        description: 'Employee records, leave and monthly pay.',
        icon: UserCog,
        tone: 'pink',
        visible: staffOnly,
        topics: [
            {
                title: 'Apply for leave',
                steps: [
                    'Open My leave and fill in the request form: leave type, dates and a reason.',
                    'Choose Submit request.',
                    'You are notified when it is approved or rejected.',
                ],
                link: { label: 'My leave', href: myLeave() },
            },
            {
                title: 'See your payslips',
                steps: [
                    'Open My payslips to see each month’s pay once payroll is finalised.',
                ],
                link: { label: 'My payslips', href: myPayslips() },
            },
            {
                title: 'Run payroll (HR)',
                steps: [
                    'Set each employee’s salary and allowances under Payroll → Salaries.',
                    'Create a run for the month and review the payslips.',
                    'Finalise the run. Payslips become visible to staff and the cost is posted to the accounts.',
                ],
                link: { label: 'Payroll', href: payroll() },
            },
        ],
    },
    {
        id: 'library',
        title: 'Library',
        description: 'The catalogue, lending and fines.',
        icon: BookOpen,
        tone: 'violet',
        visible: (viewer) => viewer.canAny(['library.view']),
        topics: [
            {
                title: 'Add books',
                steps: [
                    'Open Catalogue and add each title with its number of copies.',
                    'Increase the copies when more arrive. You cannot go below the number on loan.',
                ],
                link: { label: 'Catalogue', href: libraryBooks() },
            },
            {
                title: 'Lend and return',
                steps: [
                    'Open Loans and choose Issue book. Type the student’s admission number or the staff number.',
                    'The due date follows the loan period in settings. Borrowers over their limit, or with overdue books, are refused.',
                    'Choose Return when the book comes back.',
                ],
                tip: 'Late returns get a fine. A student’s fine is added to a fee invoice automatically when “Bill student fines” is on in Library settings.',
                link: { label: 'Loans', href: libraryLoans() },
            },
        ],
    },
    {
        id: 'operations',
        title: 'Transport, inventory, documents and certificates',
        description: 'Running the school’s operations and records.',
        icon: Truck,
        tone: 'sky',
        visible: (viewer) =>
            viewer.canAny([
                'transport.view',
                'inventory.view',
                'documents.view',
                'certificates.view',
            ]),
        topics: [
            {
                title: 'Transport',
                steps: [
                    'Create routes with their vehicle, driver and capacity, and add the stops in order.',
                    'Assign students to a route and stop. Seats are limited by capacity.',
                ],
                link: { label: 'Transport', href: transport() },
            },
            {
                title: 'Inventory',
                steps: [
                    'Add items with their unit and reorder level.',
                    'Record stock in, stock out and adjustments. Items below their reorder level are highlighted.',
                ],
                link: { label: 'Inventory', href: inventory() },
            },
            {
                title: 'Documents',
                steps: [
                    'Upload PDFs, Office files or images (up to 10 MB), with a category and optionally for one class.',
                    'Files are stored privately and only downloadable by people allowed to see them.',
                ],
                link: { label: 'Documents', href: documents() },
            },
            {
                title: 'Certificates',
                steps: [
                    'Draft a certificate for a student, then Issue it. It gets a serial number.',
                    'Print it. Every certificate carries a verification code and link anyone can use to check it is genuine.',
                    'Revoke a certificate issued by mistake. Its verification page then shows it as revoked.',
                ],
                link: { label: 'Certificates', href: certificates() },
            },
        ],
    },
    {
        id: 'communication',
        title: 'Announcements, calendar and messages',
        description: 'Keeping everyone informed.',
        icon: Megaphone,
        tone: 'pink',
        visible: (viewer) =>
            viewer.canAny([
                'announcements.view',
                'events.view',
                'messages.view',
            ]),
        topics: [
            {
                title: 'Publish an announcement',
                steps: [
                    'Open Announcements and choose New announcement.',
                    'Choose who it is for: everyone, staff, students or parents, optionally one class.',
                    'Publish it now, or save a draft. Publishing notifies everyone in the audience.',
                    'Pin important announcements to keep them at the top.',
                ],
                link: { label: 'Announcements', href: announcements() },
            },
            {
                title: 'Add an event to the calendar',
                steps: [
                    'Open Calendar and add the event with its dates, place and audience.',
                    'People only see events meant for them.',
                ],
                link: { label: 'Calendar', href: events() },
            },
            {
                title: 'Send a message',
                steps: [
                    'Open Messages and start a conversation, or reply to one.',
                    'The other person is notified. Messages are stored encrypted.',
                ],
                link: { label: 'Messages', href: messages() },
            },
        ],
    },
    {
        id: 'portal',
        title: 'For students and parents',
        description: 'Everything in your portal.',
        icon: GraduationCap,
        tone: 'emerald',
        visible: (viewer) => viewer.isPortalUser,
        topics: [
            {
                title: 'My progress',
                steps: [
                    'One page per child: attendance this year, published exam results and open homework.',
                    'Results appear once the school publishes them.',
                ],
                link: { label: 'My progress', href: progress() },
            },
            {
                title: 'Timetable and homework',
                steps: [
                    'Timetable shows the weekly lessons for your class.',
                    'Assignments lists homework with due dates.',
                ],
                link: { label: 'Timetable', href: timetable() },
            },
            {
                title: 'Fees',
                steps: [
                    'Invoices lists every bill with what has been paid and what is still due.',
                    'Open an invoice to see its lines and download receipts.',
                ],
                link: { label: 'Invoices', href: invoices() },
            },
            {
                title: 'School news and messages',
                steps: [
                    'Announcements and the Calendar show news and events for you.',
                    'Use Messages to contact the school.',
                ],
                link: { label: 'Announcements', href: announcements() },
            },
        ],
    },
    {
        id: 'insights',
        title: 'Insights and reports',
        description: 'Trends, forecasts and exports.',
        icon: BarChart3,
        tone: 'sky',
        visible: (viewer) => viewer.canAny(['analytics.view', 'reports.view']),
        topics: [
            {
                title: 'Insights',
                steps: [
                    'Attendance trends, students at risk (low attendance or failing), performance by class and subject, and a fee-collection forecast.',
                    'If Insights says the analytics service is unavailable, ask your technical administrator to start it. Everything else keeps working.',
                ],
                link: { label: 'Insights', href: insights() },
            },
            {
                title: 'Reports and exports',
                steps: [
                    'Pick a report, set its filters and download it as CSV for Excel or Google Sheets.',
                    'Every export is recorded in the audit log.',
                ],
                link: { label: 'Reports & exports', href: reports() },
            },
        ],
    },
    {
        id: 'administration',
        title: 'Administration',
        description: 'Users, roles, settings, logs and integrations.',
        icon: Building2,
        tone: 'violet',
        visible: (viewer) =>
            viewer.canAny([
                'users.view',
                'roles.view',
                'settings.edit',
                'audit_logs.view',
                'integrations.view',
            ]),
        topics: [
            {
                title: 'Create user accounts',
                steps: [
                    'Open Users and choose New user. Give them one or more roles.',
                    'Deactivate an account to block it immediately. The person is signed out on their next click.',
                    'Students and parents get accounts through Invite to portal instead.',
                ],
                link: { label: 'Users', href: users() },
            },
            {
                title: 'Roles and permissions',
                steps: [
                    'Each permission is an area and an action, for example Students → view or Invoices → approve.',
                    'Built-in roles cover the usual jobs: School Admin, Principal, Teacher, Accountant, HR, Librarian, Receptionist, Student and Parent.',
                    'Create your own role, optionally starting from a built-in one. You can only grant permissions you have yourself.',
                ],
                link: { label: 'Roles & permissions', href: roles() },
            },
            {
                title: 'School settings',
                steps: [
                    'Branding, localization, academic rules, library rules, notifications and security are each a group under Settings.',
                    'Security lets you require two-factor login for everyone and sign people out after a period of inactivity.',
                ],
                link: { label: 'Settings', href: settings() },
            },
            {
                title: 'Audit log and activity log',
                steps: [
                    'Audit log: what changed, with old and new values, plus sign-ins and failed sign-ins.',
                    'Activity log: every page visit and action for a day, with who did it and the result. Typed values are never recorded.',
                ],
                link: { label: 'Audit log', href: auditLogs() },
            },
            {
                title: 'Connect other systems',
                steps: [
                    'Webhooks send events (student created, payment recorded, announcement published, certificate issued) to another system’s https address.',
                    'Copy the signing secret when it is shown; it is shown only once. Use Send test and watch the delivery log.',
                ],
                link: { label: 'Webhooks', href: webhooks() },
            },
            {
                title: 'Look up a request',
                steps: [
                    'Open the activity log, pick the day and filter by result to find denied or failed requests.',
                ],
                link: { label: 'Activity log', href: activityLogs() },
            },
        ],
    },
    {
        id: 'account',
        title: 'Your account and security',
        description: 'Profile, password, two-factor login and API tokens.',
        icon: ShieldCheck,
        tone: 'amber',
        visible: everyone,
        topics: [
            {
                title: 'Profile and password',
                steps: [
                    'Open your name at the bottom of the menu, then Settings.',
                    'Profile changes your name and email. Security changes your password.',
                    'Changing your password signs you out on every other device and cancels your API tokens.',
                ],
                link: { label: 'Profile', href: profile() },
            },
            {
                title: 'Turn on two-factor login',
                steps: [
                    'Open Settings → Security and choose Enable two-factor authentication.',
                    'Scan the QR code with an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password …) and enter the code.',
                    'Save the recovery codes somewhere safe. Each one works once if you lose your phone.',
                    'You can also add a passkey to sign in with your fingerprint, face or device PIN.',
                ],
                tip: 'Your school may require two-factor login. If so, you are taken to this page until it is set up.',
                link: { label: 'Security', href: security() },
            },
            {
                title: 'Look and preferences',
                steps: [
                    'Appearance switches between light, dark and your device’s setting.',
                    'Preferences lets you override some school settings for yourself, such as date format.',
                ],
                link: { label: 'Preferences', href: preferences() },
            },
            {
                title: 'Mobile apps and API tokens',
                steps: [
                    'Mobile apps sign in with your email and password (plus your two-factor code).',
                    'For scripts and other systems, create a token under Settings → API tokens. Pick what it may read and when it expires, and copy it straight away; it is shown once.',
                    'A token can never do more than your own account. Revoke any token you no longer use.',
                ],
                link: { label: 'API tokens', href: apiTokens() },
            },
        ],
    },
    {
        id: 'faq',
        title: 'Questions and problems',
        description: 'Quick answers to common issues.',
        icon: HelpCircle,
        tone: 'pink',
        visible: everyone,
        topics: [
            {
                title: 'A menu item or button is missing',
                steps: [
                    'Your role does not include that permission. Ask an administrator to add it to your role.',
                ],
            },
            {
                title: '“This action is unauthorized”',
                steps: [
                    'Your role does not allow that action. An administrator can change your role’s permissions.',
                ],
            },
            {
                title: 'I didn’t receive the invitation or password email',
                steps: [
                    'Check your spam folder.',
                    'Ask the school to check your email address on your record and send the invitation again, or use Forgot password on the sign-in page.',
                ],
            },
            {
                title: 'I lost my phone with the authenticator app',
                steps: [
                    'Sign in with one of your recovery codes instead of the six-digit code.',
                    'If you have none left, ask an administrator to open your account under Administration → Users and choose Turn off two-factor. Then sign in with your password and set it up again.',
                ],
            },
            {
                title: 'Attendance or invoices say there is no current year',
                steps: [
                    'An administrator must create the academic year and mark it current under Academic years.',
                ],
                link: { label: 'Academic years', href: academicYears() },
            },
            {
                title: 'I was signed out',
                steps: [
                    'For safety, you are signed out after a period of inactivity, or straight away if your account is deactivated.',
                ],
            },
        ],
    },
];

export const setupIcon = Landmark;
