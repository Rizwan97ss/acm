# School ERP — Architecture

This document describes how the system is built and how new modules plug into it. Phase 1 (foundation) is implemented; later phases are specified here so every module follows the same patterns.

## 1. System overview

```
┌──────────────────────────┐      Inertia (session + CSRF)      ┌────────────────────────────┐
│ React 19 + TypeScript    │ ◄────────────────────────────────► │ Laravel 13 (PHP 8.4)       │
│ Tailwind 4, Inertia v3   │                                    │ • auth, RBAC, tenancy      │
│ pages / components /     │                                    │ • all business logic       │
│ hooks / Wayfinder routes │                                    │ • the only writer to MySQL │
└──────────────────────────┘                                    └─────┬──────────────┬───────┘
                                                                      │              │ signed HTTP (HMAC)
                                                         read/write   │              ▼
                                                                      ▼      ┌──────────────────────┐
                                                               ┌──────────┐  │ Python analytics     │
                                                               │  MySQL   │  │ FastAPI, Pandas,     │
                                                               │ (primary)│  │ NumPy, Plotly        │
                                                               └──────────┘  │ stateless, no DB     │
                                                                             └──────────────────────┘
```

**Responsibilities are strictly split:**

| Layer   | Owns                                                                                             | Never does                                                              |
| ------- | ------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------- |
| Laravel | Authentication, authorization, validation, business rules, transactions, all writes, audit trail | Heavy statistics or forecasting                                         |
| React   | Presentation, interaction, client-side UX checks                                                 | Authorization decisions (UI hiding is a convenience only)               |
| Python  | Statistics, forecasting, interactive HTML reports over datasets Laravel sends                    | Touches the database, authenticates end users, is called by the browser |

### Why Inertia rather than a separate SPA + REST API

The project was scaffolded with the Laravel React starter kit (Inertia v3, Fortify, Wayfinder). Inertia keeps **session authentication with CSRF protection**, server-side routing and authorization, and typed route helpers, without maintaining a parallel token-auth API for the web app. Controllers are still resource-oriented (`index/create/store/edit/update/destroy`) and return API Resources, so exposing them as JSON later is mechanical.

A versioned public API (`/api/v1`, for mobile apps or third-party integrations) needs token auth. That requires adding `laravel/sanctum`, which needs your approval as a new dependency (see §10).

## 2. Implemented foundation (phase 1)

| Area                 | What exists                                                                                                                                                                                                                                                        |
| -------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Authentication       | Fortify login, registration, password reset, email verification, TOTP 2FA with recovery codes, passkeys, rate-limited login and 2FA. Deactivated accounts are rejected at login.                                                                                   |
| Account security     | Mandatory-2FA policy, idle-session timeout, sign-out when the account or its school is deactivated or deleted, a settings-driven password policy (production enforces a minimum of 12 characters with mixed case, numbers, symbols and a breached-password check). |
| Multi-school tenancy | `schools` with one level of branches; users are school-bound or platform-level; platform users switch the active school context.                                                                                                                                   |
| RBAC (custom)        | Permission registry, system and school roles, role inheritance, per-user cached resolution, gate integration, privilege-escalation guards.                                                                                                                         |
| Settings             | Registry-driven settings resolved user → school → global → default, with generic UI and validation.                                                                                                                                                                |
| Audit log            | Model changes, role and permission changes, settings changes and authentication events, with sensitive values masked.                                                                                                                                              |
| Field encryption     | Laravel `encrypted` cast plus HMAC blind indexes for exact-match search (`users.phone`).                                                                                                                                                                           |
| Dashboard            | Permission-aware widget registry with deferred loading.                                                                                                                                                                                                            |
| Admin UI             | Users, roles (permission matrix), schools (logo upload), settings, audit log, personal preferences.                                                                                                                                                                |

## 3. Code organisation

