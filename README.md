# SEO Academy LMS

SEO Academy is independently installed and operated by each customer. One installation supports exactly one academy.

A self-hosted Learning Management System built on a **FOSS-first** Laravel 13 modular monolith with Livewire 4, Tailwind CSS 4, Spatie Laravel Permission, and dual SQLite/MySQL support. Engineered to run on standard PHP shared hosting as well as VPS deployments without proprietary runtime lock-in.

---

## Implementation Status

### Implemented — Phase 1, Phase 1.1, Phase 2.1, Phase 2.2, Phase 2.3, Phase 2.4, Phase 2.5 & Phase 2.6

- **Laravel 13 Modular Monolith (`app/Domain/`):** 23 domain directories (`Academies`, `Admissions`, `AI`, `Analytics`, `Assignments`, `Attendance`, `Certificates`, `Courses`, `Identity`, `Learning`, `Lessons`, `LiveClasses`, `Notifications`, `Payments`, `Platform`, `Quizzes`, `Reports`, `Rewards`, `Students`, `Support`, `Tenants`, `Trainers`, `Video`).
- **Database Schema & Models:** 61 migrations supporting both **SQLite** (WAL mode, `busy_timeout=5000`) and **MySQL 8 / MariaDB** (InnoDB, `utf8mb4_unicode_ci`), with UUID primary keys (`HasUuids`) across domain models.
- **Single-Academy Isolation & RBAC:**
  - `Academy::current()` singleton guard, `TenantContext`, `ResolveAcademyContext`, `EnsureAcademyAccess`, and `BelongsToAcademy` trait.
  - Academy memberships (`academy_users`) with invitation-only onboarding (no public `/register` or `/academy/switch/{academy}`).
  - Spatie roles (`admin`, `trainer`, `support_staff`, `student`) and role-scoped dashboards (`/admin/dashboard`, `/trainer/dashboard`, `/support/dashboard`, `/student/dashboard`).
- **Zero-Terminal Browser Installation System (`/install`):**
  - Completely browser-driven deployment for Apache shared hosting and VPS environments without requiring terminal or SSH access.
  - Zero-dependency standalone pre-flight bootstrapper (`bootstrap/preflight.php`) that catches missing dependencies and auto-creates `.env` with a 256-bit AES `APP_KEY`.
  - Resilient, idempotent 9-step staged installation wizard:
    1. Server & PHP Requirements Check (PHP 8.3+, required extensions, memory, execution time, directory permissions).
    2. Composer Dependencies Verification (`composer.json`, `vendor/autoload.php`, `installed.json`, version constraint check).
    3. Automatic Environment Setup (`.env` creation, `APP_KEY` verification, webroot protection).
    4. Database Selection & Configuration (SQLite 3.35+ self-contained vs MySQL 8.4+ InnoDB with switching protection).
    5. Database Initialization & Schema Migrations (sequential execution of all 86 migrations, role seeding, no `migrate:fresh`).
    6. Filesystem Storage Verification (private media, backup, session, and cache directories).
    7. Academy Profile Setup (name, slug, support email, timezone, locale, currency, date/time format).
    8. Primary Administrator Account Creation (super administrator with `admin` role).
    9. System Finalization & Security Lock (clears caches, generates `storage/app/private/installed.lock`, blocks installer routes).
  - Browser-based System Diagnostics (`/install/diagnostics`) with sanitized report export.
  - Guarded by `EnsureNotInstalled` and `EnsureApplicationInstalled` middleware.
- **Authenticated Private Media Streaming (`GET /media/{media}`):**
  - Stored outside webroot on `private_media` (`storage/app/private/media/`).
  - Enforces authentication, tenant authorization, valid active enrollment verification, and HTTP `Range` (`206 Partial Content`) streaming.
- **Design System & UI:**
  - Responsive Blade + Livewire 4 + Alpine.js components with dark mode (`dark:` + CSS custom properties) and pre-built Vite assets (`public/build/`).
- **Optional AI Foundation (Phase 1.1):**
  - `AiProviderInterface`, `NullAiProvider` (`LMS_AI_ENABLED=false` default), `OpenAiCompatibleProvider` (`openai_compatible` and `ollama` drivers), `AiAssistantService` (`RateLimiter` at `10`/min, `4000`-char input cap), and encrypted `AcademyAiCredential` model.
