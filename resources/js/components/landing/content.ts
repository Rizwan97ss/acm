import type { LucideIcon } from 'lucide-react';
import {
    BarChart3,
    BookOpen,
    Building2,
    Bus,
    CalendarClock,
    ClipboardCheck,
    FileSpreadsheet,
    FileUp,
    GraduationCap,
    HeartHandshake,
    KeyRound,
    Landmark,
    LockKeyhole,
    Megaphone,
    Network,
    PlugZap,
    Rocket,
    School,
    ScrollText,
    Settings2,
    ShieldCheck,
    Sparkles,
    UserRoundCheck,
    Users,
    Wallet,
} from 'lucide-react';

/**
 * Everything written on the landing page lives here, so wording can change without
 * touching layout. Claims must stay true to what the software does today.
 *
 * Fill the "owner" blocks (contact details, social links, legal pages, testimonials,
 * client logos, prices) with verified information only. Empty values are hidden.
 */

export type Tone = 'navy' | 'emerald' | 'gold' | 'sky' | 'crimson' | 'violet';

export const toneTile: Record<Tone, string> = {
    navy: 'from-navy-700 to-royal-500 shadow-navy-500/25',
    emerald: 'from-emerald-600 to-teal-500 shadow-emerald-500/25',
    gold: 'from-gold-500 to-orange-500 shadow-gold-500/30',
    sky: 'from-sky-600 to-cyan-500 shadow-sky-500/25',
    crimson: 'from-crimson-600 to-rose-500 shadow-crimson-500/25',
    violet: 'from-royal-600 to-navy-500 shadow-royal-500/25',
};

export const toneSoft: Record<Tone, string> = {
    navy: 'bg-navy-50 text-navy-700 dark:bg-navy-500/15 dark:text-navy-200',
    emerald:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200',
    gold: 'bg-gold-50 text-gold-800 dark:bg-gold-500/15 dark:text-gold-200',
    sky: 'bg-sky-50 text-sky-700 dark:bg-sky-500/15 dark:text-sky-200',
    crimson:
        'bg-crimson-50 text-crimson-700 dark:bg-crimson-500/15 dark:text-crimson-200',
    violet: 'bg-royal-50 text-royal-700 dark:bg-royal-500/15 dark:text-royal-200',
};

/* ------------------------------------------------------------------ owner */

export const owner = {
    /** Shown in the contact section and footer when filled in. */
    contact: {
        email: '',
        phone: '',
        address: '',
        hours: '',
    },
    /** e.g. { label: 'LinkedIn', href: 'https://linkedin.com/company/…' } */
    social: [] as { label: string; href: string }[],
    /** e.g. { label: 'Privacy policy', href: '/privacy' } — add once the pages exist. */
    legal: [] as { label: string; href: string }[],
    /**
     * Verified customer quotes only, with permission to publish.
     * e.g. { quote: '…', name: 'Jane Doe', role: 'Principal', organization: 'Example School' }
     */
    testimonials: [] as {
        quote: string;
        name: string;
        role: string;
        organization: string;
    }[],
    /** Names of schools or partners you may publicly list. */
    clients: [] as string[],
};

/* ---------------------------------------------------------------- navigation */

export const productMenu: {
    label: string;
    description: string;
    href: string;
    icon: LucideIcon;
}[] = [
    {
        label: 'Modules',
        description: 'Every area of school life, in one system.',
        href: '#modules',
        icon: Sparkles,
    },
    {
        label: 'Feature tour',
        description: 'See how the key workflows fit together.',
        href: '#features',
        icon: Rocket,
    },
    {
        label: 'Security',
        description: 'Encryption, two-factor sign-in and audit trails.',
        href: '#security',
        icon: ShieldCheck,
    },
    {
        label: 'Integrations',
        description: 'Excel import, REST API and webhooks.',
        href: '#integrations',
        icon: PlugZap,
    },
];

export const navLinks = [
    { label: 'How it works', href: '#how-it-works' },
    { label: 'Who it’s for', href: '#use-cases' },
    { label: 'Plans', href: '#plans' },
    { label: 'FAQ', href: '#faq' },
];

