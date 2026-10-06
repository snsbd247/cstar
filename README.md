# C-STAR Management System

**Center for Speech Therapy & Autism Rehabilitation (C-STAR), Bangladesh**
Public Website + Center Management System + Parent Portal

> এই README-ই প্রকল্পের **মূল পরিকল্পনা ও অগ্রগতির document** (বাংলা)। প্রতিটি কাজ শেষ হলে নিচের অগ্রগতি তালিকা হালনাগাদ করা হয়।
> সংস্করণ: Plan v1.2 · শেষ হালনাগাদ: ০৬ অক্টোবর ২০২৬ · সহযোগী document: [docs/C-STAR-Accounts-BN.md](docs/C-STAR-Accounts-BN.md) (সম্পূর্ণ Accounts Module)

## সূচিপত্র

- [প্রকল্পের কাঠামো](#প্রকল্পের-কাঠামো)
- [Local-এ চালানোর নিয়ম](#local-এ-চালানোর-নিয়ম)
- [কাজের অগ্রগতি](#-কাজের-অগ্রগতি-progress-tracker)
- [মূল ভিত্তি — তিনটি নীতি](#০-মূল-ভিত্তি--তিনটি-নীতি)
- [System Architecture](#১-system-architecture) · [Sitemap](#২-sitemap) · [User Flow](#৩-প্রধান-user-flow)
- [Database](#৪৬-database-design-erd-table-relationship) · [Role ও Permission](#৭-role-ও-permission-matrix) · [API](#৮-rest-api-architecture)
- [Folder Structure](#৯-react-folder-structure) · [UI পরিকল্পনা](#১১১৫-ui-পরিকল্পনা) · [Workflow](#১৬২০-workflow)
- [Security](#২১-security-architecture) · [cPanel Deployment](#২২-cpanel-deployment-architecture) · [Roadmap](#২৩-development-roadmap) · [MVP](#২৪-mvp-scope)
- [Missing Business Logic](#২৬-আমার-চিহ্নিত-missing-business-logic) · [চূড়ান্ত সিদ্ধান্ত](#২৭-চূড়ান্ত-সিদ্ধান্ত--০৬-অক্টোবর-২০২৬--সব-সুপারিশ-অনুমোদিত) · [Accounts Module](#২৮-সম্পূর্ণ-accounts-module-সারসংক্ষেপ)

---

## প্রকল্পের কাঠামো

| Folder | কী আছে |
|---|---|
| `backend/` | Laravel 13 (PHP 8.3): REST API `/api/v1`, Public Website (Blade), Sanctum login, role ও permission |
| `frontend/` | React 19 + TypeScript + Vite + Tailwind: Admin (`/app`), Trainer (`/trainer`), Therapist (`/therapist`), Parent Portal (`/portal`) |
| `docs/` | Accounts Module-এর বিস্তারিত design |

## Local-এ চালানোর নিয়ম

**যা লাগবে:** XAMPP (MariaDB চালু), PHP 8.3, Composer, Node.js 20 বা তার বেশি।

```bash
# ১. Backend (প্রথমবার)
cd backend
composer install
cp .env.example .env             # XAMPP-এর MySQL database "cstar"-এর জন্য আগেই সাজানো
php artisan key:generate
php artisan migrate --seed       # role, permission, branch, service, demo user ও demo শিশু
php artisan serve                # http://127.0.0.1:8000

# ২. Frontend (আরেকটি terminal-এ)
cd frontend
npm install
npm run dev                      # http://localhost:5173  (/api ও /sanctum Laravel-এ পাঠায়)
```

এরপর browser-এ খুলুন: **http://localhost:5173/login**

### Demo login (শুধু local-এর জন্য, সবার password `Cstar@1234`)

| Role | Login | কোথায় যাবে |
|---|---|---|
| Super Admin | admin@cstar.test | /app |
| Branch Admin | branchadmin@cstar.test | /app |
| Receptionist | reception@cstar.test | /app |
| Accountant | accounts@cstar.test | /app |
| Trainer (Md. Hasan) | trainer@cstar.test | /trainer |
| Therapist (Imran Hossain) | therapist@cstar.test | /therapist |
| Parent (Ayan-এর মা) | 01700000007 | /portal |

Demo data-য় plan-এর উদাহরণটাই আছে: **Ayan** (Training + Speech + OT), **Sara** (শুধু Speech Therapy), **Rafi** (শুধু Training)।

### Test

```bash
cd backend && php artisan test        # MySQL database "cstar_test" ব্যবহার করে
cd frontend && npx tsc -b && npm run lint
```

### Production build (server-এ Node.js লাগবে না)

`frontend/`-এ `npm run build` চালালে React app তৈরি হয়ে `backend/public/spa/`-তে যায়। Laravel নিজেই `/app`, `/trainer`, `/therapist`, `/portal`, `/login` path-এ এটা দেখায়। cPanel-এ `backend/` folder (সাথে `vendor/` ও `public/spa/`) upload করতে হবে। Server-এ **PHP 8.3 বা তার বেশি** লাগবে। প্রথম আসল admin তৈরি করতে: `php artisan cstar:create-admin`। `APP_ENV=production` হলে demo user কখনো তৈরি হয় না।

---

## ✅ কাজের অগ্রগতি (Progress Tracker)

> চিহ্ন: ✅ শেষ · 🔄 চলছে · ⬜ বাকি — **শেষ হালনাগাদ: ০৬ অক্টোবর ২০২৬ (Sprint ৬ শেষ)**
> প্রতিটি কাজ শেষ হলে এখানে চিহ্ন বদলানো হবে।

### Phase 1 — পরিকল্পনা ও Requirement
| অবস্থা | কাজ | তারিখ / মন্তব্য |
|---|---|---|
| ✅ | Requirement বিশ্লেষণ ও সম্পূর্ণ plan (২৫টি বিষয়) | ০৬ অক্টো ২০২৬ — এই document |
| ✅ | তিনটি মূল নীতি (Patient ≠ Student, Trainer ≠ Therapist, একাধিক Enrollment) architecture-এ বসানো | ০৬ অক্টো ২০২৬ — §০, §৪–৬ |
| ✅ | Missing business logic চিহ্নিত (২০টি) | ০৬ অক্টো ২০২৬ — §২৬ |
| ✅ | সিদ্ধান্ত D1–D8 চূড়ান্ত (সব সুপারিশ অনুমোদিত) | ০৬ অক্টো ২০২৬ — §২৭ |
| ✅ | Local development environment যাচাই (PHP 8.3, Composer, Node 24, MariaDB, Git) | ০৬ অক্টো ২০২৬ |
| ✅ | সম্পূর্ণ Accounts Module design | ০৬ অক্টো ২০২৬ — §২৮ ও [C-STAR-Accounts-BN.md](docs/C-STAR-Accounts-BN.md) |
| ✅ | Accounts সিদ্ধান্ত A1–A9 চূড়ান্ত (সব সুপারিশ অনুমোদিত) | ০৬ অক্টো ২০২৬ — §২৮ |
| ⬜ | Therapist-দের বর্তমান বেতনের পদ্ধতি জানা (A3-এর default ঠিক করতে) | আপনার কাছ থেকে |
| ⬜ | cPanel hosting-এর MySQL/MariaDB version জানা, এবং **PHP 8.3 বা তার বেশি** আছে কিনা নিশ্চিত করা (Laravel 13-এর প্রয়োজন) | আপনার কাছ থেকে |
| ⬜ | Center-এর তথ্য সংগ্রহ (service ও মূল্য, branch, staff, বর্তমান কাগজের form) | আপনার কাছ থেকে |
| ⬜ | Wireframe ও UI/UX design | |

### Development (Roadmap অনুযায়ী — বিস্তারিত §২৩)
| অবস্থা | Sprint | কাজ |
|---|---|---|
| ✅ | ৩ | ERD চূড়ান্ত (§৪–৬); ভিত্তি migration ও seeder: users, branches, rooms, holidays, settings, id_sequences, audit_logs, role/permission — ০৬ অক্টো ২০২৬। *Patient, enrollment ইত্যাদি module-এর table নিজ নিজ sprint-এ তৈরি হবে, যাতে প্রতিটি table তার কাজের সাথে test হয়।* |
| ✅ | ৪ | Laravel 13 + React 19 setup; Sanctum login (email/মোবাইল); ৭টি role ও ৫৯টি permission; branch অনুযায়ী access; Branch, User, Role management (API + UI); audit log; role অনুযায়ী ৪টি app (Admin/Trainer/Therapist/Parent); ৩৫টি backend test পাস — ০৬ অক্টো ২০২৬ |
| ⬜ | ৫ | Public Website (Blade) + CMS (basic) + Online Appointment Request |
| ✅ | ৬ | Patient + Guardian + Documents + **Enrollment System** — ০৬ অক্টো ২০২৬: patient registration (স্বয়ংক্রিয় ID `CSTAR-2026-00001`, duplicate সতর্কবার্তা, ভাই-বোনের জন্য একই guardian, consent), clinical তথ্য আলাদা ও সুরক্ষিত, private document ও ছবি, parent portal login তৈরি, enrollment (Training: class + trainer, Therapy: service + therapist), hold/resume/complete/discontinue/transfer ও ইতিহাস, patient timeline, global search; trainer/therapist শুধু নিজের শিশুদের দেখেন; ৬৯টি test পাস। *Trainer, therapist, class ও service-এর মূল table এখানেই তৈরি; এদের management page Sprint ৭–৮-এ।* |
| ⬜ | ৭ | Class + Trainer + Training Attendance + Training Session/Record + ITP |
| ⬜ | ৮ | Therapist + Schedule + Appointment + Therapy Session |
| ⬜ | ৯ | Assessment + Plans/Goals + Timeline + PDF |
| ⬜ | ১০ | Package + Invoice + Payment + Due + **Chart of Accounts ও Billing auto-posting** |
| ⬜ | ১১ | **Accounts A** — Expense, Voucher, Cash Closing, Ledger, Trial Balance, P&L, Balance Sheet |
| ⬜ | ১২ | **Accounts B** — Employee, Payroll, Therapist Payout, Advance, Payslip |
| ⬜ | ১৩ | Parent Portal |
| ⬜ | ১৪ | Reports + Dashboard charts + Notifications |
| ⬜ | ১৫ | **Accounts C** — Vendor/Payables, Bank Reconciliation, Fixed Assets, Budget, Year-end |
| ⬜ | ১৬ | Security hardening, Testing, UAT |
| ⬜ | ১৭ | cPanel deployment, staff training, go-live |

---

## ০. মূল ভিত্তি — তিনটি নীতি

এই পুরো system তিনটি নীতির উপর দাঁড়িয়ে আছে। Database, API, UI, permission — সব জায়গায় এগুলো মেনে চলা হবে।

### নীতি ১: Patient ≠ Student

- সব শিশু/ক্লায়েন্ট প্রথমে একটি **Patient** profile। এটাই একমাত্র "মানুষ"-এর table।
- **"Student" আলাদা কোনো table নয়।** একজন patient তখনই "Student", যখন তার একটি **active Training Enrollment** আছে।
- Training enrollment শেষ হলে সে আর student নয়, কিন্তু patient থেকেই যায় (তার সব history সহ)।
- UI-তে "Regular Student / Therapy Patient / Student + Therapy" — এই label **enrollment দেখে স্বয়ংক্রিয়ভাবে হিসাব হবে**, কেউ হাতে বসাবে না।

| Active enrollment | UI-তে দেখাবে |
|---|---|
| শুধু Training | Regular Student |
| শুধু Therapy | Therapy Patient |
| Training + Therapy | Student + Therapy |
| কোনোটাই নেই | Registered (Enrollment নেই) |

### নীতি ২: Trainer ≠ Therapist

| | Trainer | Therapist |
|---|---|---|
| কাজ | Regular student-কে physical/functional training (motor, daily living, social, learning) | Speech, OT, ABA, OPT, Special Education ইত্যাদি therapy session |
| কোথায় যুক্ত | শুধু **Training Enrollment** ও Class-এ | শুধু **Therapy Enrollment** ও Appointment-এ |
| যা লেখে | Training Record, Attendance | Therapy Session Note, Assessment, Home Program |
| Database | `trainers` table | `therapists` table |
| Login role | Trainer | Therapist |
| Dashboard | Today's Class / Students | Today's Appointments |

Trainer কখনো therapy enrollment-এ বসানো যাবে না, therapist কখনো class-এ — এটা database structure দিয়েই আটকানো থাকবে (field-গুলো আলাদা table-এ)।

### নীতি ৩: একজন Patient-এর একাধিক Enrollment

```
PATIENT (একজন শিশু — একটি profile)
   │
   └── ENROLLMENTS (০ বা একাধিক)
         ├── Training Enrollment  → Class + Trainer  → Attendance + Training Record
         └── Therapy Enrollment   → Service + Therapist → Appointment + Therapy Session
```

উদাহরণ:

| Patient | Enrollment ১ | Enrollment ২ | Enrollment ৩ | UI label |
|---|---|---|---|---|
| Ayan | Regular Training (Functional Development A, Trainer: Hasan) | Speech Therapy (Therapist: Imran) | Occupational Therapy (Therapist: Farhana) | Student + Therapy |
| Sara | Speech Therapy | — | — | Therapy Patient |
| Rafi | Regular Training | — | — | Regular Student |

আরও দুটি distinction (আগের requirement থেকে) একইভাবে মানা হবে:

- **Training Session ≠ Therapy Session** — আলাদা table, আলাদা form, আলাদা লেখক।
- **Student Attendance ≠ Therapy Appointment** — attendance প্রতিদিনের class উপস্থিতি; appointment হলো therapist-এর সাথে booked slot। কখনো এক table-এ মিশবে না।

---

## ১. System Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│                         ব্যবহারকারী                              │
│  Website Visitor · Admin · Receptionist · Trainer · Therapist   │
│  Accountant · Parent  (Mobile / Tablet / Desktop)                │
└───────────────┬──────────────────────────────────────────────────┘
                │ HTTPS
┌───────────────▼──────────────────────────────────────────────────┐
│  cPanel Shared Hosting (Apache + PHP 8.3 + MySQL)  — Node.js নেই  │
│                                                                  │
│  ┌──────────────────────┐   ┌─────────────────────────────────┐  │
│  │ React Build (static) │   │ Laravel                         │  │
│  │ index.html + assets  │──►│ • REST API /api/v1              │  │
│  │ Admin + Trainer +    │   │ • Public Website (Blade, SEO)   │  │
│  │ Therapist + Parent   │   │ Sanctum · RBAC · Policies       │  │
│  │ (/app /trainer       │   │ Services · Jobs · PDF (mPDF)    │  │
│  │  /therapist /portal) │   │ Notifications · Audit Log       │  │
│  └──────────────────────┘   └──────────────┬──────────────────┘  │
│                                            │                     │
│                               ┌────────────▼─────────┐           │
│                               │ MySQL Database       │           │
│                               └──────────────────────┘           │
│  storage/app/private  → রোগীর document (public-এ নয়)            │
│  cron (প্রতি মিনিট) → schedule:run → queue, reminder, invoice    │
└──────────────────────────────────────────────────────────────────┘
```

**Layer ভাগ:**

| Layer | দায়িত্ব |
|---|---|
| React SPA | UI, routing, form, role অনুযায়ী আলাদা layout (Admin / Trainer / Therapist / Parent / Website) |
| Laravel Controller | শুধু request নেয়, validation (FormRequest), authorization (Policy), Service call, Resource return |
| Service layer | Business logic — যেমন `EnrollmentService`, `AppointmentService`, `BillingService`, `PackageService` |
| Model + Observer | Data, relationship, timeline event লেখা, audit log |
| Jobs / Scheduler | Reminder, monthly training fee invoice, package expiry, recurring appointment তৈরি |

**Authentication:** একই domain-এ `/api` থাকায় Sanctum **SPA cookie authentication** — CORS ঝামেলা নেই, token browser-এ খোলা থাকে না। ভবিষ্যৎ Mobile App-এর জন্য Sanctum token auth পরে চালু করা যাবে।

---

## ২. Sitemap

### Public Website
```
/                       Home
/about                  About C-STAR
/services               Services (দুটি আলাদা ভাগ: Therapy Services | Training Programs)
/services/{slug}        Service Details
/therapists             Therapists
/therapists/{slug}      Therapist Details
/trainers               Trainers
/branches               Branches
/gallery                Gallery
/faq                    FAQ
/contact                Contact
/appointment            Online Appointment Request
/notices                Notices
/login                  Staff / Parent Login
```

### Admin / Staff Panel (`/app`)
```
Dashboard
Patients ─ List · Register · Profile (Overview, Enrollments, Timeline, Training, Therapy,
           Assessments, Plans & Goals, Home Programs, Documents, Billing, Guardians)
Students / Training ─ Active Students · Attendance · Training Records · Individual Training Plans
Classes ─ List · Create · Class Detail (Roster, Schedule, Attendance)
Trainers ─ List · Profile
Therapists ─ List · Profile · Schedule & Leave
Appointments ─ Calendar · List · Online Requests
Therapy Sessions
Training Sessions
Assessments
Packages ─ Package setup · Patient Packages
Invoices · Payments · Due List
Accounts ─ Dashboard · Expenses · Vouchers · Vendors & Bills · Payroll · Cash Closing ·
           Bank Reconciliation · Fixed Assets · Budget · Financial Reports · Chart of Accounts
Branches · Rooms · Holidays
Reports
Website CMS ─ Pages · Services · Therapists/Trainers (public profile) · Gallery · Testimonials · FAQ · Notices · Contact Messages
Users & Roles
Notifications
Settings
```

### Trainer (`/trainer`) — mobile-first
```
Today · My Classes · My Students · Attendance · Training Records · Plans/Goals · Profile
```

### Therapist (`/therapist`) — mobile/tablet-first
```
Today · Appointments · My Patients · Session Notes · Assessments · Plans/Goals · Home Programs · Schedule · Profile
```

### Parent Portal (`/portal`) — mobile-first, bottom navigation
```
Home · My Children → (Child) → Appointments · Training Schedule · Attendance ·
Therapy Sessions · Progress · Reports · Home Program · Billing · Notifications · Profile
```

---

## ৩. প্রধান User Flow

### ৩.১ সম্পূর্ণ Patient Journey
```
Website request / Walk-in / Phone
   ↓
Patient Registration  (+ Guardian, + Consent)
   ↓
Assessment  →  Recommendation (কোন service লাগবে)
   ↓
Enrollment নির্বাচন (একাধিক হতে পারে)
   ├── Regular Training → Class → Trainer → Attendance → Training Record → Training Progress
   └── Therapy → Service → Therapist → (Package) → Appointment → Therapy Session → Therapy Progress
   ↓
Billing (Invoice → Payment → Due)
   ↓
Parent Portal (child-এর enrollment অনুযায়ী dynamic)
   ↓
Progress Report / Review → Continue / Change / Discharge
```

### ৩.২ Receptionist (দ্রুত workflow)
`Search (নাম/ID/ফোন) → Patient Profile → [+ New Enrollment] → [+ Appointment] → [+ Payment]`
Profile page-এর উপরেই এই চারটি quick action button থাকবে — কোনো menu ঘুরতে হবে না।

### ৩.৩ Trainer
`Login → Today (আজকের class) → Attendance এক tap-এ → Student → Training Record → Save`

### ৩.৪ Therapist
`Login → Today's Appointments → Patient → Session Note (আগের note-এর next plan আগে থেকে দেখাবে) → Save/Finalize`

### ৩.৫ Parent
`Login (ফোন নম্বর + password) → Child নির্বাচন → Appointment / Progress / Report / Due`

---

## ৪–৬. Database Design (ERD, Table, Relationship)

### ৪.১ মূল ERD (সংক্ষিপ্ত)

```mermaid
erDiagram
    BRANCHES ||--o{ PATIENTS : "home branch"
    PATIENTS ||--o{ PATIENT_GUARDIANS : has
    GUARDIANS ||--o{ PATIENT_GUARDIANS : has
    GUARDIANS |o--|| USERS : "portal login"
    PATIENTS ||--o| PATIENT_CLINICAL_PROFILES : has
    PATIENTS ||--o{ ENROLLMENTS : has

    ENROLLMENTS ||--o| TRAINING_ENROLLMENTS : "type=training"
    ENROLLMENTS ||--o| THERAPY_ENROLLMENTS : "type=therapy"

    TRAINING_GROUPS ||--o{ TRAINING_ENROLLMENTS : "class roster"
    TRAINERS ||--o{ TRAINING_ENROLLMENTS : assigned
    TRAINERS ||--o{ TRAINING_GROUPS : "lead trainer"
    TRAINING_ENROLLMENTS ||--o{ TRAINING_ATTENDANCE : daily
    TRAINING_GROUPS ||--o{ TRAINING_SESSIONS : sittings
    TRAINING_SESSIONS ||--o{ TRAINING_RECORDS : "per student"
    TRAINING_ENROLLMENTS ||--o{ TRAINING_RECORDS : has

    SERVICES ||--o{ THERAPY_ENROLLMENTS : service
    THERAPISTS ||--o{ THERAPY_ENROLLMENTS : assigned
    THERAPY_ENROLLMENTS ||--o{ APPOINTMENTS : booked
    APPOINTMENTS ||--o| THERAPY_SESSIONS : "results in"
    THERAPY_ENROLLMENTS ||--o{ THERAPY_SESSIONS : has

    PATIENTS ||--o{ ASSESSMENTS : has
    ENROLLMENTS ||--o{ INDIVIDUAL_PLANS : has
    INDIVIDUAL_PLANS ||--o{ PLAN_GOALS : has
    PLAN_GOALS ||--o{ GOAL_PROGRESS_ENTRIES : tracked

    PACKAGES ||--o{ PATIENT_PACKAGES : sold
    PATIENT_PACKAGES ||--o{ PACKAGE_USAGES : consumed
    PATIENTS ||--o{ INVOICES : billed
    INVOICES ||--o{ INVOICE_ITEMS : has
    PAYMENTS ||--o{ PAYMENT_ALLOCATIONS : split
    INVOICES ||--o{ PAYMENT_ALLOCATIONS : receives
    PATIENTS ||--o{ TIMELINE_EVENTS : history
```

### ৪.২ Requirement-এর table list থেকে যা পরিবর্তন করা হয়েছে (এবং কেন)

| আপনার প্রস্তাব | আমার প্রস্তাব | কারণ |
|---|---|---|
| `classes` | `training_groups` (UI-তে "Class") | PHP-তে `class` reserved word — `Class` নামে Model বানানো যায় না |
| `class_students` | বাদ — `training_enrollments.training_group_id` | একই তথ্য দুই জায়গায় রাখলে mismatch হয়। Class roster = ওই class-এর active training enrollment |
| `therapy_types` | বাদ — `services.category = therapy` | Website, booking, enrollment, package, invoice — সব এক `services` table ব্যবহার করবে |
| `training_programs` | `services.category = training` | একই কারণ |
| `roles, permissions, role_user` | `spatie/laravel-permission` package-এর table | পরীক্ষিত package, shared hosting-এ চলে |
| `progress_records` | `goal_progress_entries` + `progress_reports` + `timeline_events` | দৈনিক progress, আনুষ্ঠানিক report ও timeline আলাদা কাজ |
| `training_goals` | `individual_plans` + `plan_goals` (enrollment-এর অধীনে) | Training ও Therapy দুই ধরনের plan-ই একই structure, কিন্তু enrollment দিয়ে আলাদা থাকে |
| (ছিল না) | `appointment_requests` | Website visitor এখনো patient নয় — আগে request, reception যাচাই করে patient + appointment বানাবে |
| (ছিল না) | `patient_clinical_profiles` | Diagnosis/medical history আলাদা table-এ রাখলে access control সহজ ও নিরাপদ |

### ৫. Table Structure (domain অনুযায়ী)

> সব table-এ `id`, `created_at`, `updated_at`। গুরুত্বপূর্ণ table-এ `deleted_at` (soft delete), `created_by`। টাকা `DECIMAL(12,2)`, timezone `Asia/Dhaka`।

#### (ক) User, Role, Branch, System
| Table | মূল field |
|---|---|
| `users` | name, email (nullable, unique), phone (unique), password, user_type (staff/parent), status, last_login_at, must_change_password |
| spatie tables | `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` |
| `branch_user` | user_id, branch_id, is_primary — staff কোন branch দেখতে পারবে |
| `branches` | code (DHK/CTG), name, slug, address, phone, email, map_url, opening_hours (json), is_active, show_on_website |
| `rooms` | branch_id, name, type (class/therapy/assessment), capacity |
| `holidays` | branch_id (null = সব branch), date, title, type (weekly/public/center) |
| `settings` | group, key, value |
| `id_sequences` | key (patient/invoice/receipt), year, last_value — ID generation (row lock দিয়ে) |
| `audit_logs` | user_id, action (create/update/delete/view/export/login), auditable_type/id, old_values, new_values, ip, user_agent |

#### (খ) Staff
| Table | মূল field |
|---|---|
| `trainers` | user_id, employee_code, name, slug, photo, phone, email, qualification, experience_years, bio, branch_id, joining_date, status, show_on_website |
| `therapists` | user_id, employee_code, name, slug, designation, therapist_type (slt/ot/aba/opt/special_educator/other), photo, qualification, experience_years, bio, primary_branch_id, status, show_on_website |
| `specializations` | name, applies_to (trainer/therapist) |
| `trainer_specialization`, `therapist_specialization` | pivot |
| `therapist_services` | therapist_id, service_id — কোন therapist কোন service দেন (booking-এ filter) |
| `therapist_schedules` | therapist_id, branch_id, weekday, start_time, end_time, slot_minutes, room_id, effective_from/to |
| `therapist_leaves` | therapist_id, start_at, end_at, reason, status |

#### (গ) Service ও Lookup
| Table | মূল field |
|---|---|
| `services` | category (therapy/training/assessment/consultation), name, name_bn, slug, short_description, description, icon, image, default_duration_min, default_price, is_bookable_online, show_on_website, status |
| `branch_services` | branch_id, service_id, price, is_available — branch অনুযায়ী মূল্য |
| `activity_types` | name, domain (fine motor/gross motor/daily living/communication/social/cognitive/physical/functional), applies_to (training/therapy/both) |
| `assessment_types` | name, service_id, template (json) |
| `diagnoses` | name (ASD, ADHD, CP, Down Syndrome, Speech Delay, ID…) |

#### (ঘ) Patient ও Guardian
| Table | মূল field |
|---|---|
| `patients` | patient_code (**CSTAR-2026-00001**), name, name_bn, photo, date_of_birth (বয়স হিসাব হবে, store হবে না), gender, father_name, mother_name, phone, email, address, emergency_contact_name/phone/relation, referral_source, registration_date, home_branch_id, status (active/on_hold/discharged/inactive), notes, photo_consent |
| `patient_clinical_profiles` (1:1) | diagnosis_notes, medical_history, developmental_history, previous_therapy, medications, allergies, school_info |
| `patient_diagnoses` | patient_id, diagnosis_id, diagnosed_by, diagnosed_on, notes |
| `guardians` | user_id (portal login), name, phone, alt_phone, email, occupation, nid, address |
| `patient_guardians` | patient_id, guardian_id, relationship, is_primary, is_emergency_contact, can_access_portal |
| `patient_documents` | patient_id, category, title, path (**private disk**), mime, size, uploaded_by, visible_to_parent |
| `consents` | patient_id, guardian_id, type (treatment/photo_media/data_sharing), granted, signed_at, document_id |

#### (ঙ) Enrollment — system-এর হৃদয়
| Table | মূল field |
|---|---|
| `enrollments` (base) | enrollment_code, patient_id, branch_id, **type (training/therapy)**, status (pending/active/on_hold/completed/discontinued), start_date, end_date, end_reason, source_assessment_id, notes |
| `training_enrollments` (1:1) | enrollment_id (PK), **training_group_id NOT NULL**, **trainer_id NOT NULL**, monthly_fee, notes |
| `therapy_enrollments` (1:1) | enrollment_id (PK), **service_id NOT NULL**, **therapist_id NOT NULL**, sessions_per_week, session_duration_min, billing_mode (package/per_session/monthly) |
| `therapy_enrollment_slots` | therapy_enrollment_id, weekday, start_time, duration_min, room_id — নিয়মিত সাপ্তাহিক slot (যেমন রবি ও মঙ্গল ৪টা) |
| `enrollment_assignments` | enrollment_id, staff_role (trainer/therapist), staff_id, training_group_id, from_date, to_date, reason — trainer/therapist/class বদলের ইতিহাস |

> **কেন এই design:** "Regular Training-এ Class ও Trainer required, Therapy-তে Service ও Therapist required" — এই নিয়ম database নিজেই `NOT NULL` দিয়ে নিশ্চিত করে। ভবিষ্যতে নতুন enrollment type (যেমন Day Care, Online Program) এলে শুধু একটি নতুন extension table যোগ করলেই হবে।

#### (চ) Training Module
| Table | মূল field |
|---|---|
| `training_groups` ("Class") | code, name, branch_id, lead_trainer_id, room_id, start_date, end_date, max_students, status, notes |
| `training_group_trainers` | training_group_id, trainer_id, role (lead/assistant/substitute), from/to |
| `training_group_schedules` | training_group_id, weekday, start_time, end_time |
| `training_group_services` | কোন training program এই class-এ |
| `training_attendance` | training_enrollment_id, patient_id, training_group_id, date, status (present/absent/late/leave/holiday), arrival_time, remarks, marked_by — **unique(training_enrollment_id, date)** |
| `training_sessions` | training_group_id, trainer_id, branch_id, date, start_time, end_time, theme, status (planned/conducted/cancelled) — একটি class-এর একদিনের বসা |
| `training_records` | training_session_id, training_enrollment_id, patient_id, trainer_id, date, start/end, duration, goals, observation, performance (১–৫), progress, challenges, trainer_notes (internal), parent_note, next_plan, status (draft/final) |
| `training_record_activities` | training_record_id, activity_type_id, rating, note |

#### (ছ) Therapy Module
| Table | মূল field |
|---|---|
| `appointment_requests` | parent_name, child_name, phone, email, child_age, branch_id, service_id, preferred_therapist_id, preferred_date, preferred_time, message, status (new/contacted/converted/rejected/spam), handled_by, appointment_id |
| `appointments` | appointment_code, patient_id, therapy_enrollment_id (nullable — প্রথম assessment-এ enrollment থাকে না), service_id, therapist_id, branch_id, room_id, date, start_time, end_time, type (assessment/therapy/consultation/follow_up), status, source (walk_in/phone/online/recurring), cancel_reason, is_late_cancellation, rescheduled_from_id, confirmed_by/at, checked_in_at |
| `therapy_sessions` | appointment_id (unique), therapy_enrollment_id, patient_id, therapist_id, service_id, date, start/end, duration, goals, activities, observation, patient_response, progress, challenges, home_practice, next_session_plan, therapist_notes (internal), parent_summary, status (draft/finalized), finalized_at |
| `therapy_session_activities` | therapy_session_id, activity_type_id, note |

> **Double booking রোধ:** `appointments`-এ একটি generated column (`active_slot` = cancelled/no_show হলে NULL, নাহলে therapist+date+time) এবং তার উপর UNIQUE index — একই therapist-এর একই সময়ে দুটি appointment database-ই আটকাবে।

#### (জ) Assessment, Plan, Progress
| Table | মূল field |
|---|---|
| `assessments` | assessment_code, patient_id, assessment_type_id, appointment_id, assessor_user_id, branch_id, date, chief_complaint, findings, scores (json), recommendations, therapy_plan_note, status (draft/finalized), shared_with_parent |
| `assessment_recommendations` | assessment_id, service_id, frequency, priority, enrollment_id (convert হলে) |
| `assessment_reports` | assessment_id, version, file_path, generated_by, generated_at |
| `individual_plans` | enrollment_id, patient_id, title, start_date, review_date, status (draft/active/reviewed/closed) — Training enrollment-এর হলে ITP, Therapy-র হলে Therapy Plan |
| `plan_goals` | individual_plan_id, domain, title, target, baseline_level, current_level, activities, measurement, progress_percent, review_date, status (not_started/in_progress/achieved/discontinued) |
| `goal_progress_entries` | plan_goal_id, source_type/source_id (training_record বা therapy_session), date, score, note, recorded_by |
| `progress_reports` | patient_id, enrollment_id, period_start/end, type (monthly/quarterly/discharge), summary, prepared_by, status, shared_with_parent, file_path |
| `home_programs` | patient_id, enrollment_id, created_by, title, instructions, frequency, start/end_date, status, visible_to_parent |
| `timeline_events` | patient_id, event_type, eventable_type/id, title, occurred_at, branch_id, actor_id, visibility (internal/parent) |

#### (ঝ) Package ও Billing
| Table | মূল field |
|---|---|
| `packages` | name, service_id, category, sessions_count, validity_days, price, branch_id, is_active |
| `patient_packages` | patient_id, package_id, enrollment_id, invoice_item_id, total_sessions, used_sessions (cache), price, start_date, expiry_date, status (active/exhausted/expired/cancelled) |
| `package_usages` | patient_package_id, therapy_session_id, appointment_id, reason (session/no_show/late_cancel/adjustment), quantity (+/−), recorded_by — **ledger**, তাই remaining সবসময় যাচাইযোগ্য |
| `invoices` | invoice_no, patient_id, branch_id, guardian_id, issue_date, due_date, subtotal, discount_total, total, paid_total, due_total, status (draft/issued/partially_paid/paid/void), void_reason |
| `invoice_items` | invoice_id, item_type (admission/training_fee/therapy_session/package/assessment/other), service_id, enrollment_id, patient_package_id, therapy_session_id, billing_period (YYYY-MM), description, qty, unit_price, discount, line_total |
| `payments` | receipt_no, patient_id, branch_id, amount, method (cash/bkash/nagad/bank/card), transaction_ref, paid_at, received_by, payer_name, status |
| `payment_allocations` | payment_id, invoice_id, amount — এক payment একাধিক invoice-এ ভাগ করা যায়; অবশিষ্ট = advance |

#### (ঞ) Notification ও CMS
| Table | মূল field |
|---|---|
| `notifications` | Laravel standard (in-app) |
| `notification_templates` | key, channel (sms/email/whatsapp/in_app), language, subject, body |
| `notification_logs` | channel, recipient, template, status, provider_response |
| `notices` | title, body, audience (all/parents/staff/branch), publish_at, expires_at, show_on_website |
| `website_pages` + `page_sections` | slug, meta_title, meta_description, og_image / section_key, content (json), sort, is_visible |
| `gallery_albums`, `gallery_items` | album, image/video, caption, consent_checked |
| `testimonials`, `faqs`, `contact_messages` | — |

### ৬. মূল Relationship সারসংক্ষেপ

| Relationship | ধরন |
|---|---|
| Patient → Enrollments | ১ : অনেক (০ সহ) |
| Enrollment → Training Enrollment / Therapy Enrollment | ১ : ১ (type অনুযায়ী ঠিক একটি) |
| Training Group (Class) → Training Enrollments | ১ : অনেক (= class roster) |
| Trainer → Training Enrollments, Classes | ১ : অনেক |
| Therapist → Therapy Enrollments, Appointments, Therapy Sessions | ১ : অনেক |
| Therapy Enrollment → Appointments → Therapy Session | ১ : অনেক → ১ : ০/১ |
| Training Session → Training Records | ১ : অনেক (প্রতি student একটি) |
| Patient ↔ Guardian | অনেক : অনেক (ভাই-বোন একই guardian শেয়ার করতে পারে) |
| Enrollment → Individual Plan → Goals → Progress Entries | ১ : অনেক : অনেক : অনেক |
| Patient Package → Package Usages ← Therapy Session | ledger |
| Payment ↔ Invoice | অনেক : অনেক (payment_allocations দিয়ে) |

**Derived "Patient Type" query (উদাহরণ):**
```sql
SELECT p.id,
  MAX(e.type='training' AND e.status='active') AS is_student,
  MAX(e.type='therapy'  AND e.status='active') AS has_therapy
FROM patients p LEFT JOIN enrollments e ON e.patient_id = p.id
GROUP BY p.id;
```

---

## ৭. Role ও Permission Matrix

চিহ্ন: **F** = সম্পূর্ণ · **B** = নিজ branch · **A** = শুধু assigned · **O** = শুধু নিজের child · **R** = শুধু দেখা · **—** = নেই

| Module | Super Admin | Branch Admin | Receptionist | Trainer | Therapist | Accountant | Parent |
|---|---|---|---|---|---|---|---|
| Patient demographic | F | B | B (create/edit) | A (R) | A (R) | B (R) | O (R) |
| Patient clinical info | F | B | create at intake | A (R, সীমিত) | A | — | — |
| Guardian | F | B | B | — | A (R) | B (R) | নিজের profile |
| Enrollment | F | B | B (create) | A (R) | A (R) | B (R) | O (R) |
| Class | F | B | B (R) | নিজের class (R) | — | — | — |
| Training Attendance | F | B | B (R) | A (mark) | — | — | O (R) |
| Training Record | F | B (R) | — | A (create/edit) | — | — | O (parent_note শুধু) |
| Appointment | F | B | B (create/confirm) | — | A (R/status) | — | O (R/request) |
| Therapy Session | F | B (R) | — | — | A (create/edit) | — | O (parent_summary শুধু) |
| Assessment | F | B (R) | — | — | A (create) | — | O (shared report) |
| Plans & Goals | F | B (R) | — | A (training) | A (therapy) | — | O (R) |
| Home Program | F | B | — | A | A | — | O (R) |
| Package / Invoice / Payment | F | B | B (basic billing) | — | — | B (F) | O (R) |
| Accounts: Expense / Voucher | F | B (অনুমোদন) | Petty cash | — | — | F | — |
| Accounts: Payroll | F | B (অনুমোদন) | — | নিজের payslip | নিজের payslip | F | — |
| Accounts: Cash Closing | F | B (গ্রহণ) | নিজের | — | — | F | — |
| Financial Statements (P&L, Balance Sheet) | F | B | — | — | — | F | — |
| Discount | F | B (limit সহ) | ছোট limit পর্যন্ত | — | — | B | — |
| Reports | F | B | daily collection | নিজের | নিজের | Financial | — |
| CMS | F | — | — | — | — | — | — |
| Users & Roles, Settings | F | B (staff) | — | — | — | — | — |
| Audit Log | F | B (R) | — | — | — | — | — |

**Record-level scope নিয়ম (Laravel Policy দিয়ে):**
- **Trainer** একজন patient দেখতে পারবে শুধু যদি ওই patient-এর **active training enrollment**-এ সে trainer, অথবা সে ওই class-এর lead/assistant/substitute trainer। Therapy session note সে দেখবে না।
- **Therapist** দেখতে পারবে শুধু যদি তার নামে **active therapy enrollment** বা আসন্ন/চলতি **appointment** আছে। অন্য therapist-এর internal note দেখতে পাবে কিনা — setting দিয়ে নিয়ন্ত্রিত (default: একই patient-এর team হলে summary দেখবে)।
- **Parent** শুধু `patient_guardians`-এ `can_access_portal = true` থাকা child দেখবে। Internal note কখনো নয় — শুধু parent_note / parent_summary / shared report।
- **Branch Admin/Receptionist** — patient-এর home branch অথবা তার branch-এ active enrollment থাকলে।
- পুরোনো trainer/therapist (assignment শেষ) — নতুন data দেখতে পারবে না, নিজের লেখা পুরোনো record দেখতে পারবে।

---

## ৮. REST API Architecture

**Convention:** prefix `/api/v1`, JSON, Laravel API Resource, pagination `?page=&per_page=`, filter `?filter[status]=active`, sort `?sort=-date`। Error format `{ message, errors }`। সব route role/permission middleware + Policy দিয়ে সুরক্ষিত।

```
# Auth
POST   /auth/login            POST /auth/logout        GET /auth/me
POST   /auth/forgot-password  POST /auth/reset-password PUT /auth/password

# Public (auth ছাড়া, rate limited)
GET    /public/services  /public/services/{slug}  /public/therapists  /public/therapists/{slug}
GET    /public/trainers  /public/branches  /public/gallery  /public/testimonials  /public/faqs  /public/pages/{slug}
GET    /public/availability?branch=&service=&therapist=&date=
POST   /public/appointment-requests     POST /public/contact

# Patients
GET|POST       /patients               GET|PUT /patients/{id}
GET            /patients/search?q=     (নাম / ID / ফোন — receptionist quick search)
GET            /patients/{id}/timeline
GET|POST       /patients/{id}/guardians      /patients/{id}/documents   /patients/{id}/consents
GET            /patients/{id}/billing-summary

# Enrollments
GET|POST       /enrollments            GET|PUT /enrollments/{id}
POST           /enrollments/{id}/hold | resume | complete | discontinue | transfer

# Training
GET|POST       /classes                GET|PUT /classes/{id}    GET /classes/{id}/roster
GET|POST       /training-attendance    POST /training-attendance/bulk
GET            /training-attendance/monthly?enrollment=&month=2026-10
GET|POST       /training-sessions      GET|PUT /training-sessions/{id}
GET|POST       /training-records       PUT /training-records/{id}   POST /training-records/{id}/finalize

# Therapy
GET|POST       /therapy-enrollments
GET|POST       /appointment-requests   POST /appointment-requests/{id}/convert
GET|POST       /appointments           PUT /appointments/{id}
POST           /appointments/{id}/confirm | check-in | cancel | no-show | reschedule
GET|POST       /therapy-sessions       PUT /therapy-sessions/{id}   POST /therapy-sessions/{id}/finalize

# Assessment / Plan / Progress
GET|POST       /assessments            PUT /assessments/{id}   GET /assessments/{id}/pdf
GET|POST       /plans                  GET|POST /plans/{id}/goals   POST /goals/{id}/progress
GET|POST       /progress-reports       GET|POST /home-programs

# Billing
GET|POST       /packages               GET|POST /patient-packages
GET|POST       /invoices               POST /invoices/{id}/issue | void   GET /invoices/{id}/pdf
GET|POST       /payments               GET /payments/{id}/receipt
GET            /dues

# Role-specific dashboards
GET            /dashboard/admin   /trainer/today   /therapist/today

# Parent portal (আলাদা namespace, শুধু নিজের child)
GET            /portal/children   /portal/children/{id}
GET            /portal/children/{id}/appointments | attendance | training-records | therapy-sessions
               | progress | reports | home-programs | invoices
POST           /portal/appointment-requests

# Reports (PDF/Excel export: ?format=pdf|xlsx)
GET            /reports/patients | students | attendance | training | therapy | appointments
               | therapists | trainers | branches | revenue | payments | dues | progress | assessments

# Admin
CRUD           /branches /rooms /holidays /services /trainers /therapists /users /roles
CRUD           /cms/pages /cms/gallery /cms/testimonials /cms/faqs /notices
GET            /notifications   POST /notifications/{id}/read   GET /audit-logs
```

---

## ৯. React Folder Structure

```
frontend/
├── src/
│   ├── app/                 # App.tsx, providers (QueryClient, Auth), router
│   ├── routes/              # route definitions + RoleGuard
│   ├── layouts/             # WebsiteLayout, AdminLayout, TrainerLayout, TherapistLayout, ParentLayout
│   ├── components/
│   │   ├── ui/              # Button, Input, Modal, Table, Badge, Card, Tabs, Drawer
│   │   └── shared/          # PatientSearch, DatePicker, StatusBadge, FileUpload, EmptyState
│   ├── features/
│   │   ├── auth/            # api.ts, hooks, pages, components, types (প্রতিটি feature-এ একই pattern)
│   │   ├── dashboard/
│   │   ├── patients/
│   │   ├── enrollments/
│   │   ├── training/        # classes, attendance, training-sessions, records
│   │   ├── trainers/
│   │   ├── therapists/
│   │   ├── therapy/         # therapy-enrollments, therapy-sessions
│   │   ├── appointments/
│   │   ├── assessments/
│   │   ├── plans/
│   │   ├── billing/         # packages, invoices, payments
│   │   ├── portal/          # parent portal
│   │   ├── reports/
│   │   ├── cms/
│   │   └── users/
│   ├── api/                 # axios client (withCredentials, CSRF, interceptors)
│   ├── hooks/               # usePermission, useBranch, useDebounce
│   ├── contexts/            # AuthContext, BranchContext
│   ├── utils/               # date (Asia/Dhaka), currency (৳), age calc
│   ├── types/               # shared TS types
│   └── i18n/                # bn.json, en.json
├── public/  ·  .env.production  ·  vite.config.ts
```
Library: Vite, TypeScript, React Router, Axios, **TanStack Query** (cache/refetch), React Hook Form + Zod, Tailwind CSS, Recharts, react-i18next।

## ১০. Laravel Folder Structure

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/   # Auth, Patient, Enrollment, TrainingGroup, TrainingAttendance,
│   │   │                         # TrainingSession, TrainingRecord, Appointment, TherapySession,
│   │   │                         # Assessment, Invoice, Payment, Report ...
│   │   │   ├── Portal/           # Parent portal controllers
│   │   │   └── Public/           # Website controllers
│   │   ├── Requests/             # FormRequest validation (প্রতিটি action-এর জন্য)
│   │   ├── Resources/            # API Resource (role অনুযায়ী field লুকানো)
│   │   └── Middleware/
│   ├── Models/                   # Patient, Enrollment, TrainingEnrollment, TherapyEnrollment,
│   │                             # TrainingGroup, Trainer, Therapist, Appointment, ...
│   ├── Enums/                    # EnrollmentType, AppointmentStatus, AttendanceStatus, PaymentMethod
│   ├── Policies/                 # PatientPolicy, TrainingRecordPolicy, TherapySessionPolicy ...
│   ├── Services/                 # EnrollmentService, AppointmentService, AvailabilityService,
│   │                             # AttendanceService, PackageService, BillingService,
│   │                             # IdGenerator, TimelineService, ReportService, PdfService
│   ├── Observers/                # timeline event + audit log
│   ├── Notifications/            # AppointmentConfirmed, PaymentReceived, ...
│   ├── Channels/                 # SmsChannel (future), WhatsAppChannel (future)
│   ├── Jobs/                     # SendReminder, GenerateMonthlyTrainingInvoices, ExpirePackages
│   └── Console/                  # scheduled commands
├── database/ migrations · seeders (roles, permissions, services, demo) · factories
├── routes/ api.php · web.php (SPA fallback) · console.php
├── resources/views/pdf/          # Assessment, Invoice, Receipt, Progress report template
└── tests/ Feature (policy ও workflow test) · Unit
```

---

## ১১–১৫. UI পরিকল্পনা

### ১১. Public Website
- **Design:** আধুনিক, পেশাদার, শিশু-বান্ধব; Green + Blue branding; গোলাকার card, নরম illustration; mobile-first।
- **Homepage section ক্রম:** Header (logo, menu, ফোন, "Appointment" button) → Hero (মূল বার্তা + দুটি CTA: Book Appointment / Call Now) → Trust/Statistics (শিশু সংখ্যা, বছর, therapist, branch) → **Therapy Services** → **Training Programs** (আলাদা section) → About C-STAR → Why Choose Us → Therapists → Branches → Testimonials → Gallery → FAQ → Appointment CTA → Contact (map) → Footer।
- **Appointment page:** ধাপে ধাপে form (Branch → Service → Therapist (ঐচ্ছিক) → তারিখ → সময় → তথ্য) — mobile-এ এক screen-এ একটি ধাপ। Submit-এর পর "আমরা শীঘ্রই কল করব" confirmation।
- Floating WhatsApp/Call button, Bangla/English toggle।

### ১২. Admin Dashboard (Modern SaaS)
- বাম sidebar (collapsible), উপরে global patient search + branch selector + notification bell।
- **Stat card:** Total Children · Regular Students · Therapy-only · Student + Therapy · Active Trainers · Active Therapists · আজকের Training Session · আজকের Therapy Session · আজকের Appointment · আজকের Collection · Total Due।
- **Chart:** New Registration (মাসিক), Enrollment (Training vs Therapy), Monthly Revenue, Attendance Rate, Therapy Sessions, Branch Performance।
- **Action list:** নতুন online request, unconfirmed appointment, unfinalized note, মেয়াদ শেষ হওয়া package, বেশি due।
- **Patient Profile:** উপরে header (ছবি, ID, বয়স, label badge, guardian ফোন, due) + quick action (New Enrollment / Appointment / Payment) + tabs।

### ১৩. Trainer Dashboard (সহজ, দ্রুত)
- **Today screen:** আজকের class card (সময়, room, student সংখ্যা) + Present/Absent গণনা + Pending Training Notes।
- **Attendance:** student-এর ছবি সহ list, default "Present", এক tap-এ Absent/Late/Leave — "Save All"।
- **Training Record:** student নির্বাচন → activity chip (Fine Motor, Gross Motor…) tap করে বাছাই → performance ১–৫ star → ছোট note → Save। আগের দিনের next plan উপরে দেখাবে।
- Mobile-এ bottom navigation: Today · Students · Records · Profile।

### ১৪. Therapist Dashboard (Clinical workflow)
- **Today:** সময় অনুযায়ী appointment list (status color), check-in হলে "Start Session"।
- **Session Note screen:** বামে patient summary (diagnosis, active goals, last session-এর next plan, package remaining) ; ডানে structured form (Goals worked → Activities → Observation → Response → Progress → Home Practice → Next Plan) ; Draft auto-save ; Finalize।
- Assessment form (type অনুযায়ী template), Plan/Goal editor, Home Program, নিজের weekly schedule ও leave।
- Tablet-এ দুই column, mobile-এ এক column।

### ১৫. Parent Portal (খুব সহজ, mobile-first)
- Bottom navigation: **Home · Schedule · Progress · Billing · Profile**।
- একাধিক সন্তান থাকলে উপরে child switcher।
- **Dynamic:** child-এর enrollment অনুযায়ী section দেখাবে —
  - Regular Student → Training Schedule, Attendance (মাসিক calendar + rate %), Training Progress
  - Therapy Patient → Appointments, Therapy Sessions (parent summary), Therapy Progress, Home Program
  - দুটোই → দুটো section-ই
- Home card: পরবর্তী appointment, এই মাসের attendance %, due amount, নতুন report।
- Bangla ভাষা default রাখার সুপারিশ।

---

## ১৬–২০. Workflow

### ১৬. Appointment Workflow
```
[Online] Visitor form → appointment_request (New) → Admin notification
   → Receptionist ফোন করে যাচাই → existing patient খোঁজা / নতুন patient তৈরি
   → Appointment তৈরি (Confirmed) → Parent-কে SMS/notification
[Walk-in/Phone] Receptionist → Patient search → Appointment (Confirmed)
[Recurring] Therapy enrollment slot → scheduler আগামী ৪ সপ্তাহের appointment তৈরি করে

Confirmed → (দিনের আগে Reminder) → Checked-in (reception) → Therapist session note
   → Completed (note save হলে স্বয়ংক্রিয়) → Package থেকে ১ session কাটা / invoice item
বিকল্প: Cancelled (কারণ সহ; নির্দিষ্ট সময়ের কম আগে হলে Late Cancel) · No Show · Rescheduled
```
**Availability হিসাব:** therapist schedule (branch, weekday) − ছুটি/leave − holiday − ইতিমধ্যে booked slot।

### ১৭. Training Workflow
```
Assessment → Training Enrollment (Class + Trainer + monthly fee) → ITP তৈরি (goals)
প্রতিদিন: Trainer → Attendance (bulk) → Training Session খোলে
   → উপস্থিত student-দের Training Record (activities, performance, goal score)
   → Goal progress update → Parent note portal-এ
মাসিক: Attendance report (rate %), Training fee invoice (স্বয়ংক্রিয়)
নির্দিষ্ট সময় পর: ITP Review → নতুন goal / Class change / Complete
```
**নিয়ম:** Absent/Leave student-এর training record তৈরি করা যাবে না। Holiday দিনে attendance স্বয়ংক্রিয় "Holiday"।
**Attendance rate** = (Present + Late) ÷ (মোট কার্যদিবস − Holiday − Leave) × ১০০ (Leave বাদ দেওয়া হবে কিনা setting-এ)।

### ১৮. Therapy Workflow
```
Registration → Assessment (Finalized) → Recommendation (যেমন Speech ২/সপ্তাহ, OT ২/সপ্তাহ)
   → প্রতিটি recommendation থেকে Therapy Enrollment (service + therapist + slot)
   → Package বিক্রি (ঐচ্ছিক) → Recurring appointments
   → Session → Session Note (draft → finalize) → goal progress → home practice
   → Package remaining হালনাগাদ → ২ session বাকি থাকলে renewal alert
   → মাসিক/ত্রৈমাসিক Progress Report → Parent-এর সাথে share
   → Continue / Therapist change / Discharge
```

### ১৯. Billing Workflow
```
চার্জের উৎস:
  ├── Registration/Admission fee (একবার)
  ├── Assessment fee
  ├── Training: মাসিক fee (১ তারিখে স্বয়ংক্রিয় invoice; একই মাস দুবার নয়)
  ├── Therapy package (অগ্রিম invoice → patient_package)
  └── Therapy per-session (session complete হলে invoice item)
      ↓
Invoice (Draft → Issued) → Discount (role-ভিত্তিক সীমা, কারণ বাধ্যতামূলক)
      ↓
Payment (Cash/bKash/Nagad/Bank/Card + transaction ref) → এক বা একাধিক invoice-এ allocation
      ↓
Receipt (PDF/print) → Due হালনাগাদ → Parent portal-এ দেখা যাবে
দিন শেষে: Receptionist-ভিত্তিক cash collection report (method অনুযায়ী)
```
**নিয়ম:** Issue হওয়া invoice delete হবে না — শুধু Void (কারণ সহ, audit log)। Training ও Therapy আলাদা invoice item হিসেবে থাকবে, তাই revenue আলাদাভাবে report করা যাবে।

### ২০. Reporting Workflow
```
Report নির্বাচন → Filter (তারিখ, branch, service, trainer/therapist, status)
   → স্ক্রিনে table + chart → Export (PDF / Excel)
```
| ধরন | উদাহরণ |
|---|---|
| Operational | আজকের appointment, attendance sheet, pending notes |
| Clinical | Patient progress, goal achievement, assessment summary |
| Training | Class-wise attendance %, trainer-wise records |
| Therapy | Therapist-wise session, no-show rate, service utilization |
| Financial | Revenue (training vs therapy), payment method, due/aging, daily collection |
| Management | Branch performance, নতুন registration, enrollment trend |

Report শুধু permission অনুযায়ী (accountant → financial, trainer → নিজের)। Export audit log-এ লেখা হবে।

---

## ২১. Security Architecture

| স্তর | ব্যবস্থা |
|---|---|
| Transport | পুরো site HTTPS (cPanel AutoSSL), HSTS |
| Authentication | Sanctum SPA cookie (httpOnly, secure, SameSite), CSRF, login rate limit (৫ বার/মিনিট), দীর্ঘ inactivity-তে logout |
| Authorization | Spatie role/permission (route level) + **Policy (record level)** — নীতি ৭-এর scope নিয়ম |
| Field-level | API Resource role অনুযায়ী clinical/internal field বাদ দেয় |
| Validation | সব input FormRequest দিয়ে; mass-assignment সুরক্ষা |
| File upload | mime + size check, random নাম, **`storage/app/private`** (public URL নেই), signed temporary URL দিয়ে download |
| Rate limiting | Public appointment form ও login-এ throttle + honeypot/captcha |
| Audit | Create/Update/Delete + clinical record **view** + export + login — সব audit_logs-এ |
| Clinical note | Finalize হওয়ার পর edit নয় — শুধু amendment (কারণ সহ) |
| Data | Soft delete, password bcrypt, `.env` public_html-এর বাইরে, `APP_DEBUG=false` |
| Backup | প্রতিদিন database dump (cron) + সাপ্তাহিক file backup, server-এর বাইরে কপি |
| Parent isolation | `/portal` route শুধু `patient_guardians` link দিয়ে query — অন্য child-এর ID দিলে 403/404 |

---

## ২২. cPanel Deployment Architecture

```
/home/cstaruser/
├── cstar-app/                 ← Laravel (public_html-এর বাইরে — নিরাপদ)
│   ├── app, config, routes, storage, vendor, .env ...
└── public_html/               ← শুধু public ফাইল
    ├── index.php              ← Laravel public/index.php (path ../cstar-app-এ বদলানো)
    ├── .htaccess              ← /app /trainer /therapist /portal → React index.html; বাকি সব (website, /api) → Laravel
    ├── build/ বা assets/      ← React production build (npm run build-এর output)
    └── storage → symlink (শুধু public media; patient document নয়)
```
**ধাপ:**
1. Local/CI-তে: `composer install --no-dev -o` এবং `npm run build` (React)।
2. ফাইল upload (Git Version Control / FTP / zip) — server-এ Node.js লাগবে না।
3. cPanel-এ MySQL database + user, PHP 8.3 নির্বাচন (MultiPHP), `.env` সেট।
4. `php artisan migrate --force`, `config:cache`, `route:cache`, `storage:link` (SSH বা cPanel Terminal; না থাকলে একবারের জন্য সুরক্ষিত deploy route)।
5. **Cron:** `* * * * * php /home/cstaruser/cstar-app/artisan schedule:run` — এর মধ্যেই `queue:work --stop-when-empty`, reminder, monthly invoice, backup।
6. AutoSSL চালু, HTTP → HTTPS redirect।

---

## ২৩. Development Roadmap

| Sprint (আনুমানিক) | কাজ |
|---|---|
| ১ (সপ্তাহ ১) | Requirement চূড়ান্ত, নিচের "সিদ্ধান্ত" অংশ নিশ্চিত, wireframe |
| ২ (সপ্তাহ ২–৩) | UI/UX design (Website + Admin + Trainer + Therapist + Parent), design system |
| ৩ (সপ্তাহ ৩) | ERD চূড়ান্ত, migration লেখা, seeder |
| ৪ (সপ্তাহ ৪) | Laravel setup, Auth, Roles, Branch, Users |
| ৫ (সপ্তাহ ৫–৬) | Public Website + CMS (basic) + Online Appointment Request |
| ৬ (সপ্তাহ ৬–৭) | Patient + Guardian + Documents + **Enrollment System** |
| ৭ (সপ্তাহ ৮–৯) | Class + Trainer + Training Attendance + Training Session/Record + ITP |
| ৮ (সপ্তাহ ১০–১১) | Therapist + Schedule + Appointment + Therapy Session |
| ৯ (সপ্তাহ ১২) | Assessment + Plans/Goals + Timeline + PDF |
| ১০ (সপ্তাহ ১৩–১৫) | Package + Invoice + Payment + Due + **Chart of Accounts, journal, Billing auto-posting** |
| ১১ (সপ্তাহ ১৬–১৭) | **Accounts A** — Expense, Voucher (approval), Cash Closing, Petty Cash, Cash/Bank Book, Ledger, Trial Balance, P&L, Balance Sheet, Period Lock |
| ১২ (সপ্তাহ ১৮–১৯) | **Accounts B** — Employee, Salary setup, Payroll, Therapist session payout, Advance, Payslip |
| ১৩ (সপ্তাহ ২০) | Parent Portal |
| ১৪ (সপ্তাহ ২১) | Reports + Dashboard charts + Notifications (in-app/email) |
| ১৫ (সপ্তাহ ২২) | **Accounts C** — Vendor/Payables, Bank Reconciliation, Fixed Assets, Budget, Cash Flow, Year-end closing |
| ১৬ (সপ্তাহ ২৩) | Security hardening, Testing (policy ও accounting test বিশেষভাবে), UAT |
| ১৭ (সপ্তাহ ২৪) | cPanel deployment, opening balance, staff training, go-live |

**মোট সময়:** প্রায় ২৪ সপ্তাহ (Accounts যোগ হওয়ায় ১৮ থেকে বেড়েছে)।

**মূল নীতি:**
- Enrollment system (sprint ৬) সম্পূর্ণ ও test করা না হওয়া পর্যন্ত training/therapy module শুরু নয়, কারণ বাকি সব এর উপর নির্ভরশীল।
- Billing চালুর প্রথম দিন থেকেই accounts-এ auto-posting চলবে, যাতে কোনো লেনদেন হিসাবের বাইরে না থাকে।

## ২৪. MVP Scope

**অন্তর্ভুক্ত:** Public Website · Login + ৭টি Role · Branch · Patients + Guardians · **Enrollments (Training + Therapy)** · Classes · Trainers · Training Attendance · Training Sessions/Records · Therapists + Schedule · Therapy Services · Appointments (online request সহ) · Therapy Sessions · Basic Assessment (PDF) · Individual Plan/Goals (basic) · Packages · Invoices · Payments · Due · **Accounts (auto-posting, Expense, Voucher, Cash Closing, Ledger, Trial Balance, P&L, Balance Sheet, Payroll)** · Parent Portal · Basic Reports (PDF) · In-app notification · Audit log · Basic CMS।

**MVP-তে নয় (go-live-এর পরপরই):** Accounts C (Vendor/Payables, Bank Reconciliation, Fixed Assets, Budget), SMS/WhatsApp, online payment gateway, advanced chart, home program parent feedback, Excel import।

## ২৫. Future Scope
SMS (BD gateway) · WhatsApp · Online Payment (bKash/SSLCommerz) · Email automation · Mobile App (Sanctum token) · Advanced analytics ও progress chart · Video consultation · Parent নিজে slot book · Digital consent ও e-signature · Automated reminder · Staff attendance (biometric) · দাতাভিত্তিক fund accounting · Inventory · Multi-language report · Waiting list automation।

---

## ২৬. আমার চিহ্নিত Missing Business Logic

আপনার requirement-এ নেই, কিন্তু বাস্তবে C-STAR চালাতে লাগবে:

1. **ছুটির calendar (Holiday)** — শুক্রবার সাপ্তাহিক ছুটি, সরকারি ছুটি, center ছুটি। এটা ছাড়া attendance "Holiday" ও appointment availability ঠিকভাবে হিসাব হবে না।
2. **Recurring therapy appointment** — নিয়মিত therapy patient প্রতি সপ্তাহে একই সময়ে আসে; প্রতিবার হাতে appointment বানানো অবাস্তব।
3. **Online request ≠ Patient** — website form থেকে সরাসরি patient না বানিয়ে আগে request, reception যাচাই করবে (duplicate ও ভুয়া entry রোধ)।
4. **Training-এর মাসিক fee** — regular student সাধারণত মাসিক fee দেয়; স্বয়ংক্রিয় মাসিক invoice দরকার। Absent থাকলে fee কমবে কিনা — নীতি ঠিক করতে হবে।
5. **Package নীতি** — No-show বা দেরিতে cancel করলে session কাটা যাবে কিনা? Package-এর মেয়াদ (validity) কত দিন? মেয়াদ শেষে বাকি session-এর কী হবে?
6. **Therapist অনুপস্থিত হলে** — ঐ দিনের appointment reschedule বা substitute therapist; Trainer অনুপস্থিত হলে substitute trainer (সাময়িক access সহ)।
7. **Class পূর্ণ হলে Waiting list** — max students পার হলে কী হবে।
8. **Enrollment শেষ করার কারণ** — Discharge / Dropout / Transfer / Goal achieved — report-এর জন্য জরুরি।
9. **Class বা Therapist পরিবর্তনের ইতিহাস** — কে কখন কার অধীনে ছিল (report ও access-এর জন্য)।
10. **Clinical note lock** — finalize করার পর note বদলানো যাবে না, শুধু amendment।
11. **Parent-এর জন্য আলাদা summary** — therapist-এর internal clinical note সরাসরি parent দেখবে না; parent-এর জন্য সহজ ভাষায় আলাদা field।
12. **Consent** — চিকিৎসা consent এবং ছবি/ভিডিও website-এ ব্যবহারের consent (gallery-তে শিশুর ছবি দেওয়ার আগে বাধ্যতামূলক)।
13. **Discount/Concession নীতি** — কে কত % পর্যন্ত discount দিতে পারবে; দরিদ্র পরিবারের জন্য scholarship/concession।
14. **Advance payment ও Refund** — অগ্রিম টাকা, package cancel হলে refund।
15. **দিন শেষের cash হিসাব** — কোন receptionist কত টাকা নিল (cash, bKash আলাদা)।
16. **বাংলা ভাষা** — Parent portal ও report-এ বাংলা দরকার হবে; PDF-এ বাংলা ঠিকমতো দেখাতে **mPDF** লাগবে (dompdf বাংলা যুক্তাক্ষর ভাঙে)।
17. **একাধিক branch-এ একই patient** — home branch এক জায়গায়, therapy অন্য branch-এ — দুই branch admin-ই দেখতে পারবে।
18. **ভাই-বোন** — একই guardian-এর একাধিক সন্তান; parent এক login-এ সবাইকে দেখবে।
19. **Duplicate patient রোধ** — নতুন registration-এর সময় একই ফোন + জন্মতারিখ মিলে গেলে সতর্কবার্তা।
20. **Clinical supervisor/Head therapist** — junior therapist-এর note/assessment review করার ভূমিকা লাগবে কিনা।

## ২৭. চূড়ান্ত সিদ্ধান্ত ✅ (০৬ অক্টোবর ২০২৬ — সব সুপারিশ অনুমোদিত)

| # | প্রশ্ন | চূড়ান্ত সিদ্ধান্ত |
|---|---|---|
| D1 | **Public website কীভাবে render হবে?** সম্পূর্ণ React SPA হলে Google SEO দুর্বল হয় — healthcare site-এর জন্য search-এ আসা খুব জরুরি। | Public website **Laravel Blade + Tailwind** (SEO ভালো, CMS সরাসরি কাজ করে, cPanel-এ দ্রুত); Admin/Trainer/Therapist/Parent অংশ **React SPA**। আপনি সম্পূর্ণ React চাইলে Laravel থেকে প্রতিটি page-এর meta tag inject করে কিছুটা সমাধান করা যাবে। |
| D2 | Patient ID প্রতি বছর ০০০০১ থেকে শুরু হবে? Branch code থাকবে (CSTAR-DHK-2026-00001)? | বছরভিত্তিক reset, branch code ছাড়া (patient branch বদলালেও ID একই থাকে) |
| D3 | No-show / Late cancel-এ package session কাটা যাবে? | Setting দিয়ে নিয়ন্ত্রণ; default: No-show কাটা যাবে, ২৪ ঘণ্টা আগে cancel করলে কাটা যাবে না |
| D4 | Training fee মাসিক নির্দিষ্ট, নাকি উপস্থিতি অনুযায়ী? | মাসিক নির্দিষ্ট |
| D5 | Parent login — ফোন + password, নাকি OTP? | MVP-তে ফোন + password (reception থেকে তৈরি); SMS gateway এলে OTP |
| D6 | একজন therapist কি অন্য therapist-এর internal note দেখতে পারবে? | একই patient-এর active team হলে হ্যাঁ (read-only); নাহলে না |
| D7 | UI ভাষা | Admin: English (বাংলা toggle); Parent Portal: বাংলা default |
| D8 | Branch কয়টি এবং MVP-তে multi-branch চালু হবে? | Architecture শুরু থেকেই multi-branch; data এক branch দিয়ে শুরু করা যাবে |

---

---

## ২৮. সম্পূর্ণ Accounts Module (সারসংক্ষেপ)

> বিস্তারিত design (Chart of Accounts, posting নিয়ম, table, API, UI): **[docs/C-STAR-Accounts-BN.md](docs/C-STAR-Accounts-BN.md)**

**মূল নীতি:** Billing শুধু রোগীর কাছ থেকে টাকা নেওয়ার হিসাব রাখে। Accounts রাখে পুরো center-এর হিসাব: আয়, খরচ, বেতন, ব্যাংক, সম্পদ আর দায়। ভেতরে পূর্ণ **double-entry**। Billing-এর প্রতিটি invoice বা payment থেকে নিজে থেকেই journal entry হবে, তাই কাউকে একই হিসাব দুবার লিখতে হবে না।

| অংশ | কাজ |
|---|---|
| Chart of Accounts | Asset / Liability / Equity / Income / Expense; C-STAR-এর জন্য তৈরি default খাত |
| Fund Accounts | Branch-এর Cash Box, Bank, bKash, Nagad |
| Auto-posting | Invoice, Payment, Discount, Refund, Package session থেকে journal |
| Voucher | Payment / Receipt / Journal / Contra, Maker-Checker অনুমোদন সহ |
| Expense | সহজ খরচ form, category, bill-এর ছবি, petty cash, recurring খরচ |
| Vendor ও Payable | Supplier-এর bill ও দেনা |
| Payroll | **নতুন `employees` table** (সব staff); নির্দিষ্ট / per-session / মিশ্র / revenue share বেতন; অগ্রিম; payslip |
| Cash Closing | দিন শেষে receptionist-ভিত্তিক cash মিলানো |
| Bank Reconciliation | Bank ও bKash/Nagad statement মেলানো |
| Fixed Assets | Asset register ও depreciation |
| Fiscal Year ও Period Lock | জুলাই–জুন বছর, মাস বন্ধ, year-end closing |
| Reports | Cash Book, Bank Book, Ledger, Trial Balance, P&L, Balance Sheet, Cash Flow, service ও branch অনুযায়ী লাভ, aging |

**Database-এ নতুন table:** `fiscal_years`, `accounting_periods`, `accounts`, `bank_accounts`, `journal_entries`, `journal_lines`, `posting_rules`, `expense_categories`, `expenses`, `recurring_expenses`, `vendors`, `vendor_bills`, `vendor_payments`, `employees`, `salary_structures`, `session_pay_rates`, `employee_advances`, `payroll_runs`, `payroll_items`, `cash_closings`, `bank_reconciliations`, `bank_statement_lines`, `fixed_assets`, `budgets`, `budget_lines`।

**Trainer ≠ Therapist নীতি বজায় থাকছে:** `employees` শুধু বেতন/HR-এর জন্য সাধারণ স্তর। `trainers` আর `therapists` আগের মতোই আলাদা থাকবে, শুধু তাদের সাথে `employee_id` যুক্ত হবে।

### Accounts-এর চূড়ান্ত সিদ্ধান্ত ✅ (০৬ অক্টোবর ২০২৬ — সব সুপারিশ অনুমোদিত)

| # | প্রশ্ন | চূড়ান্ত সিদ্ধান্ত |
|---|---|---|
| A1 | Fiscal year | জুলাই–জুন |
| A2 | Package-এর টাকা কখন আয় ধরা হবে | Session হলে তবেই আয় (Unearned Revenue) |
| A3 | Therapist-দের বেতনের ধরন | চারটি ধরনই সমর্থন করবে, প্রতি employee-তে আলাদা বাছাই। Default আপাতত "মাসিক নির্দিষ্ট"; center-এর বর্তমান পদ্ধতি জানার পর প্রয়োজনে বদলানো হবে |
| A4 | সব staff payroll-এ থাকবে? | হ্যাঁ, সবাই `employees`-এ |
| A5 | অনুমোদন ছাড়া খরচের সীমা | ৳৫,০০০ |
| A6 | VAT/Tax (TDS) | Setting থাকবে, default বন্ধ |
| A7 | Donation/Grant | Donation income account থাকবে, দাতাভিত্তিক হিসাব পরে |
| A8 | হাজিরা দিয়ে বেতন কাটা | MVP-তে হাতে লেখা কর্তন |
| A9 | পুরনো হিসাব | Go-live তারিখের opening balance দিয়ে শুরু |

---

**পরবর্তী ধাপ:** Sprint ৩, ৪ ও ৬ শেষ। এখন Sprint ৫ (Public Website)।