- **Academy Management & DB-Driven Admin Dashboard (Phase 2.1):**
  - `AcademySettingsService`, `AcademyPolicy`, and Livewire `AcademySettings` (`/admin/academy/settings`) with Profile & Contact, Branding & Logo uploads, Domain Configuration, and General Settings tabs.
  - Real DB-driven `AdminDashboard` (`/admin/dashboard`) with single-academy KPI metrics, academy summary, and recent `AuditLog` activity.
- **User, Role, Membership & Invitation Management (Phase 2.2):**
  - `StudentManagementService`, `TrainerManagementService`, `MembershipManagementService`, `InvitationService`, `AcademyInvitationMail`, and policies (`StudentProfilePolicy`, `TrainerProfilePolicy`, `AcademyUserPolicy`, `InvitationPolicy`).
  - Livewire `StudentsIndex` (`/admin/students`) with search/filters, CRUD, global user reuse, and formula-safe CSV template download, preview, and transactional import (`/admin/students/csv-template`).
  - Livewire `TrainersIndex` (`/admin/trainers`), `MembersIndex` (`/admin/members`) for support staff, role assignment (`admin`, `trainer`, `support_staff`, `student`), membership suspension/revocation, and hashed-token invitation lifecycle (`/invitations/{token}/accept`).
  - Global user suspension (`is_active`) and membership status (`active`/`suspended`) enforcement in `LoginController`, `Livewire\Auth\Login`, `ResolveAcademyContext`, and `EnsureAcademyAccess`.
- **Course & Catalogue Management (Phase 2.3):**
  - `CourseCategory`, `CourseTrainer`, `CoursePrerequisite`, and extended `Course` catalogue fields (`category_id`, `difficulty`, `price`, `currency`, `is_free`, `visibility`, `enrollment_limit`, `published_at`, per-academy unique `slug`).
  - `CourseCategoryService`, `CourseManagementService` (with active-trainer membership validation and self/circular/cross-academy prerequisite cycle detection), `CoursePolicy`, and `CourseCategoryPolicy`.
  - Livewire `Admin\CoursesIndex` (`/admin/courses`) with search, multi-facet filters, sorting, pagination, thumbnail uploads, trainer/prerequisite assignment, status lifecycle transitions, and category modal; `Trainer\TrainerCoursesIndex` (`/trainer/courses`) for assigned courses; `Catalogue\PublicCoursesIndex` (`/courses`), `Student\StudentCoursesIndex` (`/student/courses`), and `CourseCatalogueController` (`/courses/{slug}` and `/admin/courses/{course}/preview`).
- **Course Structure, Modules, Lessons & Private Resources (Phase 2.4):**
  - Extended `Module`, `Lesson`, and `LessonMaterial` models plus `LessonPrerequisite` and `LessonProgress` schema (`lesson_prerequisites`, `lesson_progress`).
  - `CourseStructureService`, `ModulePolicy`, `LessonPolicy`, and `PrivateMediaService` free-preview/tenant streaming support for `Lesson` and `LessonMaterial` media.
  - Livewire `Courses\CourseStructureEditor` (`/admin/courses/{course}/structure` and `/trainer/courses/{course}/structure`) supporting module/lesson CRUD for all 6 lesson types (`video`, `text`, `pdf`, `external`, `live_class`, `assignment`), drag/order persistence, safe module deletion guard, prerequisite validation, and private MP4/WebM/PDF uploads; `LessonPreviewController` (`/courses/{course}/lessons/{lesson}/preview`) and curriculum outline on `catalogue.show`.