```
app/
  Concerns/            Model traits: Auditable, BelongsToSchool, HasBlindIndexes, HasRoles (+ validation rule traits)
  Console/Commands/    rbac:sync
  Enums/               SettingScope …
  Http/
    Controllers/       Thin controllers; Admin/ for administration, one folder per module later
    Middleware/        ResolveCurrentSchool, EnforceAccountSecurity, HandleInertiaRequests, HandleAppearance
    Requests/          Form Requests: authorize() via policies, then validation, then typed payload()
    Resources/         API Resources: the only shape sent to the frontend
  Listeners/           RecordAuthenticationActivity (auto-discovered)
  Models/
  Policies/            Permission check + tenant boundary + business invariants
  Services/
    Audit/  Dashboard/  Rbac/  Security/  Settings/  Tenancy/  Users/
config/
  rbac.php             Modules, actions, default roles  ← add a module's permissions here
  settings.php         Settings registry                ← add a setting here
  security.php         Blind-index key

resources/js/
  components/          Reusable app components (data/, charts/, admin/, settings/), ui/ primitives
  hooks/               use-permissions, use-format, use-branding, use-table-filters …
  layouts/
  pages/               One folder per module: pages/admin/users/{index,create,edit}.tsx
  routes/, actions/    Wayfinder-generated typed routes (do not edit)
  types/               Shared prop and model types
analytics/             (phase 6) Python FastAPI service, deployed separately
```

**Conventions:** controllers authorize, delegate to a service and redirect with a flash toast. Services own transactions, invariants and audit entries. Use repositories only where a query is reused across services; Eloquent is the data-access layer.

## 4. Multi-tenancy

**Tenancy mode** (`TENANCY_MODE` in `.env`, read through `App\Services\Tenancy\Tenancy`):

- `single` (default): one institute, created on first use from `INSTITUTE_NAME` / `INSTITUTE_CODE`. Everyone works in it automatically. The school switcher, school creation and deletion are disabled, "Schools" becomes "Institute profile", and the settings screens edit the institute-wide (global) values, including security.
- `multi`: several schools or branches, as described below.

The data model is multi-tenant in both modes, so moving to multi later only means changing this value. The automated tests run in `multi` mode (set in `phpunit.xml`) to keep cross-school isolation covered, and `SingleInstituteModeTest` covers single mode.

- `users.school_id` is **null for platform users** (super admins, platform support) and set for everyone else.
- `ResolveCurrentSchool` sets the active school on every request. School-bound users are locked to their own school; platform users choose a school with the sidebar switcher (`session.current_school_id`) or work across all schools.
- **School-owned models use the `BelongsToSchool` trait.** It adds a global scope (`where school_id = current`) and stamps `school_id` on create. Every module table (students, invoices, attendance, …) must have an indexed `school_id` foreign key and use this trait.
- Policies also check the tenant boundary explicitly, as defence in depth for route-model binding.
- If a school-bound user's school is missing or inactive, the user is signed out rather than falling back to the unscoped global context.

## 5. RBAC

### Model

```
permissions (name = "module.action", module, action)   ← synced from config/rbac.php
roles (school_id NULL = platform-wide, parent_id → roles, is_system)
permission_role, role_user
```

