<?php

/*
|--------------------------------------------------------------------------
| Settings Registry
|--------------------------------------------------------------------------
|
| Every configurable setting is declared here once. The settings UI, the
| validation rules and the resolution order (user -> school -> global ->
| default) are all driven from this registry, so adding a setting never
| requires new controllers or pages.
|
| Supported types: string, text, boolean, integer, select, color.
| `scopes` lists where a value may be stored: global, school, user.
|
*/

return [

    'cache_ttl' => 3600,

    'groups' => [

        'branding' => [
            'label' => 'Branding',
            'description' => 'Name, colours and theme shown across the application.',
            'settings' => [
                'branding.app_name' => [
                    'label' => 'Display name',
                    'type' => 'string',
                    'default' => null,
                    'rules' => ['nullable', 'string', 'max:80'],
                    'scopes' => ['global', 'school'],
                    'description' => 'Shown in the sidebar and browser title. Defaults to the school or application name.',
                ],
                'branding.primary_color' => [
                    'label' => 'Primary colour',
                    'type' => 'color',
                    'default' => '#4f46e5',
                    'rules' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
                    'scopes' => ['global', 'school'],
                ],
                'branding.default_theme' => [
                    'label' => 'Default theme',
                    'type' => 'select',
                    'default' => 'system',
                    'options' => ['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'],
                    'scopes' => ['global', 'school'],
                    'description' => 'Used until a user picks their own theme under Appearance.',
                ],
            ],
        ],

        'localization' => [
            'label' => 'Localization',
            'description' => 'Language, time zone, date formats and currency.',
            'settings' => [
                'localization.timezone' => [
                    'label' => 'Time zone',
                    'type' => 'string',
                    'default' => 'UTC',
                    'rules' => ['required', 'timezone:all'],
                    'scopes' => ['global', 'school', 'user'],
                ],
                'localization.date_format' => [
                    'label' => 'Date format',
                    'type' => 'select',
                    'default' => 'Y-m-d',
                    'options' => ['Y-m-d' => '2026-01-31', 'd/m/Y' => '31/01/2026', 'm/d/Y' => '01/31/2026', 'd M Y' => '31 Jan 2026'],
                    'scopes' => ['global', 'school', 'user'],
                ],
                'localization.time_format' => [
                    'label' => 'Time format',
                    'type' => 'select',
                    'default' => 'H:i',
                    'options' => ['H:i' => '24-hour (14:30)', 'h:i A' => '12-hour (02:30 PM)'],
                    'scopes' => ['global', 'school', 'user'],
                ],
                'localization.currency' => [
                    'label' => 'Currency code',
                    'type' => 'string',
                    'default' => 'USD',
                    'rules' => ['required', 'string', 'size:3', 'alpha'],
                    'scopes' => ['global', 'school'],
                ],
                'localization.week_starts_on' => [
                    'label' => 'Week starts on',
                    'type' => 'select',
                    'default' => 'monday',
                    'options' => ['sunday' => 'Sunday', 'monday' => 'Monday', 'saturday' => 'Saturday'],
                    'scopes' => ['global', 'school'],
                ],
            ],
        ],

        'academic' => [
            'label' => 'Academic',
            'description' => 'Academic calendar, attendance and grading defaults.',
            'settings' => [
                'academic.year_start_month' => [
                    'label' => 'Academic year starts in',
                    'type' => 'select',
                    'default' => '9',
                    'options' => [
                        '1' => 'January', '2' => 'February', '3' => 'March', '4' => 'April', '5' => 'May', '6' => 'June',
                        '7' => 'July', '8' => 'August', '9' => 'September', '10' => 'October', '11' => 'November', '12' => 'December',
                    ],
                    'scopes' => ['global', 'school'],
                ],
                'academic.school_week' => [
                    'label' => 'School week',
                    'type' => 'select',
                    'default' => 'mon-fri',
                    'options' => [
                        'mon-fri' => 'Monday – Friday',
                        'mon-sat' => 'Monday – Saturday',
                        'sun-thu' => 'Sunday – Thursday',
                        'sat-wed' => 'Saturday – Wednesday',
                    ],
                    'scopes' => ['global', 'school'],
                    'description' => 'Days shown on timetables.',
                ],
                'academic.attendance_mode' => [
                    'label' => 'Attendance mode',
                    'type' => 'select',
                    'default' => 'daily',
                    'options' => ['daily' => 'Once per day', 'period' => 'Every period'],
                    'scopes' => ['global', 'school'],
                ],
                'academic.pass_percentage' => [
                    'label' => 'Pass mark (%)',
                    'type' => 'integer',
                    'default' => 40,
                    'rules' => ['required', 'integer', 'min:0', 'max:100'],
                    'scopes' => ['global', 'school'],
                ],
                'academic.attendance_alert_threshold' => [
                    'label' => 'At-risk attendance threshold (%)',
                    'type' => 'integer',
                    'default' => 75,
                    'rules' => ['required', 'integer', 'min:0', 'max:100'],
                    'scopes' => ['global', 'school'],
                    'description' => 'Students below this attendance rate are flagged on the Insights page.',
                ],
            ],
        ],

        'library' => [
            'label' => 'Library',
            'description' => 'Loan periods, limits and overdue fines.',
            'settings' => [
                'library.loan_days' => [
                    'label' => 'Loan period (days)',
                    'type' => 'integer',
                    'default' => 14,
                    'rules' => ['required', 'integer', 'min:1', 'max:120'],
                    'scopes' => ['global', 'school'],
                ],
                'library.max_loans' => [
                    'label' => 'Books per borrower',
                    'type' => 'integer',
                    'default' => 3,
                    'rules' => ['required', 'integer', 'min:1', 'max:20'],
                    'scopes' => ['global', 'school'],
                ],
                'library.fine_per_day' => [
                    'label' => 'Overdue fine per day (in cents)',
                    'type' => 'integer',
                    'default' => 0,
                    'rules' => ['required', 'integer', 'min:0', 'max:100000'],
                    'scopes' => ['global', 'school'],
                    'description' => 'Use 0 for no fines. 1000 = 10.00 in the school currency.',
                ],
                'library.bill_fines' => [
                    'label' => 'Bill student fines',
                    'type' => 'boolean',
                    'default' => true,
                    'scopes' => ['global', 'school'],
                    'description' => 'Add overdue fines for students to a fee invoice when the book is returned.',
                ],
            ],
        ],

        'notifications' => [
            'label' => 'Notifications',
            'description' => 'Which channels and automatic alerts are enabled.',
            'settings' => [
                'notifications.email_enabled' => [
                    'label' => 'Email notifications',
                    'type' => 'boolean',
                    'default' => true,
                    'scopes' => ['global', 'school', 'user'],
                ],
                'notifications.sms_enabled' => [
                    'label' => 'SMS notifications',
                    'type' => 'boolean',
                    'default' => false,
                    'scopes' => ['global', 'school', 'user'],
                ],
                'notifications.notify_guardians_on_absence' => [
                    'label' => 'Alert guardians when a student is absent',
                    'type' => 'boolean',
                    'default' => true,
                    'scopes' => ['global', 'school'],
                ],
            ],
        ],

        'security' => [
            'label' => 'Security',
            'description' => 'Password policy, two-factor enforcement and session limits. Platform-wide.',
            'settings' => [
                'security.password_min_length' => [
                    'label' => 'Minimum password length',
                    'type' => 'integer',
                    'default' => 8,
                    'rules' => ['required', 'integer', 'min:8', 'max:128'],
                    'scopes' => ['global'],
                ],
                'security.password_require_mixed_case' => [
                    'label' => 'Require upper and lower case letters',
                    'type' => 'boolean',
                    'default' => false,
                    'scopes' => ['global'],
                ],
                'security.password_require_numbers' => [
                    'label' => 'Require numbers',
                    'type' => 'boolean',
                    'default' => false,
                    'scopes' => ['global'],
                ],
                'security.password_require_symbols' => [
                    'label' => 'Require symbols',
                    'type' => 'boolean',
                    'default' => false,
                    'scopes' => ['global'],
                ],
                'security.require_two_factor' => [
                    'label' => 'Require two-factor authentication for all users',
                    'type' => 'boolean',
                    'default' => false,
                    'scopes' => ['global'],
                    'description' => 'Users without 2FA are sent to Security settings until they enable it.',
                ],
                'security.session_idle_timeout' => [
                    'label' => 'Idle session timeout (minutes)',
                    'type' => 'integer',
                    'default' => 0,
                    'rules' => ['required', 'integer', 'min:0', 'max:1440'],
                    'scopes' => ['global'],
                    'description' => '0 disables the idle timeout.',
                ],
            ],
        ],

    ],

];
