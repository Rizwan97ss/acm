<?php

/*
|--------------------------------------------------------------------------
| Role-Based Access Control
|--------------------------------------------------------------------------
|
| Every permission is "{module}.{action}". Modules and their allowed actions
| are declared here and synced into the `permissions` table with
| `php artisan rbac:sync`. Default roles are declared using patterns
| (`*`, `students.*`, `*.view`) that are expanded against the registry.
|
*/

$crud = ['view', 'create', 'edit', 'delete', 'export'];
$approvable = [...$crud, 'approve'];

return [

    'super_admin_role' => 'super-admin',

    'cache_ttl' => 3600,

    'modules' => [
        // Platform & administration
        'schools' => ['label' => 'Schools & Branches', 'group' => 'Administration', 'actions' => $crud],
        'users' => ['label' => 'Users', 'group' => 'Administration', 'actions' => $crud],
        'roles' => ['label' => 'Roles & Permissions', 'group' => 'Administration', 'actions' => ['view', 'create', 'edit', 'delete']],
        'settings' => ['label' => 'Settings', 'group' => 'Administration', 'actions' => ['view', 'edit']],
        'audit_logs' => ['label' => 'Audit Logs', 'group' => 'Administration', 'actions' => ['view', 'export']],
        'integrations' => ['label' => 'API & Webhooks', 'group' => 'Administration', 'actions' => ['view', 'create', 'edit', 'delete']],

        // Academics
        'academic_years' => ['label' => 'Academic Years', 'group' => 'Academics', 'actions' => $crud],
        'classes' => ['label' => 'Classes & Sections', 'group' => 'Academics', 'actions' => $crud],
        'subjects' => ['label' => 'Subjects', 'group' => 'Academics', 'actions' => $crud],
        'timetables' => ['label' => 'Timetables', 'group' => 'Academics', 'actions' => $crud],
        'attendance' => ['label' => 'Attendance', 'group' => 'Academics', 'actions' => $approvable],
        'exams' => ['label' => 'Examinations', 'group' => 'Academics', 'actions' => $approvable],
        'grades' => ['label' => 'Grades & Results', 'group' => 'Academics', 'actions' => $approvable],
        'assignments' => ['label' => 'Assignments & Homework', 'group' => 'Academics', 'actions' => $approvable],

        // People
        'admissions' => ['label' => 'Admissions', 'group' => 'People', 'actions' => $approvable],
        'students' => ['label' => 'Students', 'group' => 'People', 'actions' => $crud],
        'guardians' => ['label' => 'Parents & Guardians', 'group' => 'People', 'actions' => $crud],
        'teachers' => ['label' => 'Teachers', 'group' => 'People', 'actions' => $crud],
        'employees' => ['label' => 'Employees', 'group' => 'People', 'actions' => $crud],

        // Finance
        'fees' => ['label' => 'Fee Structures', 'group' => 'Finance', 'actions' => $crud],
        'invoices' => ['label' => 'Invoices', 'group' => 'Finance', 'actions' => $approvable],
        'payments' => ['label' => 'Payments', 'group' => 'Finance', 'actions' => $approvable],
        'expenses' => ['label' => 'Expenses', 'group' => 'Finance', 'actions' => $approvable],
        'accounting' => ['label' => 'Accounting', 'group' => 'Finance', 'actions' => $approvable],
        'payroll' => ['label' => 'Payroll', 'group' => 'Finance', 'actions' => $approvable],

        // HR & Operations
        'leaves' => ['label' => 'Leave Management', 'group' => 'Operations', 'actions' => $approvable],
        'library' => ['label' => 'Library', 'group' => 'Operations', 'actions' => $crud],
        'transport' => ['label' => 'Transport', 'group' => 'Operations', 'actions' => $crud],
        'inventory' => ['label' => 'Inventory', 'group' => 'Operations', 'actions' => $crud],

        // Communication
        'announcements' => ['label' => 'Announcements', 'group' => 'Communication', 'actions' => $approvable],
        'events' => ['label' => 'Events', 'group' => 'Communication', 'actions' => $crud],
        'messages' => ['label' => 'Messages & Notifications', 'group' => 'Communication', 'actions' => ['view', 'create', 'delete']],
        'documents' => ['label' => 'Documents', 'group' => 'Communication', 'actions' => $crud],
        'certificates' => ['label' => 'Certificates', 'group' => 'Communication', 'actions' => $approvable],

        // Insights
        'reports' => ['label' => 'Reports', 'group' => 'Insights', 'actions' => ['view', 'export']],
        'analytics' => ['label' => 'Analytics', 'group' => 'Insights', 'actions' => ['view', 'export']],
    ],

    /*
    | Default system roles. `school_id` is null for these, so they are shared
    | templates across schools. Schools create custom roles that may inherit
    | from a system role via `parent`.
    */
    'roles' => [
        'super-admin' => [
            'name' => 'Super Admin',
            'description' => 'Unrestricted platform access across all schools.',
            'permissions' => ['*'],
        ],
        'school-admin' => [
            'name' => 'School Admin',
            'description' => 'Full administration of a single school.',
            'permissions' => ['*'],
            'except' => ['schools.create', 'schools.delete'],
        ],
        'principal' => [
            'name' => 'Principal',
            'description' => 'Academic leadership with approval rights.',
            'permissions' => ['*.view', '*.export', '*.approve', 'announcements.*', 'events.*', 'certificates.*'],
            'except' => ['schools.export', 'settings.view'],
        ],
        'teacher' => [
            'name' => 'Teacher',
            'description' => 'Manages classes, attendance, exams and assignments.',
            'permissions' => [
                'classes.view', 'subjects.view', 'timetables.view', 'students.view', 'guardians.view',
                'attendance.view', 'attendance.create', 'attendance.edit',
                'exams.view', 'grades.view', 'grades.create', 'grades.edit',
                'assignments.view', 'assignments.create', 'assignments.edit', 'assignments.delete', 'announcements.view', 'events.view', 'messages.*', 'leaves.view', 'leaves.create',
                'library.view', 'documents.view',
            ],
        ],
        'student' => [
            'name' => 'Student',
            'description' => 'Views own timetable, results, assignments and fees.',
            'permissions' => [
                'timetables.view', 'attendance.view', 'exams.view', 'grades.view', 'assignments.view',
                'invoices.view', 'announcements.view', 'events.view', 'messages.view', 'library.view', 'documents.view',
                'transport.view', 'certificates.view',
            ],
        ],
        'parent' => [
            'name' => 'Parent',
            'description' => 'Views linked children\'s progress, attendance and fees.',
            'permissions' => [
                'timetables.view', 'attendance.view', 'grades.view', 'assignments.view', 'invoices.view',
                'payments.view', 'announcements.view', 'events.view', 'messages.view',
                'messages.create', 'documents.view', 'library.view', 'transport.view', 'certificates.view',
            ],
        ],
        'accountant' => [
            'name' => 'Accountant',
            'description' => 'Manages fees, invoices, payments and expenses.',
            'permissions' => ['fees.*', 'invoices.*', 'payments.*', 'expenses.*', 'accounting.*', 'students.view', 'guardians.view', 'reports.*'],
            'except' => ['invoices.approve', 'expenses.approve'],
        ],
        'hr' => [
            'name' => 'HR Manager',
            'description' => 'Manages employees, payroll and leave.',
            'permissions' => ['employees.*', 'teachers.*', 'payroll.*', 'leaves.*', 'reports.view'],
        ],
        'librarian' => [
            'name' => 'Librarian',
            'description' => 'Manages the library catalogue and circulation.',
            'permissions' => ['library.*', 'students.view', 'teachers.view'],
        ],
        'receptionist' => [
            'name' => 'Receptionist',
            'description' => 'Front desk: admissions enquiries, visitors and messages.',
            'permissions' => ['admissions.view', 'admissions.create', 'admissions.edit', 'students.view', 'guardians.view', 'events.view', 'announcements.view', 'messages.*'],
        ],
    ],

];