- **Administrator Enrollment Management & Lifecycle (Phase 2.5):**
  - Single-academy, administrator-controlled enrollment model: public self-enrollment and cart checkout are strictly disabled; only administrators assign, activate, suspend, reactivate, or cancel enrollments.
  - Extended `enrollments` schema (`academy_id`, `assigned_by`, `starts_at`, `expires_at`, `status`, `cancelled_at`, `cancellation_reason`, `suspended_at`, `suspension_reason`, `completed_at`, `notes`) with UUID primary keys and indices.
  - Robust 6-state lifecycle state machine (`pending`, `active`, `suspended`, `completed`, `expired`, `cancelled`) enforcing validated transition paths and explicit admin reactivation.
  - `EnrollmentManagementService` providing eligibility validation (student account active with `student` role, published course, prerequisite completion verification, and pessimistic capacity locking via `lockForUpdate`), single assignment, transactional bulk assignment, status transitions, expiry extensions, and automated database notifications.
  - Scheduled console command `php artisan enrollments:process-expired` (hourly in `routes/console.php`) automatically transitioning expired active enrollments to `expired` status and recording audit logs.
  - Strict access control via `EnrollmentPolicy` (admin-only mutation, student read-only view) and `PrivateMediaService` integration (active and valid enrollment required to stream course private media, while free preview lessons remain accessible).
  - Livewire `Admin\EnrollmentsIndex` (`/admin/enrollments`) management UI with search, metrics cards, single/bulk assign modals, and status action modals; read-only `Student\StudentCoursesIndex` (`/student/courses`) with enrolled-only views, validity badges, and administrator contact prompts when empty.
  - Comprehensive audit trail recording acting administrator IDs for all assignments (`enrollment.assigned`, `enrollment.bulk_assigned`), status transitions (`enrollment.status_changed`), and date extensions (`enrollment.dates_updated`).
- **Trainer & Support Staff Operations (Phase 2.6):**
  - **Trainer Operations:**
    - `TrainerOperationService` computing real-time dashboard metrics (`total_assigned_courses`, `published_assigned_courses`, `draft_assigned_courses`, `total_enrolled_students`, `active_cohorts`, recent courses with lesson counts, and recent enrollment activity).
    - Refactored `TrainerDashboard` (`/trainer/dashboard`) displaying live KPI metrics cards and activity feeds.
    - Assigned course management (`TrainerCoursesIndex` at `/trainer/courses`) strictly isolating courses assigned to the authenticated trainer.
    - Enrolled student roster (`TrainerStudentsIndex` at `/trainer/students`) providing read-only visibility into students enrolled in trainer's assigned courses with search (name, email, student code), course and status filters, and pagination.
    - Zero administrative privileges for trainers: strictly blocked from student account creation, enrollment modifications, and administrative routes (`/admin/*`).
  - **Support Staff Operations:**
    - Extended support ticketing schema (`2026_11_13_120000_update_support_tickets_for_phase26.php`) adding `priority` (`low`, `medium`, `high`, `urgent`), `assigned_to`, `assigned_at`, `resolved_at`, `closed_at`, `last_replied_at`, and new table `support_ticket_messages` with composite index `stm_ticket_internal_created_idx`.
    - `SupportTicket` model state machine enforcing valid transitions (`open`, `in_progress`, `waiting_for_response`, `resolved`, `closed`) and `SupportTicketMessage` with `scopePublicOnly`.
    - `SupportTicketService` handling ticket listing with multi-facet filters (`listForStaff`), staff assignment (`assignTicket`), status updates (`updateStatus`), public replies, and private internal notes (`addReply` / `addMessage`).
    - Dedicated `SupportDashboard` (`/support/dashboard`) computing live metrics (total, open, in progress, resolved today, assigned to me).
    - Support ticket queue (`SupportTicketsIndex` at `/support/tickets`) with search, status/priority/assignment filters, and pagination.
    - Interactive ticket detail (`SupportTicketDetail` at `/support/tickets/{ticket}`) featuring threaded conversation messages, internal note checkboxes with amber styling, status transition dropdowns, and staff assignment controls.
    - Strict boundary protection: internal notes (`is_internal = true`) are strictly hidden from students across all queries and views.
  - **Auditing & Notification Architecture:**
    - Audit logs recorded via `RecordsAuditLog` on assignments (`ticket.assigned`), status transitions (`ticket.status_changed`), public replies (`ticket.reply_added`), and internal notes (`ticket.internal_note_added`).
    - `AcademyNotification` database notifications dispatched to staff upon ticket assignment and to students upon ticket resolution and public replies.
  - **Access Control & RBAC Guardrails:**
    - Students and support staff blocked from `/trainer/*` routes.
    - Students blocked from `/support/*` routes.
    - Support staff blocked from `/admin/*` routes (`/admin/courses`, `/admin/enrollments`, `/admin/academy/settings`, `/admin/trainers`).