/* ---------------------------------------------------------------------- about */

export const values: {
    title: string;
    text: string;
    icon: LucideIcon;
    tone: Tone;
}[] = [
    {
        title: 'One source of truth',
        text: 'Admissions, classes, attendance, results, fees and payroll share the same records, so nothing is typed twice and nothing drifts out of date.',
        icon: Network,
        tone: 'navy',
    },
    {
        title: 'Privacy by default',
        text: 'Sensitive details are encrypted in the database, masked on screen for people who don’t need them, and every change is recorded.',
        icon: LockKeyhole,
        tone: 'emerald',
    },
    {
        title: 'Built for every role',
        text: 'Administrators, teachers, accountants, librarians, students and parents each see exactly what their role allows, nothing more.',
        icon: UserRoundCheck,
        tone: 'gold',
    },
];

/* -------------------------------------------------------------------- modules */

export const modules: {
    title: string;
    text: string;
    icon: LucideIcon;
    tone: Tone;
    points: string[];
}[] = [
    {
        title: 'Students & admissions',
        text: 'From the first application to graduation.',
        icon: GraduationCap,
        tone: 'navy',
        points: [
            'Application → approval → enrolment workflow',
            'Parents shared between siblings',
            'Automatic admission numbers',
        ],
    },
    {
        title: 'Academics',
        text: 'Plan the year and run every lesson.',
        icon: CalendarClock,
        tone: 'violet',
        points: [
            'Years, terms, classes and sections',
            'Timetables with clash detection',
            'Assignments with online submission',
        ],
    },
    {
        title: 'Attendance & results',
        text: 'Daily records and fair, transparent grading.',
        icon: ClipboardCheck,
        tone: 'emerald',
        points: [
            'Daily attendance with parent alerts',
            'Exams, grading scales and ranks',
            'Printable report cards',
        ],
    },
    {
        title: 'Fees & accounting',
        text: 'Every rupee, dollar or dirham accounted for.',
        icon: Wallet,
        tone: 'gold',
        points: [
            'Fee structures, concessions and invoices',
            'Payments, receipts and reversals',
            'Double-entry ledger behind every posting',
        ],
    },
    {
        title: 'HR & payroll',
        text: 'Look after the people who run your school.',
        icon: Landmark,
        tone: 'crimson',
        points: [
            'Teaching and non-teaching staff records',
            'Leave requests with yearly allowances',
            'Monthly payroll runs and payslips',
        ],
    },
    {
        title: 'Communication',
        text: 'Reach the right people, every time.',
        icon: Megaphone,
        tone: 'sky',
        points: [
            'Announcements and events by audience',
            'Private, encrypted messages',
            'In-app notifications',
        ],
    },
    {
        title: 'Library, transport & inventory',
        text: 'The operations that keep the day moving.',
        icon: Bus,
        tone: 'emerald',
        points: [
            'Catalogue, loans and automatic fine invoices',
            'Routes, stops and seat capacity',
            'Stock movements that never go negative',
        ],
    },
    {
        title: 'Records & certificates',
        text: 'Paperwork without the paper chase.',
        icon: ScrollText,
        tone: 'navy',
        points: [
            'Secure document sharing',
            'Numbered, approved certificates',
            'Public verification links',
        ],
    },
    {
        title: 'Insights & reports',
        text: 'Decisions backed by your own data.',
        icon: BarChart3,
        tone: 'violet',
        points: [
            'Attendance and fee trend analysis',
            'Forecasting from historical data',
            'CSV exports for every report',
        ],
    },
];

/* ---------------------------------------------------------------- why choose */

