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
                                                               │  MySQL   │◄─┤ FastAPI, Pandas,     │
                                                               │ (primary)│  │ NumPy, Plotly        │
                                                               └──────────┘  │ read-only DB user    │
                                                                             └──────────────────────┘
```

**Responsibilities are strictly split:**

| Layer   | Owns                                                                                             | Never does                                                              |
| ------- | ------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------- |
| Laravel | Authentication, authorization, validation, business rules, transactions, all writes, audit trail | Heavy statistics or forecasting                                         |
| React   | Presentation, interaction, client-side UX checks                                                 | Authorization decisions (UI hiding is a convenience only)               |
| Python  | Aggregation, statistics, forecasting, chart data                                                 | Writes business data, authenticates end users, is called by the browser |

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
| `/internal/analytics/*` | Laravel endpoints that proxy and cache Python results                         | `GET /internal/analytics/attendance-trends`                                                                                                       |

The frontend never hardcodes URLs: import from `@/routes/...` or `@/actions/...` (Wayfinder).

## 8. Python analytics service (phase 6)

- **Location:** `analytics/` (FastAPI app, `pyproject.toml`), deployed as its own service on a private network.
- **Data access:** a dedicated MySQL user with `SELECT` only, granted on reporting views (`v_attendance_daily`, `v_marks`, `v_invoice_balances`), not on raw tables. Encrypted columns are never exposed to Python.
- **Auth between services:** Laravel signs each request with HMAC-SHA256 over `method + path + timestamp + body` using `ANALYTICS_SHARED_SECRET`. Python rejects requests with a bad signature or a clock skew over 60 seconds. Every request carries `school_id`, and Python filters by it.
- **Flow:** React → Laravel (authorizes `analytics.view`, scopes to the current school) → `AnalyticsClient` (HTTP client with timeout, retries and circuit breaking) → Python → JSON → cached in Laravel (`Cache::remember`, keyed by school and parameters) → Inertia deferred prop → React chart.
- **Endpoints:** `/attendance/trends`, `/attendance/at-risk`, `/performance/distribution`, `/performance/cohort-comparison`, `/finance/collections`, `/finance/forecast` (time-series forecast of fee collections), `/reports/{id}.pdf|xlsx` (queued exports).
- **Long jobs:** Laravel dispatches a queued job that calls Python, stores the file and notifies the user.

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
| 4     | Attendance, examinations, grading scales, results, assignments and homework                                               |
| 5     | Fees, invoices, payments, expenses, accounting ledger, payroll, HR and leave                                              |
| 6     | Python analytics service, reports and exports, forecasting                                                                |
| 7     | Library, transport, inventory, communication (announcements, events, messages, notifications), documents and certificates |
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