- **Automated Test Suite:** 27 test files covering boot, database connectivity, dual-engine migrations & compatibility (61 migrations), authentication & suspended accounts, authorization, media streaming, installer, AI foundation, admin dashboard, academy settings, student/trainer/support-staff/membership management, CSV import, invitation lifecycle, course/catalogue management, course structure/lessons/private media, administrator enrollment management, and trainer & support staff operations (`TrainerOperationsTest`, `SupportOperationsTest`).

### Planned — Phases 3–6 (Not Yet Implemented)

- **Phase 2 (Remaining):** Practice checks.
- **Phase 3:** Assignments, grading, quizzes, attendance tracking, certificates, and student rewards/gamification UI.
- **Phase 4:** Live classes integration (Jitsi/BigBlueButton), admissions workflow, notifications center.
- **Phase 5:** End-user AI assistant chat UI, RAG over `ai_faqs`/`ai_materials`, AI-assisted assignment feedback, SEO keyword research tools, and per-academy AI credential settings UI.
- **Phase 6:** Analytics dashboards, reporting exports, payment gateway integrations, and production hardening.

---

## Documentation

| Area | Document |
|---|---|
| **Installation** | [Installation Guide](docs/installation/installation-guide.md) · [Shared Hosting](docs/installation/shared-hosting.md) · [VPS Profile](docs/installation/vps.md) |
| **Operations** | [Backup & Restore](docs/backup-restore.md) |
| **Architecture** | [Overview](docs/architecture/overview.md) · [Database](docs/architecture/database-architecture.md) · [Single-Academy](docs/architecture/single-academy.md) · [Security](docs/architecture/security.md) · [Deployment & Security](docs/architecture/deployment-and-security.md) · [AI Integration](docs/architecture/ai-integration.md) |
| **AI Setup & Governance** | [Provider Setup](docs/ai/provider-setup.md) · [Privacy & Costs](docs/ai/privacy-and-costs.md) |
| **Licenses & Compliance** | [Dependencies & SPDX Inventory](docs/licenses/dependencies.md) · [Third-Party Notices](docs/licenses/third-party-notices.md) |
| **Audit & Verification** | [Existing Application](docs/audit/existing-application.md) · [Feature Inventory](docs/audit/feature-inventory.md) · [Migration Mapping](docs/audit/migration-mapping.md) · [Phase 1 Verification](docs/audit/phase1-verification.md) |

---

## System Requirements

- **PHP:** `^8.3` or `^8.4` (PHP 8.3+ required)
- **Composer:** `2.2+`
- **Required PHP Extensions:**
  - `bcmath` (Required for financial/grade calculations and idempotency tokens)
  - `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `gd`, `hash`, `intl`, `json`, `mbstring`, `openssl`, `pcre`, `session`, `tokenizer`, `xml`, `zip`
  - `pdo_sqlite` (if deploying with SQLite)
  - `pdo_mysql` (if deploying with MySQL / MariaDB)
- **Supported Databases:**
  - **SQLite 3.35+** (Default; recommended for single academy installations and standard shared hosting)
  - **MySQL 8.4+ InnoDB** or **MariaDB 10.11+ InnoDB** (`utf8mb4_unicode_ci`)
- **Node.js (Optional):** Pre-compiled production assets are bundled in `public/build/`. Node.js / npm is **not** required for production deployment unless modifying frontend stylesheets or JavaScript source code.

---

## Installation & Configuration

### Option A: Standard SQLite Setup (Recommended / Shared Hosting)

1. **Clone the repository:**
   ```bash
   git clone <repository-url> seo-academy
   cd seo-academy
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. **Configure environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Note: In `.env`, `DB_CONNECTION=sqlite` is enabled by default. SQLite database will be initialized automatically in `storage/app/private/database.sqlite`.*

4. **Run migrations and seed default roles:**
   ```bash
   php artisan migrate --seed
   ```