export const reasons: { title: string; text: string; icon: LucideIcon }[] = [
    {
        title: 'Security you can explain to parents',
        text: 'Two-factor sign-in, passkeys, encrypted personal data, automatic sign-out and a full audit trail come as standard.',
        icon: ShieldCheck,
    },
    {
        title: 'Approvals where they matter',
        text: 'Results, expenses, leave, announcements and certificates follow approval steps, and nobody approves their own request.',
        icon: KeyRound,
    },
    {
        title: 'Start with the data you have',
        text: 'Bring students, parents, staff and books across from Excel. Every row is checked before anything is saved.',
        icon: FileUp,
    },
    {
        title: 'Open to your other systems',
        text: 'A versioned REST API and signed webhooks connect mobile apps, accounting tools and anything else you use.',
        icon: PlugZap,
    },
    {
        title: 'Your branding, your rules',
        text: 'Set your name, logo, colours, password policy, school week and more from the settings screen.',
        icon: Settings2,
    },
    {
        title: 'Room to grow',
        text: 'Run branches, add custom roles built on the defaults, and switch modules into daily use at your own pace.',
        icon: Building2,
    },
];

/* --------------------------------------------------------------- how it works */

export const steps: { title: string; text: string; icon: LucideIcon }[] = [
    {
        title: 'Tell us about your school',
        text: 'Send an enquiry to arrange a walkthrough and decide which modules to start with.',
        icon: HeartHandshake,
    },
    {
        title: 'Set up the academic year',
        text: 'Create your year, classes, sections, subjects and bell schedule; the built-in checklist shows what is left.',
        icon: School,
    },
    {
        title: 'Import your people',
        text: 'Fill the Excel templates for students, parents and staff, review the checks, and import in one go.',
        icon: FileSpreadsheet,
    },
    {
        title: 'Invite everyone in',
        text: 'Give staff their roles and invite students and parents to the portal by email.',
        icon: Users,
    },
];

/* ---------------------------------------------------------------- feature tour */

export const tour: {
    id: string;
    label: string;
    title: string;
    text: string;
    points: string[];
    icon: LucideIcon;
    tone: Tone;
}[] = [
    {
        id: 'admissions',
        label: 'Admissions',
        title: 'From enquiry to enrolled, without re-typing',
        text: 'Record an application once. When it’s approved, one click creates the student, links the parent and places them in a section.',
        points: [
            'Reuses an existing parent when the phone or email matches',
            'Enforces section capacity and roll numbers',
            'Notifies connected systems through webhooks',
        ],
        icon: GraduationCap,
        tone: 'navy',
    },
    {
        id: 'attendance',
        label: 'Attendance',
        title: 'Take the register in seconds',
        text: 'Teachers mark their own sections. Parents of absent children are emailed automatically when your settings allow it.',
        points: [
            'Teachers see only the sections they teach',
            'Supervisors can review the whole school',
            'Attendance flows into report cards and analytics',
        ],
        icon: ClipboardCheck,
        tone: 'emerald',
    },
    {
        id: 'fees',
        label: 'Fees',
        title: 'Billing that balances itself',
        text: 'Generate invoices for a whole class in one run, apply each student’s concession, and record payments with receipts.',
        points: [
            'Every posting writes a balanced ledger entry',
            'Re-running a batch never double-bills',
            'Parents see their own children’s invoices',
        ],
        icon: Wallet,
        tone: 'gold',
    },
    {
        id: 'results',
        label: 'Results',
        title: 'Fair results, published when ready',
        text: 'Enter marks per paper, compute grades and ranks automatically, then publish with approval.',
        points: [
            'Custom grading scales per school',
            'Students must pass every paper',
            'Printable report cards include attendance',
        ],
        icon: BookOpen,
        tone: 'violet',
    },
];

/* ---------------------------------------------------------------- use cases */

export const useCases: {
    title: string;
    text: string;
    icon: LucideIcon;
    tone: Tone;
}[] = [
    {
        title: 'Primary & secondary schools',
        text: 'Run admissions, timetables, attendance, exams and fees for one campus from a single system.',
        icon: School,
        tone: 'navy',
    },
    {
        title: 'Schools with branches',
        text: 'Keep each branch’s records separate while leadership switches between them.',
        icon: Building2,
        tone: 'emerald',
    },
    {
        title: 'Growing private schools',
        text: 'Replace spreadsheets and paper registers without losing the data you already have.',
        icon: Rocket,
        tone: 'gold',
    },
];