- **System roles** (`school_id` null, `is_system`) are shared templates; only super admins edit them. `rbac:sync` creates missing ones but never overwrites changes unless `--reset-roles` is passed.
- **School roles** belong to one school and may **inherit** from a parent role (system or same-school). Inheritance is resolved recursively with cycle and depth guards.
- **Super Admin** bypasses permission checks (`PermissionResolver::isSuperAdmin`) but not business invariants (for example, a user can't delete their own account).
- Effective permissions are cached per user and invalidated globally by bumping `rbac:version` whenever roles or assignments change.

### Enforcement

| Where                       | How                                                                                                |
| --------------------------- | -------------------------------------------------------------------------------------------------- |
| Routes                      | `->middleware('can:audit_logs.view')`: dotted abilities resolve through `Gate::before`             |
| Controllers / Form Requests | `Gate::authorize('update', $user)` → policies                                                      |
| Policies                    | permission + tenant boundary + "the actor's permissions cover the target's permissions"            |
| Escalation                  | `RoleService` and `UserService` refuse to grant permissions or assign roles the actor doesn't hold |
| Frontend                    | `usePermissions().can('users.view')` hides UI only                                                 |

### Permission matrix (default system roles)

Actions: **V**iew, **C**reate, **E**dit, **D**elete, **A**pprove, e**X**port. The source of truth is `config/rbac.php`.

| Module group                                   | School Admin | Principal             | Teacher                   | Student    | Parent                    | Accountant                        | HR        | Librarian  | Receptionist |
| ---------------------------------------------- | ------------ | --------------------- | ------------------------- | ---------- | ------------------------- | --------------------------------- | --------- | ---------- | ------------ |
| Schools                                        | V E X        | V                     | –                         | –          | –                         | –                                 | –         | –          | –            |
| Users / Roles / Settings                       | all          | V (no settings)       | –                         | –          | –                         | –                                 | –         | –          | –            |
| Audit log                                      | V X          | V X                   | –                         | –          | –                         | –                                 | –         | –          | –            |
| Academic years, classes, subjects              | all          | V X                   | V                         | –          | –                         | –                                 | –         | –          | –            |
| Timetables                                     | all          | V X                   | V                         | V          | V                         | –                                 | –         | –          | –            |
| Attendance                                     | all          | V X A                 | V C E                     | V          | V                         | –                                 | –         | –          | –            |
| Exams / Grades                                 | all          | V X A                 | V; grades C E             | V          | grades V                  | –                                 | –         | –          | –            |
| Assignments & homework                         | all          | V X                   | all                       | V          | V                         | –                                 | –         | –          | –            |
| Admissions                                     | all          | V X A                 | –                         | –          | –                         | –                                 | –         | –          | V C E        |
| Students / Guardians                           | all          | V X                   | V                         | –          | –                         | V                                 | –         | students V | V            |
| Teachers / Employees                           | all          | V X                   | –                         | –          | –                         | –                                 | all       | teachers V | –            |
| Fees, invoices, payments, expenses, accounting | all          | V X A                 | –                         | invoices V | invoices V, payments V C  | all (no invoice/expense approval) | –         | –          | –            |
| Payroll / Leave                                | all          | V X A                 | leave V C                 | –          | leave C                   | –                                 | all       | –          | –            |
| Library                                        | all          | V X                   | V                         | V          | –                         | –                                 | –         | all        | –            |
| Transport / Inventory                          | all          | V X                   | –                         | –          | –                         | –                                 | –         | –          | –            |
| Announcements / Events                         | all          | all                   | V                         | V          | V                         | –                                 | –         | –          | V            |
| Messages / Documents / Certificates            | all          | V X; certificates all | messages all, documents V | V          | messages V C, documents V | –                                 | –         | –          | messages all |
| Reports / Analytics                            | all          | V X                   | –                         | –          | –                         | reports all                       | reports V | –          | –            |

Super Admin: everything, in every school.

## 6. Database

### Implemented tables

| Table             | Key columns                                                                                                                                         | Notes                       |
| ----------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------- |
| `schools`         | `parent_id → schools`, `code` unique, `is_active` idx, soft deletes                                                                                 | Branches are one level deep |
| `users`           | `school_id → schools` (null = platform), `phone` (encrypted), `phone_index` (HMAC, idx), `is_active`, `last_login_at`, `(school_id, is_active)` idx |                             |
| `permissions`     | `name` unique, `module` idx                                                                                                                         |                             |
| `roles`           | `school_id`, `parent_id → roles`, `(school_id, slug)` unique                                                                                        |                             |
| `permission_role` | PK `(role_id, permission_id)`                                                                                                                       |                             |
| `role_user`       | PK `(user_id, role_id)`, idx `role_id`                                                                                                              |                             |
| `settings`        | `(scope, scope_id, key)` unique, `value` JSON                                                                                                       | `scope_id = 0` for global   |
| `audit_logs`      | `school_id`, `user_id`, `event`, morphs `auditable`, `old_values`/`new_values` JSON, idx `(school_id, created_at)`, `(event, created_at)`           | Append-only                 |

### Planned core relationships (later phases)

```
schools 1─* academic_years 1─* terms
schools 1─* classes 1─* sections *─* subjects (class_subject, with teacher_id)
sections 1─* timetable_slots (day, period, subject_id, teacher_id, room)
students (user_id?, school_id, admission_no unique per school, national_id enc + index)
students *─* guardians (guardian_student: relationship, is_primary)
enrollments (student_id, section_id, academic_year_id, roll_no)      ← history of placement
attendance_records (enrollment_id, date, period?, status, marked_by) unique(enrollment_id, date, period)
exams 1─* exam_subjects 1─* marks (enrollment_id, score) → grade via grading_scales
assignments 1─* submissions
fee_structures 1─* fee_items; invoices 1─* invoice_lines; payments *─1 invoices (+ ledger_entries)
employees (user_id, school_id, salary data enc) 1─* payroll_items; leave_requests (approver_id)
library_items 1─* loans; vehicles/routes/stops 1─* transport_assignments
inventory_items 1─* stock_movements; expenses (approved_by)
announcements/events/messages (audience polymorphic); documents/certificates (file paths, verify hash)
```

**Rules for every new table:** an indexed `school_id` FK (via `BelongsToSchool`), FKs with explicit delete behaviour, composite indexes matching list filters, money as `decimal(12,2)`, and encrypted PII (national IDs, medical notes, bank details) in `text` columns with a blind index only when exact lookup is required. Never encrypt columns used in joins, sorting or ranges.

## 7. Routes / API structure

| Prefix                  | Purpose                                                                       | Example                                                                                                                                           |
| ----------------------- | ----------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| `/dashboard`            | Role-aware dashboard                                                          | `GET /dashboard`                                                                                                                                  |
| `/settings/*`           | The signed-in user's own account (profile, security, appearance, preferences) | `PUT /settings/preferences/{group}`                                                                                                               |
| `/admin/*`              | Administration (resource controllers)                                         | `GET/POST /admin/users`, `PUT /admin/roles/{role}`, `POST /admin/schools/{school}/switch`, `PUT /admin/settings/{group}`, `GET /admin/audit-logs` |
| `/{module}/*`           | Domain modules (phase 2 onward)                                               | `/academics/classes`, `/finance/invoices`                                                                                                         |
| `/api/v1/*`             | Public token API (pending Sanctum approval)                                   | `GET /api/v1/students`                                                                                                                            |
| `/insights`, `/reports` | Analytics (deferred, cached Python results) and CSV exports                   | `GET /insights`, `GET /insights/report`, `GET /reports/{report}.csv?from=&to=`                                                                    |

The frontend never hardcodes URLs: import from `@/routes/...` or `@/actions/...` (Wayfinder).

## 8. Python analytics service (phase 6)

- **Location:** `analytics/` (FastAPI, Pandas, NumPy, Plotly; pinned in `analytics/requirements.txt`). It is stateless and listens on localhost or a private network only.
- **No database access.** Laravel stays the only component that talks to MySQL. `AnalyticsDatasets` builds small, pre-aggregated datasets through Eloquent, so the current-school scope always applies. They contain internal IDs and counts or percentages only: no names, no encrypted fields. `InsightsService` attaches student names to the results after they come back. This replaces the earlier plan of a read-only DB user: it is simpler to secure, and tenancy cannot be bypassed.
- **Auth between services:** `AnalyticsClient` signs each request with `X-Timestamp` and `X-Signature = HMAC-SHA256(ANALYTICS_SHARED_SECRET, "{ts}.{METHOD}.{path}.{sha256(body)}")`. Python (`app/security.py`) rejects a bad signature, a clock skew over 60 seconds, or a body over 5 MB, and compares in constant time. The secret must be at least 32 characters. Docs and OpenAPI endpoints are disabled; only `/health` is unauthenticated.
- **Flow:** React → `GET /insights` (`analytics.view`, plus each section's module permission: `attendance.view`, `exams.view`, `accounting.view`) → Inertia deferred props → `InsightsService` → Python → JSON. Successful results are cached for 10 minutes per school (`ANALYTICS_CACHE_MINUTES`). Failures are never cached. If the service is down, each section shows an "unavailable" state and the rest of the app is unaffected.
- **Endpoints (all `POST`):**
    - `/v1/attendance/trends`: daily rate, 7-day moving average, weekday pattern, weekly trend slope (NumPy polyfit).
    - `/v1/attendance/at-risk`: rate below the `academic.attendance_alert_threshold` setting, 3+ absences in 14 days, or frequent lateness, ranked by a risk score with z-scores.
    - `/v1/performance/summary`: the latest published exam's subject mean, median, std and pass rate, the score distribution, the attendance–score correlation, and improvers and decliners versus the previous exam.
    - `/v1/finance/forecast`: 12-month billed, collected and expense history; a Holt linear-trend forecast with an 80% band; receivables ageing (not due, 1–30, 31–60, 61–90, 90+).
    - `/v1/reports/html`: a self-contained interactive Plotly report (downloaded via `GET /insights/report`, needs `analytics.export`, audited).
- **CSV exports** are generated in Laravel (`ReportCatalog` and `CsvExporter`). They need `reports.export` plus view access to the module, and cover the student roll, attendance register, invoices, payments and expenses. Rows are streamed with a UTF-8 BOM, limited to one year per file, and audited (`report.exported`). Cells beginning with `= + - @` are prefixed with `'` to stop formula injection. Encrypted personal fields and salaries are not exportable.
- **Running it:**

```bash
cd analytics
python -m venv .venv && .venv/Scripts/pip install -r requirements.txt   # .venv/bin/pip on Linux/macOS
export ANALYTICS_SHARED_SECRET=...   # same value as in Laravel's .env (PowerShell: $env:ANALYTICS_SHARED_SECRET="...")
.venv/Scripts/python -m uvicorn app.main:create_app --factory --host 127.0.0.1 --port 8100
.venv/Scripts/python -m pytest        # service tests
```

In production, run it under a process manager (systemd or Supervisor) bound to `127.0.0.1`, or on a private network, with several workers (`--workers 2`).

## 9. Security checklist

- Passwords are hashed with bcrypt (`hashed` cast). They are never stored with reversible encryption.
- Sensitive fields use Laravel's `encrypted` cast (AES-256-CBC with MAC, keyed by `APP_KEY`). Decrypted values are only sent to users authorized to edit the record; others see masked values (see `UserResource`).
- Blind indexes use a **separate key** (`BLIND_INDEX_KEY`; otherwise one derived from `APP_KEY` with a domain separator). Rotating it requires recomputing indexes.
- Key management: keep `APP_KEY` and `BLIND_INDEX_KEY` in a secrets manager. For `APP_KEY` rotation, use `APP_PREVIOUS_KEYS` so existing ciphertext stays readable. Back up the keys separately from database backups.
- CSRF is built into Inertia (XSRF cookie). Login, 2FA and password routes are rate limited.
- The audit trail redacts hidden attributes, masks encrypted ones and never records attempted passwords.
- Uploads: logos are restricted to PNG/JPG/WebP (no SVG, which can carry script), with size and dimension limits, stored with generated names.
- Production: HTTPS only (`SESSION_SECURE_COOKIE=true`, HSTS at the proxy), `APP_DEBUG=false`, encrypted off-site backups, and `composer audit` in CI.

## 10. Setup and integration

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# set DB_* for MySQL, optionally BLIND_INDEX_KEY (php -r "echo bin2hex(random_bytes(32));")
php artisan migrate
php artisan db:seed            # rbac:sync always; demo school + accounts only outside production
php artisan storage:link       # school logos
composer run dev
# optional: the analytics service (section 8); set ANALYTICS_SHARED_SECRET in .env
```

Demo accounts (all with the password `password`, local only): `admin@example.com` (Super Admin), `school.admin@example.com`, `principal@example.com`, `teacher@example.com`, `accountant@example.com`, `hr@example.com`, `librarian@example.com`, `reception@example.com`, `student@example.com`, `parent@example.com`, `north.admin@example.com` (branch admin).

Run `php artisan rbac:sync` on every deploy, after migrations.

## 11. Adding a module (checklist)

1. Declare the module and its actions in `config/rbac.php`, add defaults for the system roles, and run `rbac:sync`.
2. Create the migration with `school_id` and indexes, and a model using `BelongsToSchool` (plus `Auditable`, and `HasBlindIndexes` if needed), with a factory.
3. Write the policy (permission + tenant boundary), the Form Requests (`authorize()` + rules + typed `payload()`), and a service for multi-step writes inside a transaction.
4. Add a resource controller and API Resource, and routes in the module's route file.
5. Add pages under `resources/js/pages/{module}/` built from `DataTable`, `Pagination`, `SearchInput`, `FormField` and `ConfirmDelete`, plus a nav item with `permissions` in `app-sidebar.tsx`.
6. Add dashboard widgets by implementing `DashboardWidget` and registering them in `DashboardService::WIDGETS`.
7. Write feature tests covering access per role, cross-school isolation, validation, and the happy path.

## 12. Roadmap

| Phase | Modules                                                                                                                   |
| ----- | ------------------------------------------------------------------------------------------------------------------------- |
| 1 ✅  | Auth, RBAC, tenancy, settings, audit, dashboard, admin UI                                                                 |
| 2 ✅  | Academic years and terms, classes and sections, subjects, bell schedule, timetable with clash detection                   |
| 3 ✅  | Admissions workflow, students, guardians, staff (teaching and non-teaching), enrollment, portal accounts                  |
| 4 ✅  | Attendance, examinations, grading scales, results and report cards, assignments and homework, "My progress"               |
| 5 ✅  | Fees, invoices, payments, expenses, double-entry accounting, payroll, leave                                               |
| 6 ✅  | Python analytics service, reports and exports, forecasting                                                                |
| 7 ✅  | Library, transport, inventory, communication (announcements, events, messages, notifications), documents and certificates |
| 8     | Public API (`/api/v1`, Sanctum), mobile-ready endpoints, webhooks                                                         |

### Phase 2 notes

- Academic modules live under `/academics/*` behind `RequireSchoolContext`; platform users pick a school first.
- `ResolveCurrentSchool` is registered ahead of `SubstituteBindings` in the middleware priority list, so route-model binding is tenant-scoped: another school's record returns 404.
- `TeacherDirectory` defines who can be scheduled: active users linked to an active teaching staff record, or holding the system `teacher` role (or a role inheriting from it).

### Phase 3 notes

- People modules live under `/people/*` (students, guardians, staff, admissions).
- **Reference numbers** (`ReferenceNumberGenerator`): admission numbers (`GHS260001`), employee numbers (`GHS-E0001`) and application numbers (`APP-2026-00001`) are generated under an atomic cache lock while the record is created. The unique indexes are the backstop. Staff may type a number instead.
- **Enrollment:** one enrollment per student per academic year. Enrolling again in the same year transfers the student. Section capacity and roll-number uniqueness are enforced, and sections or classes with enrolled students cannot be removed.
- **Guardians** are shared between siblings. Each student has exactly one primary guardian, maintained by `GuardianService`. Admissions reuse an existing guardian matched by exact phone (via its blind index) or email.
- **Admissions:** pending → approved | rejected (requires `admissions.approve`); approved → enrolled (also requires `students.create`). Enrolling creates the student, the guardian link and the enrollment in one transaction.
- **Staff:** `employees.*` covers all staff, while `teachers.*` covers teaching staff only. The list, the forms and the policy all enforce this.
- **Portal accounts:** staff never choose passwords for students or parents. "Invite to portal" creates the user with the `student` or `parent` role, is subject to the same no-escalation check as user management, and emails a password-reset link.
- **Confidential fields:** national IDs, medical notes, addresses, phones and bank details are encrypted. They are decrypted in API resources only for users who may edit the record; everyone else gets masked values (`MasksSensitiveValues`).
- **Portal scoping** (`PortalScope`): users linked to a student or guardian record, and not to a staff record, see only their own or their children's sections on the timetable and the dashboard. This closes the phase 2 limitation.

### Phase 4 notes

- **Teaching scope** (`TeachingScope`): holding `{module}.approve` (attendance, grades, assignments) makes a user a supervisor across the school. Everyone else works only with sections they are class teacher of or teach on the timetable. Mark entry is subject-level: the subject's timetable teacher or the section's class teacher.
- **Attendance** is recorded daily, one record per student per day; late counts as present and excused days are not counted. Non-supervisors can record today and the previous seven days; nobody can record future dates. Newly absent students trigger a queued email (`StudentAbsentNotification`) to emergency-contact guardians when the notification settings allow it. Period-wise attendance (the `academic.attendance_mode = period` setting) is not implemented yet.
- **Grading scales:** bands run from each minimum percentage up to the next band, and the lowest band must start at 0%. A "Standard" A+–F scale is created on first use.
- **Exams → papers → marks.** `ResultService` computes totals, percentages, grades, pass/fail (a student must pass every paper) and competition ranks ("1, 2, 2, 4") per section; only students with every mark entered are ranked. Publishing requires `exams.approve` and at least one mark; published exams are read-only until unpublished.
- **Report cards** are printable and include attendance. Staff can see them any time within their teaching scope; students and guardians only for published exams and only for their own children.
- **Assignments:** students submit text answers and can resubmit until the work is graded; late submissions are flagged; teachers grade within the assignment's maximum. File attachments are not implemented yet.
- **My progress** (`/academics/progress`) gives students and guardians attendance, published results and work due soon for each child.

### Phase 5 notes

- **Money** is stored as integer minor units (cents) everywhere (`App\Services\Finance\Money` converts typed amounts), so arithmetic never uses floats. The UI formats amounts in the `localization.currency` setting. Two-decimal currencies are assumed.
- **Double-entry ledger** (`LedgerService`): system chart of accounts (1000 Cash, 1010 Bank, 1100 Fees receivable, 2100 Payroll deductions payable, 3000 Retained earnings, 4000 Fee income, 4900 Other income, 5000 Operating expenses, 5100 Salaries). Every posting balances or is refused. Entries are never edited: voids and reversals post reversing entries linked through `reverses_id`.
    - Invoice issued: Dr Fees receivable / Cr Fee income.
    - Payment: Dr Cash (cash) or Bank (all other methods) / Cr Fees receivable.
    - Approved expense: Dr Operating expenses / Cr Cash or Bank.
    - Payroll paid: Dr Salaries (earned pay) / Cr Bank (net) / Cr Payroll deductions payable (deductions other than unpaid leave).
- **Billing:** fee items per academic year and class (or every class) → invoice batches per student, with each student's standing concession applied per line. Re-running a batch with the same title skips students who are already billed. Payments never exceed the balance; invoice status (unpaid → partly paid → paid) is derived. Invoices with payments must have those payments reversed (`payments.approve`) before they can be voided (`invoices.delete`). Guardians and students see only their own children's invoices and receipts, and cannot record payments. There is no online payment gateway yet.
- **Expenses** are pending until approved by someone other than the submitter (segregation of duties; super admins excepted). Only approved expenses reach the ledger.
- **Leave:** default types are created on first use. Days are counted on school days per the school-week setting. Overlapping requests and requests beyond the remaining yearly allowance are refused. Nobody reviews their own request.
- **Payroll:** salary profiles, payslip amounts and breakdowns are encrypted at rest. A draft run can be recalculated; approve → pay. Approved unpaid leave in the month is deducted at basic ÷ school days × days. Staff see their own payslips once a run is approved.

### Phase 7 notes

- **Audiences** (`App\Enums\Audience`, `TargetsAudience`, `AudienceResolver`): announcements and events go to everyone, staff, students or guardians, and student or guardian audiences can be narrowed to one class. Readers see only live items addressed to them (a guardian sees their children's classes); managers (`announcements.create`/`approve`, `events.create`) see everything.
- **Announcements** are drafts until someone with `announcements.approve` publishes them. Publishing sends an in-app `AnnouncementPublished` notification (database channel, queued) to every active account in the audience except the publisher. Unpublishing returns an announcement to drafts; editing a published one does not notify again.
- **Messages:** private conversations with bodies encrypted at rest. Notifications never copy message text. Staff can write to staff and guardians, guardians can write to staff, and students can read but not write (safeguarding). Non-participants get a 404. "Leave" removes the thread only for that person.
- **Notifications:** the bell in the header shows `auth.unreadNotifications`. `/notifications/{id}` marks a notification read and follows its link (relative URLs only).
- **Library:** catalogue (`library_books`, with copies owned and available) and loans to a student (admission number) or an employee (staff number). The book row is locked while a copy is issued or returned. Loans are refused when no copy is left, the borrower has an overdue book or has reached `library.max_loans`, or already holds that title. The due date is `library.loan_days` from issue. Late returns record a fine of days late × `library.fine_per_day` (cents). Fines are recorded only and not yet invoiced. Students and guardians see only their own or their children's loans.
- **Transport:** routes with ordered stops (pick-up and drop-off times), a vehicle, a driver and a seat capacity. Each student has at most one seat. Assigning locks the route row so capacity can never be exceeded, and the stop must belong to the route. Capacity cannot be lowered below the current riders, and a route with riders cannot be deleted. Editing stops keeps their ids so students' chosen stops survive. Students and guardians see only their own seat: route, stop, times and driver contact.
- **Inventory:** items with a unit, location and reorder level. Quantity changes only through append-only movements (`in`, `out`, or `adjust` = stock count), each storing the balance afterwards, under a row lock. Stock never goes negative. Items still holding stock cannot be deleted.
- **Documents:** uploads (PDF, Office, text, CSV, PNG/JPG, at most 10 MB, checked by both extension and detected MIME type) are stored on the private `local` disk under random names. They are always served through `DocumentController@download` as an `application/octet-stream` attachment with `nosniff`, and only to people in the document's audience. Other people get a 404.
- **Certificates:** a draft is prefilled from the student's record (bonafide, character, transfer, achievement) and numbered `CRT-YYYY-NNNNN`. Its wording is editable until someone with `certificates.approve` issues it. Issued certificates can be revoked with a reason but never edited or deleted. The printable A4 page (`standalone/certificate`) watermarks drafts and revoked certificates and carries a public verification link, `/verify/{32-char code}` (rate limited). That link shows only what is printed on the certificate: title, serial number, name, school and date. Students and guardians see and print only their own issued certificates.
- **Portal permissions:** the student and parent roles gained `transport.view` and `certificates.view`, and parents also gained `library.view`. Run `php artisan rbac:sync` after deploying.