5. **Set directory permissions:**
   Ensure `storage/` and `bootstrap/cache/` are writable by your web server:
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```

6. **Serve or deploy:**
   - Web server document root must point to the `public/` directory.
   - For local development: `php artisan serve`

---

### Option B: MySQL 8.4+ InnoDB Setup

1. **Clone and install dependencies:**
   ```bash
   git clone <repository-url> seo-academy
   cd seo-academy
   composer install --no-dev --optimize-autoloader
   cp .env.example .env
   php artisan key:generate
   ```

2. **Create MySQL database:**
   ```sql
   CREATE DATABASE seo_academy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Update `.env` database configuration:**
   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=seo_academy
   DB_USERNAME=your_database_user
   DB_PASSWORD=your_secure_password
   ```

4. **Run migrations and seed initial roles:**
   ```bash
   php artisan migrate --seed
   ```

---

### Option C: Web Setup Wizard

If you prefer browser-based configuration, you can point your browser to `/install` after cloning and generating your application key. The interactive setup wizard will:
1. Verify system environment, extensions, and directory writability.
2. Test SQLite or MySQL connection credentials without leaking passwords.
3. Automatically execute migrations and seed administrative roles.
4. Generate the `installed.lock` guard file to protect against reinstallation.

---

## Production Optimization

For maximum performance on shared hosting or VPS environments:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

To schedule background maintenance (e.g. processing expired enrollments and certificate PDFs) on shared hosting, add a single cron job:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Web Server Deployment (Apache & Nginx)

### Apache Configuration
Ensure `mod_rewrite` is enabled. Point the `DocumentRoot` to `public/`:
```apache
<VirtualHost *:80>
    ServerName your-academy.com
    DocumentRoot /var/www/seo-academy/public

    <Directory /var/www/seo-academy/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/seo_academy_error.log
    CustomLog ${APACHE_LOG_DIR}/seo_academy_access.log combined
</VirtualHost>
```

### Nginx Configuration
```nginx
server {
    listen 80;
    server_name your-academy.com;
    root /var/www/seo-academy/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## Shared Hosting & VPS Deployments

- **Shared Hosting:** Refer to [docs/installation/shared-hosting.md](docs/installation/shared-hosting.md). SEO Academy requires no Node.js runtime, Redis daemon, or long-running worker processes on shared hosts.
- **VPS / Cloud:** Refer to [docs/installation/vps.md](docs/installation/vps.md) for automated systemd/supervisor setup.

---

## Upgrades

When pulling or deploying a new release version:

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan app:upgrade
```

The `app:upgrade` command safely applies additive database migrations, refreshes role capabilities, clears application and route caches, and validates system health.

---

## Backup & Recovery

SEO Academy includes built-in transactional backup and recovery capabilities:

- Documentation: [docs/backup-restore.md](docs/backup-restore.md)
- In the administrator dashboard, navigate to **Backups & Recovery** (`/admin/backups`) to create consistent database snapshots or download full archive packages.

---

## Security Guidance

- **Architecture:** Review [docs/architecture/security.md](docs/architecture/security.md).
- **Private Media:** All course videos, PDFs, and assignment attachments are stored outside the public web root in `storage/app/private/` and served exclusively through authenticated, enrollment-verified streaming controllers.
- **Role Isolation:** Exactly four canonical roles (`admin`, `trainer`, `support_staff`, `student`) are supported with strict boundary policies. Public self-registration is permanently disabled.
- **Reporting Vulnerabilities:** Please report security issues confidentially to the repository maintainers rather than opening a public issue.

---

## Contributing

We welcome community feedback and contributions!

1. Fork the repository.
2. Create a feature branch (`git checkout -b feature/improvement`).
3. Ensure all tests pass (`php artisan test`).
4. Follow PSR-12 and Laravel Pint coding standards (`vendor/bin/pint --format agent`).
5. Submit a pull request.

Please see [docs/licenses/third-party-notices.md](docs/licenses/third-party-notices.md) for third-party copyright notices.

---

## License

This project is licensed under the **Apache License, Version 2.0** (Apache-2.0).  
See the [LICENSE](LICENSE) file for the full license text.

Copyright 2026 SEO Academy contributors.

All third-party open-source components and their respective licenses are listed in [docs/licenses/dependencies.md](docs/licenses/dependencies.md) and [docs/licenses/third-party-notices.md](docs/licenses/third-party-notices.md). Third-party dependencies are governed by their own respective licenses and are not covered by the project's Apache-2.0 license.