export const audiences: { role: string; gets: string }[] = [
    {
        role: 'Owners & principals',
        gets: 'Approvals, dashboards, analytics and the full audit trail.',
    },
    {
        role: 'Administrators',
        gets: 'Admissions, enrolment, users, roles and school settings.',
    },
    {
        role: 'Teachers',
        gets: 'Their timetable, registers, marks entry and assignments.',
    },
    {
        role: 'Accountants',
        gets: 'Invoices, payments, expenses, ledger and payroll.',
    },
    {
        role: 'Parents & students',
        gets: 'Attendance, results, homework, invoices and messages.',
    },
];

/* ---------------------------------------------------------------- security */

export const securityPoints = [
    'Two-factor sign-in and passkeys, which you can make mandatory',
    'National IDs, medical notes, addresses, phones and salaries encrypted at rest',
    'Role-based permissions with privilege-escalation guards',
    'Automatic sign-out on idle, password change or deactivation',
    'Audit log of every change and an activity log of every action',
    'Rate-limited sign-in and a strict content security policy',
];

/* ---------------------------------------------------------------- plans */

export const plans: {
    name: string;
    summary: string;
    /** Leave null to show "Contact us for a quote". */
    price: string | null;
    featured?: boolean;
    includes: string[];
}[] = [
    {
        name: 'Single campus',
        summary: 'Everything one school needs to run day to day.',
        price: null,
        includes: [
            'All modules included',
            'Accounts for staff, students and parents',
            'Excel import for your existing data',
            'Two-factor sign-in and encryption',
        ],
    },
    {
        name: 'Multi-branch',
        summary: 'For school groups running more than one campus.',
        price: null,
        featured: true,
        includes: [
            'Everything in Single campus',
            'Branch-level records and switching',
            'Custom roles built on the defaults',
            'Analytics and forecasting',
        ],
    },
    {
        name: 'Custom & integrations',
        summary: 'Connect the ERP to the rest of your systems.',
        price: null,
        includes: [
            'REST API for mobile and partner apps',
            'Signed webhooks for real-time events',
            'Webhook delivery history with automatic retries',
            'Personal API tokens with scoped access',
        ],
    },
];

/* -------------------------------------------------------------------- FAQ */

export const faqs: { question: string; answer: string }[] = [
    {
        question: 'Can we bring our existing student and staff data?',
        answer: 'Yes. Download the Excel template for students and parents, staff, admission applications or library books, fill it in, and upload it. Every row is checked and problems are listed by row number before anything is saved, and the whole file is saved together or not at all.',
    },
    {
        question: 'How is personal information protected?',
        answer: 'National IDs, medical notes, addresses, phone numbers, bank details, salaries and private messages are encrypted in the database. People only see what their role permits, sensitive values are masked for everyone else, and every change is written to an audit log.',
    },
    {
        question: 'Can parents and students use it?',
        answer: 'Yes. Parents and students are invited by email to a portal where they see their own (or their children’s) timetable, attendance, published results, homework, invoices, announcements and messages.',
    },
    {
        question: 'Is there a mobile app?',
        answer: 'The system works in any modern browser on phones, tablets and computers. It also includes a versioned REST API with token sign-in, so a mobile app can be connected to it.',
    },
    {
        question: 'Do you accept online fee payments?',
        answer: 'Payments are recorded with receipts and posted to the ledger today. An online payment gateway is not built in yet; ask us about your preferred provider.',
    },
    {
        question: 'Can we control who sees what?',
        answer: 'Yes. Ten built-in roles cover common jobs, and you can create your own roles based on them. Nobody can grant permissions they don’t hold themselves.',
    },
    {
        question: 'How do we get started?',
        answer: 'Send an enquiry using the form below to arrange a walkthrough. Setup then follows the built-in checklist: academic year, classes and subjects, then importing your people from Excel.',
    },
];
