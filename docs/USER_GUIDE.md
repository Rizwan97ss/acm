# School ERP — User Guide

This guide takes you from a fresh copy of the code to a school that runs day to day on the system. Read it in order the first time:

1. [Get it running](#1-get-it-running) — install, demo data, sign in
2. [Set up your school](#2-set-up-your-school-do-this-in-order) — the order to enter data in
3. [Daily use by role](#3-daily-use-by-role) — what each person does
4. [Administration and security](#4-administration-and-security)
5. [Mobile apps and the API](#5-mobile-apps-and-the-api)
6. [Logs and troubleshooting](#6-logs-and-troubleshooting)
7. [Going live](#7-going-live-checklist)
8. [Command cheat sheet](#8-command-cheat-sheet)

For how the code is built, see [ARCHITECTURE.md](ARCHITECTURE.md).

---

## How the system fits together

| Part | What it does | Do you need it? |
|---|---|---|
| **Main app** (Laravel + React) | Everything you see and use: people, academics, finance, HR, library, communication, admin | Always |
| **Queue worker** | Sends webhooks, notifications and emails in the background | Yes, or those never go out |
| **Analytics service** (Python, in `analytics/`) | Charts, forecasts and at-risk lists on the **Insights** page | Only for Insights; everything else works without it |

The app runs for **one institute**. A multi-institute version will be a separate project.

---

## 1. Get it running

### What you need

- PHP 8.4, Composer, Node.js and MySQL. [Laravel Herd](https://herd.laravel.com) provides PHP and serves the site.
- Python 3.11 or newer, only if you want the Insights page.

### First-time install

From the project folder:

```bash
composer run setup        # installs packages, creates .env, generates the key, migrates, builds the frontend
```

Then open `.env` and set your database (`DB_DATABASE=acm`, user and password), and run:

```bash
php artisan migrate --seed
```

`--seed` creates every permission and system role, plus **demo data** (only outside production): two schools, a student, a parent, a teacher, and one account per role.

### Demo accounts

All demo accounts use the password **`password`**.

| Email | Role | Good for trying |
|---|---|---|
| `admin@example.com` | Super Admin | Everything, including Administration |
| `school.admin@example.com` | School Admin | Running the school day to day |
| `principal@example.com` | Principal | Viewing everything, approving |
| `teacher@example.com` | Teacher | Attendance, marks, homework |
| `accountant@example.com` | Accountant | Fees, invoices, payments |
| `hr@example.com` | HR Manager | Staff, payroll, leave |
| `librarian@example.com` | Librarian | Books and loans |
| `reception@example.com` | Receptionist | Admissions enquiries |
| `student@example.com` | Student | The student portal |
| `parent@example.com` | Parent | The parent portal |

### Your own admin account

If you registered your own account instead, give it full access once:

```bash
php artisan rbac:grant-super-admin you@yourschool.com
```

### Start it every day

```bash
composer run dev          # web app + Vite (hot reload) + log viewer
```

Open the site Herd serves (usually **http://acm.test**) and sign in.

If webhooks or notifications aren't being sent, also run `php artisan queue:work` in a second terminal.

If a page change doesn't show, or you see *"Unable to locate file in Vite manifest"*, run `npm run build` or keep `composer run dev` running.

### Optional: the analytics service (Insights page)

1. Put the same long random secret (at least 32 characters) in **both** places:
   - `.env` of the main app: `ANALYTICS_SHARED_SECRET=...`
   - the environment of the Python service: `ANALYTICS_SHARED_SECRET=...`
2. Start the service:

```bash
cd analytics
python -m venv .venv
.venv\Scripts\activate             # Windows  (macOS/Linux: source .venv/bin/activate)
pip install -r requirements.txt
uvicorn app.main:create_app --factory --host 127.0.0.1 --port 8100
```

The app finds it at `ANALYTICS_URL` (default `http://127.0.0.1:8100`). If the service is down, Insights shows a notice and the rest of the app carries on.

---

## 2. Set up your school (do this in order)

Sign in as **Super Admin** or **School Admin**. Each step depends on the ones before it, so follow the order.

### Step 1 — School details and settings
**Administration → Settings**

| Group | What to set |
|---|---|
| Branding | App name, primary colour, default light/dark theme |
| Localization | Time zone, date and time format, currency, first day of the week |
| Academic | School week (which days), year start month, pass percentage, attendance mode and alert threshold |
| Library | Loan period, books per borrower, fine per day (in cents), whether to bill student fines |
| Notifications | Email and SMS switches |
| Security | Password rules, idle sign-out time, **require two-factor login** |

Each user can override some of these for themselves under **Settings → Preferences** (their avatar menu).

### Step 2 — Academic year and terms
**Academics → Academic years**

Create the year (for example *2026–27*), add its terms, and mark it **current**. Attendance, timetables, invoices and reports all use the current year.

### Step 3 — Classes and sections
**Academics → Classes & sections**

Create each class (Grade 1, Grade 2 …) with its sections (A, B …), rooms and class teachers.

### Step 4 — Subjects
**Academics → Subjects**

Create subjects, then attach them to classes with the number of periods per week.

### Step 5 — Bell schedule
**Academics → Bell schedule**

Enter the periods of the day (start and end times, and breaks).

### Step 6 — Grading scales
**Academics → Grading scales**

Define grade bands (A = 90–100 …) used on results and report cards. The pass percentage is in **Settings → Academic**.

### Step 7 — Staff and user accounts
- **People → Staff** — add teachers and other employees (needed for payroll, leave and the library).
- **Administration → Users** — create sign-in accounts and give each a role.
- **Administration → Roles & permissions** — adjust what each role can do, or create your own roles. See [section 4](#roles-and-permissions).

### Step 8 — Timetable
**Academics → Timetable**

Pick a section and place a subject and teacher in each period. Clashes (the same teacher or room twice at once) are blocked.

### Step 9 — Students and parents
Two ways to add students:

- **Admissions** (recommended for new intake): **People → Admissions** → record an application → *Approve* → *Enrol* into a section. This creates the student, links or creates the parent (siblings share a parent matched by phone or email), and enrols them.
- **Directly**: **People → Students → New student**.

Then on the student's or guardian's page, choose **Invite to portal**. They get an email with a link to set their password, and see only their own (or their children's) records. Each person needs an email address first.

### Step 10 — Fees
**Finance → Fee structure**

1. Create fee items for the year (tuition, lab fee …), for all classes or one class, with their frequency.
2. Give individual students a discount (concession) on the student's page if needed.
3. **Finance → Invoices → Generate** — pick the fees and classes to bill everyone at once.

You're set up. Everything after this is day-to-day work.

---

## 3. Daily use by role

### Teacher
- **Attendance** — pick your section and date, mark present, absent, late or excused, and save. Past days can only be corrected by supervisors.
- **Assignments** — set homework with a due date; see who has submitted.
- **Exams & results** — enter marks per paper and section. Results reach students and parents only once the exam is **published**.
- **Timetable** — opens on your own teaching schedule.

### Principal / School Admin
- **Overview** dashboard — today's attendance, fees collected, recent activity.
- **Exams** — create exams, generate papers, and **publish** results when marking is complete.
- **Admissions** — approve or reject applications.
- **Leave requests** and **Expenses** — approve or reject.
- **Announcements** and **Calendar** — publish news and events to everyone, staff, students or parents (optionally one class).

### Accountant
- **Invoices** — generate in bulk, open one to **record a payment** (cash, bank, card …), print the receipt, or **void** a wrong invoice.
- **Payments** — list and reverse mistaken payments (with a reason).
- **Expenses** — record spending; approvers review it.
- **Accounting** — trial balance and journal. Invoices, payments, expenses and payroll post themselves to the double-entry ledger automatically.

### HR Manager
- **Staff** — employee records.
- **Payroll** — set salaries, create a monthly run, review and finalise it. Staff see their payslips under **HR → My payslips**.
- **Leave requests** — approve or reject. Staff apply under **HR → My leave**.

### Librarian
- **Catalogue** — add books and the number of copies.
- **Loans** — issue a book by typing the student's admission number or the staff number; record the return. Late returns get a fine, and a student's fine is added to a fee invoice automatically (if *Bill student fines* is on).

### Receptionist
- **Admissions** — record enquiries and applications.
- **Messages** — reply to parents.

### Operations
- **Transport** — routes, stops and which students ride each route (seats are limited by capacity).
- **Inventory** — items and stock movements (in, out, adjustments).
- **Documents** — upload files (PDF, Office, images; up to 10 MB), stored privately and downloadable only by people allowed to see them.
- **Certificates** — draft → **issue** → print. Every issued certificate carries a verification link and code that anyone can check publicly; revoked certificates show as revoked.

### Students and parents (portal)
- **My progress** — attendance, published results and open homework for each child.
- **Timetable**, **Invoices** and receipts, **Announcements**, **Calendar**, **Messages** to the school, **Library** loans, **Transport**, **Certificates**.
- The bell icon in the header shows new notifications.

### Insights and reports
- **Insights** — attendance trends, students at risk, performance, and a fee-collection forecast (needs the analytics service).
- **Reports & exports** — download CSV reports.

---

## 4. Administration and security

### Roles and permissions
**Administration → Roles & permissions**

- Permissions are *module.action*, for example `students.view` or `invoices.approve`.
- Built-in roles: Super Admin, School Admin, Principal, Teacher, Student, Parent, Accountant, HR Manager, Librarian, Receptionist.
- You can create your own roles, optionally based on a built-in one. You can never grant permissions you don't have yourself.
- After changing `config/rbac.php` in code, run `php artisan rbac:sync`.

### Securing accounts
- Each user can turn on **two-factor login** and **passkeys** under **Settings → Security**.
- **Settings → Security → Require two-factor** makes it mandatory for everyone.
- Accounts can be **deactivated** under **Administration → Users**; their sessions and API tokens end straight away.
- **Lost authenticator:** an administrator opens the user under **Administration → Users** and chooses **Turn off two-factor** (never for their own account). It is recorded in the audit log.
- Changing or resetting a password signs the person out on every other device and cancels their API tokens.
- There is no public sign-up: accounts are created by administrators.
- Forgot-password, password reset and password confirmation are rate-limited, like sign-in.
- Pages are sent with a **Content-Security-Policy** (off in local development). Set `CSP_REPORT_ONLY=true` to only report problems, and `CSP_IMG_SOURCES` to allow an external image host such as an S3 bucket.
- People are signed out automatically after the idle time set in Security settings.
- Sensitive details (phone numbers, national IDs, medical notes, addresses, messages) are encrypted in the database and masked in logs.

### Audit log and activity log
| Page | What it shows |
|---|---|
| **Administration → Audit log** | What changed: every record created, edited or deleted with old and new values, sign-ins, failed sign-ins, role and settings changes |
| **Administration → Activity log** | Every page visit and action: who, which page or button, which records, result and time taken. Submitted values are never recorded, only field names |

Both need the **Audit log** permission. School admins see only their own school.

### Webhooks (sending events to other systems)
**Administration → Webhooks**

1. Add an `https://` URL on a public address and choose events: *student created*, *payment recorded*, *announcement published*, *certificate issued*.
2. Copy the **signing secret** shown once. The receiving system uses it to check the `X-Webhook-Signature` header.
3. Use **Send test** and watch the delivery log.

Failed deliveries retry after 30 s, 2 min, 10 min and 1 h. An endpoint is switched off after 20 failures in a row. Webhooks need the **queue worker** running.

---

## 5. Mobile apps and the API

Base address: `https://<your-site>/api/v1`. Send `Accept: application/json` and `Authorization: Bearer <token>`.

### Getting a token
- **For a mobile app:** `POST /api/v1/auth/token` with `email`, `password`, `device_name` (and `code` if the account uses two-factor login). It returns a token valid for 30 days. `DELETE /api/v1/auth/token` signs that device out.
- **For a script or integration:** **Settings → API tokens** → choose a name, scopes and expiry → copy the token (shown once). Revoke it there at any time.

### What you can read
| Endpoint | Scope needed |
|---|---|
| `GET me` | any |
| `GET students`, `GET students/{id}` | Students |
| `GET timetable`, `GET students/{id}/attendance` | Timetable and attendance |
| `GET invoices`, `GET invoices/{id}` | Invoices |
| `GET announcements`, `GET events`, `GET notifications`, `POST notifications/{id}/read`, `POST notifications/read-all` | Announcements, events and notifications |

A token can never do more than its owner: role permissions still apply, and students and parents only ever see their own records. Requests are limited to 120 per minute per user.

Quick test:

```bash
curl -H "Accept: application/json" -H "Authorization: Bearer <token>" https://<your-site>/api/v1/me
```

---

## 6. Logs and troubleshooting

### Where the logs are (`storage/logs/`)
| File | Contents |
|---|---|
| `activity-YYYY-MM-DD.log` | Every request (also on the Activity log page). Kept 30 days (`LOG_ACTIVITY_DAYS`) |
| `audit-YYYY-MM-DD.log` | Every audit entry (also on the Audit log page and in the database). Kept 90 days (`LOG_AUDIT_DAYS`) |
| `laravel.log` | Errors, webhook failures, analytics problems, and — locally — the emails that would have been sent |

### Common problems
| Problem | Fix |
|---|---|
| A menu item is missing | The user's role lacks that permission. Check **Roles & permissions**. Your own account: run `rbac:grant-super-admin` |
| "Choose a school to work in first" | Your account isn't linked to a school. Give it a school under **Administration → Users** |
| Portal invite or password-reset email never arrives | Locally `MAIL_MAILER=log`, so emails are written to `storage/logs/laravel.log`. In production, configure a real mail service |
| Webhooks or notifications never go out | The queue worker isn't running: `php artisan queue:work` |
| Insights shows "analytics unavailable" | Start the Python service and make sure both sides use the same `ANALYTICS_SHARED_SECRET` |
| Can't mark attendance / generate invoices | There's no **current** academic year, or the student isn't enrolled in a section |
| Page changes don't appear | Run `npm run build` or keep `composer run dev` running |
| "This action is unauthorized" (403) | Expected: the role doesn't allow it. Ask an admin for the permission |

---

## 7. Going live checklist

- [ ] `.env`: `APP_ENV=production`, `APP_DEBUG=false`, real `APP_URL` on **https**
- [ ] `.env`: `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`
- [ ] Back up `APP_KEY` safely. **If it's lost, encrypted data (phones, IDs, medical notes, messages) cannot be recovered**
- [ ] A real mail service (`MAIL_MAILER`, host, user, password, from address)
- [ ] `composer install --no-dev --optimize-autoloader` and `npm ci && npm run build`
- [ ] `php artisan migrate --force` and `php artisan rbac:sync`
- [ ] `php artisan rbac:grant-super-admin <your email>` (demo data is never created in production)
- [ ] `php artisan config:cache`, `route:cache`, `view:cache`
- [ ] Keep a queue worker running all the time (Supervisor or your host's worker feature): `php artisan queue:work --tries=3`
- [ ] Host the analytics service privately (not public), with the shared secret set
- [ ] Daily database backups, and a copy of `storage/app` (uploaded documents and logos)
- [ ] Decide whether to keep public registration open (most schools turn it off)

---

## 8. Command cheat sheet

| Command | What it does |
|---|---|
| `composer run setup` | First-time install |
| `composer run dev` | Run everything locally |
| `php artisan migrate --seed` | Create tables, permissions, roles and demo data |
| `php artisan rbac:sync` | Re-sync permissions and system roles after changing `config/rbac.php` |
| `php artisan rbac:grant-super-admin <email>` | Give an account full access |
| `php artisan queue:work` | Process webhooks, notifications and emails |
| `php artisan test --compact` | Run the test suite |
| `npm run build` | Build the frontend |
| `uvicorn app.main:create_app --factory --port 8100` (in `analytics/`) | Start the analytics service |
