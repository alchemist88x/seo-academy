# Architecture Overview — SEO Academy

SEO Academy is independently installed and operated by each customer. One installation supports exactly one academy.

SEO Academy is a self-hosted Learning Management System (LMS) built as a **FOSS-first modular monolith** on Laravel 13, designed to run on everything from local SQLite setups to shared PHP hosting and dedicated MySQL servers with zero proprietary runtime lock-in.

---

## 1. FOSS-First Technology Stack

All runtime and development dependencies are Free and Open-Source Software (FOSS):

| Layer | Technology | Version Constraint (`composer.json` / `package.json`) | License | Role |
|---|---|---|---|---|
| **Language** | PHP | `^8.3` | PHP License | Server runtime |
| **Framework** | Laravel | `^13.17` | MIT | Routing, Eloquent ORM, Queues, Auth, Encryption |
| **Reactive UI** | Livewire | `^4.4` | MIT | Server-driven reactive components |
| **Styling** | Tailwind CSS | `^4.0.0` (`@tailwindcss/vite`, `forms`, `typography`) | MIT | Utility-first CSS & design tokens |
| **Client Interactivity** | Alpine.js | Bundled with Livewire 4 | MIT | Lightweight DOM state (sidebar, modals, theme toggle) |
| **RBAC** | `spatie/laravel-permission` | `^8.5` | MIT | Role & permission management |
| **Databases** | SQLite 3.35+ / MySQL 8.0+ (InnoDB) | Dual-engine via PDO | Public Domain / GPL | Relational persistence |
| **Testing** | PHPUnit | `^12.5.12` | BSD-3-Clause | Unit & feature test suite |
| **Asset Bundler** | Vite + `laravel-vite-plugin` | `^8.0.0` / `^3.1` | MIT | **Build-time only**; compiled to `public/build/` |

> **Zero Node.js in Production:** Frontend assets are pre-compiled into `public/build/` during CI or local build. Production and shared-hosting servers only require PHP 8.3+ and a supported database.

---

## 2. Modular Monolith (`app/Domain/*`)

Business logic is organized into **23 domain modules** under `app/Domain/`, keeping HTTP and Livewire layers thin:

```text
app/
├── Domain/
│   ├── Academies/      # Single-academy entity & academy_users memberships
│   ├── Admissions/     # Prospective student applications & review workflow
│   ├── AI/             # Optional provider-independent AI assistant & encrypted credentials
│   ├── Analytics/      # Chat & engagement metrics
│   ├── Assignments/    # Coursework assignments & student submissions
│   ├── Attendance/     # Live session attendance records
│   ├── Certificates/   # Course completion certificates
│   ├── Courses/        # Courses, course levels, modules, and CoursePolicy
│   ├── Identity/       # Authentication & identity domain services
│   ├── Learning/       # Practice checks & learning state
│   ├── Lessons/        # Lessons & lesson materials
│   ├── LiveClasses/    # Live sessions, class materials, and class videos
│   ├── Notifications/  # Academy announcements & read tracking
│   ├── Payments/       # Future billing & invoice domain (scaffolded)
│   ├── Platform/       # Media, Settings, PortalMenus, AuditLog, LoginAttempt, DatabaseInstallerService
│   ├── Quizzes/        # Quizzes, normalized quiz questions, and submissions
│   ├── Reports/        # Reporting & exports domain (scaffolded)
│   ├── Rewards/        # Gamification rules, events, and streaks
│   ├── Students/       # StudentProfile, Enrollment, EnrollmentManagementService, StudentManagementService, StudentProfilePolicy, EnrollmentPolicy
│   ├── Support/        # SupportTicket, SupportTicketMessage, SupportTicketService, SupportTicketPolicy
│   ├── Tenants/        # TenantContext (single-academy resolver) & BelongsToAcademy concern
│   ├── Trainers/       # TrainerProfile, TrainerManagementService, TrainerOperationService, TrainerProfilePolicy
│   └── Video/          # VideoProgress, VideoEvent, and PrivateMediaService
├── Http/
│   ├── Controllers/    # Thin controllers (LoginController, InstallController, PrivateMediaController, InvitationAcceptanceController, CourseCatalogueController, LessonPreviewController)
│   └── Middleware/     # ResolveAcademyContext, EnsureAcademyAccess, EnsureNotInstalled
├── Livewire/
│   ├── Auth/           # Login
│   ├── Dashboard/      # Role-specific dashboards (Admin, Trainer, Support, Student)
│   └── Ui/             # Toast notifications
├── Models/
│   └── User.php        # Authenticatable user with HasUuids, HasRoles, and academy membership helpers
└── Providers/
    └── AppServiceProvider.php  # TenantContext scoped binding, AiProviderInterface binding, Gate policies
```

### Standard Module Layout
Each module under `app/Domain/<Module>/` follows a consistent internal convention:
- `Models/` — Eloquent models using `HasUuids` and explicit typed relationships
- `Services/` — Domain services (e.g., `TenantContext`, `PrivateMediaService`, `AiAssistantService`, `DatabaseInstallerService`)
- `Policies/` — Laravel authorization policies registered in `AppServiceProvider::boot()`
- `Contracts/` & `Concerns/` — Interfaces and reusable traits (e.g., `AiProviderInterface`, `BelongsToAcademy`)
- `Actions/`, `Events/`, `Listeners/`, `Repositories/` — Scaffolded extension points for Phase 2+ workflows

### Thin Controllers & Domain Services
HTTP controllers and Livewire components handle only request validation, authorization checks, and response formatting, delegating core logic to domain services:
- `PrivateMediaController::show()` delegates authorization, path validation, and byte-range streaming to `PrivateMediaService::stream()`.
- `CourseStructureService` manages module/lesson CRUD, ordering, safe module deletion guards, lesson prerequisite cycle detection, and private lesson resource/material uploads.
- `EnrollmentManagementService` encapsulates single and bulk enrollment assignments, prerequisite evaluation, course capacity locking, lifecycle state transitions, expiry extensions, and automated notifications.
- `InstallController` delegates connection testing and environment/database setup to `DatabaseInstallerService`.
- `AppServiceProvider::register()` binds `TenantContext` as a request-scoped singleton (`$this->app->scoped(TenantContext::class)`) and resolves `AiProviderInterface` based on `config/lms.php`.

### Provider-Independent Lesson Content Architecture
Lessons (`App\Domain\Lessons\Models\Lesson`) decouple curriculum structure from any single media or video host:
- **Unified Lesson Types:** Supports `video`, `text`, `pdf`, `external`, `live_class`, and `assignment` within ordered `Module` containers (`all_lessons` or `required_count` completion rules).
- **Provider Abstraction:** `resource_provider` (`private_media`, `external_url`, `peertube`, `jitsi`, `bigbluebutton`, `internal`) and `resource_reference` allow academies to mix self-hosted private files with external or FOSS embeds without vendor lock-in.
- **Private Media & Materials:** Uploaded MP4/WebM videos and PDF `LessonMaterial` attachments are stored outside webroot on the `private_media` disk with random hex filenames and streamed via `GET /media/{media}` (`PrivateMediaService`), enforcing tenant isolation, course visibility, and free-preview rules.
- **Prerequisites & Progress Readiness:** `lesson_prerequisites` enforces same-course acyclic dependencies, while `lesson_progress` provides dual-engine progress tracking across SQLite and MySQL.

### Administrator-Controlled Enrollment Management (Phase 2.5)
Enrollment in SEO Academy is strictly administrator-controlled to preserve cohort isolation and customer licensing integrity:
- **No Self-Enrollment / Cart Checkout:** Students cannot self-enroll or purchase courses online; all enrollments originate from local academy administrators via `EnrollmentsIndex` (`/admin/enrollments`) or `EnrollmentManagementService`.
- **Lifecycle State Machine:** Enrollments traverse a deterministic 6-state transition graph (`pending`, `active`, `suspended`, `completed`, `expired`, `cancelled`). Inactive or cancelled enrollments require explicit administrator intervention to reactivate.
- **Strict Eligibility & Capacity Enforcement:**
  - Student account must be active (`is_active = true`) with the `student` role within the resolved single academy.
  - Course must be published (`status = 'Published'`) and non-private (`visibility != 'private'`).
  - Prerequisite courses must be satisfied (`status = 'completed'` or valid `active` enrollment).
  - Course `enrollment_limit` capacity is verified using pessimistic database locking (`lockForUpdate()`) to prevent race conditions during concurrent assignments.
- **Automated Expiry Processing:** Background console command `php artisan enrollments:process-expired` runs hourly to transition expired active enrollments to `expired` status and record `AuditLog` events.
- **Integrated Media Access Control:** `PrivateMediaService` checks that requesting students have a valid, active enrollment (`valid()`) for course-linked media, returning 403 Forbidden for suspended, expired, or cancelled enrollments while honoring `allowsFreePreview()` for public preview lessons.
- **Student My Courses Read-Only Surface:** `/student/courses` provides students with clear status visibility (`Active`, `Suspended`, `Expired`, `Completed`) and resume navigation, displaying an administrative contact prompt when no active enrollments exist without cart or checkout buttons.

### Trainer & Support Staff Operations (Phase 2.6)

Phase 2.6 establishes dedicated, privilege-separated workflows for Trainers and Support Staff within the single-academy model:

- **Trainer Workspace & Enrolled Students Isolation:**
  - `TrainerOperationService` aggregates live single-academy metrics: total assigned courses, published courses, active enrolled student count, and active cohorts.
  - `TrainerDashboard` (`/trainer/dashboard`) surfaces live metric counters and quick links to assigned curriculum and recent student activity.
  - `TrainerCoursesIndex` (`/trainer/courses`) filters courses via `assignedTrainer: $trainer`, preventing trainers from viewing unassigned courses. Unassigned course structure edits (`/trainer/courses/{course}/structure`) strictly return 403 Forbidden.
  - `TrainerStudentsIndex` (`/trainer/students`) provides read-only visibility into students enrolled in courses taught by the authenticated trainer. Includes search across name, email, and student code, as well as course and status filtering. Trainers possess zero administrative capabilities: no account creation, no enrollment changes, no account suspension controls, and strict exclusion from `/admin/*` routes.

- **Support Staff Desk & Ticketing Workflow:**
  - Extended `support_tickets` schema (`priority`, `assigned_to`, `assigned_at`, `resolved_at`, `closed_at`, `last_replied_at`) and new `support_ticket_messages` table (`is_internal` boolean flag with composite index `stm_ticket_internal_created_idx`).
  - `SupportTicket` state machine governs deterministic status transitions: `open -> in_progress -> waiting_for_response -> resolved -> closed`, with administrative reopen capabilities.
  - `SupportTicketService` manages ticket filtering (`listForStaff`), assignee assignment (`assignTicket`), status updates (`updateStatus`), public replies, and private internal notes (`addReply`).
  - `SupportDashboard` (`/support/dashboard`) displays real-time operational metrics: total tickets, open tickets, in-progress tickets, resolved today, and tickets assigned to the logged-in staff member.
  - `SupportTicketsIndex` (`/support/tickets`) provides a responsive support queue with search, priority badges (`low`, `medium`, `high`, `urgent`), status filters, and assignee views (`mine`, `unassigned`, `all`).
  - `SupportTicketDetail` (`/support/tickets/{ticket}`) provides conversation threads with staff assignment controls, status update dropdowns, public responses, and internal notes highlighted with distinctive styling.
  - Strict privacy boundary: internal notes (`is_internal = true`) are strictly stripped from student-facing queries via `scopePublicOnly` and are never visible to students.

- **Audit Trails & Cross-Role Notification Boundaries:**
  - All operational actions write structured audit events (`ticket.assigned`, `ticket.status_changed`, `ticket.reply_added`, `ticket.internal_note_added`) to `audit_log` with actor identity and payload metadata.
  - Targeted database notifications (`AcademyNotification`) alert staff on ticket assignments and notify students upon ticket status resolution and replies, with strict per-user visibility boundaries.
  - Role-based route guards ensure students and support staff cannot access trainer routes (`/trainer/*`), students cannot access support routes (`/support/*`), and support staff cannot access admin routes (`/admin/*`).

---

## 3. Database Architecture Summary

SEO Academy supports **SQLite** and **MySQL (InnoDB)** exclusively (PostgreSQL and SQL Server are out of scope). See [database-architecture.md](file:///C:/laragon\www\seo-academy\docs\architecture\database-architecture.md) for full operational details.

- **Dual-Engine Portability:** 61 migrations in `database/migrations/` use Laravel Schema Builder abstractions that execute cleanly on both SQLite 3.35+ and MySQL 8.0+.
- **UUID Primary Keys:** `App\Models\User` and domain models use `Illuminate\Database\Eloquent\Concerns\HasUuids` (`uuid('id')->primary()`).
- **SQLite Configuration (`config/database.php`):** Stored outside webroot at `storage/app/private/database.sqlite` with `WAL` journal mode, `busy_timeout = 5000`, `synchronous = NORMAL`, and `foreign_key_constraints = true`.
- **MySQL Configuration (`config/database.php`):** Enforces `InnoDB` engine, `utf8mb4` / `utf8mb4_unicode_ci`, `strict => true`, and `PDO::ATTR_EMULATE_PREPARES => false`.
- **Schema Safety Rules:**
  - Fractional/monetary values use `decimal(...)` instead of `float`/`double`.
  - `text`, `mediumText`, `longText`, and `json` columns never define `->default(...)` (required for MySQL strict mode).
  - Composite keys and indexes remain well within InnoDB's 3072-byte `utf8mb4` key limit.

---

## 4. Design System Foundation

The UI layer is built with **Blade**, **Livewire 4**, **Tailwind CSS 4**, and **Alpine.js**, supporting light, dark, and system-preference themes without Flash of Unstyled Content (FOUC).

- **Theme System (`resources/css/app.css` & `resources/views/layouts/app.blade.php`):**
  - Uses Tailwind v4 `@custom-variant dark (&:where(.dark, .dark *));` and `@theme` mappings backed by CSS custom properties (`--color-surface-bg`, `--color-surface-card`, `--color-text-primary`, `--color-primary-*`, etc.) in `:root` and `.dark`.
  - An inline `<head>` script in `app.blade.php` reads `localStorage.getItem('seo_academy_theme')` (defaulting to `'system'`) and toggles `.dark` / `.light` on `<html>` before first paint.
- **Layouts (`resources/views/layouts/`):**
  - `app.blade.php` — Authenticated application shell with responsive off-canvas mobile sidebar (`md:hidden`), collapsible desktop rail (`$store.sidebar.collapsed`), sticky header, breadcrumbs slot, and global toast container.
  - `auth.blade.php` & `guest.blade.php` — Unauthenticated and installer shells.
- **Reusable Blade Components (`resources/views/components/`):**
  23 UI primitives: `alert`, `badge`, `breadcrumbs`, `button`, `card`, `checkbox`, `confirm-dialog`, `empty-state`, `form-section`, `header`, `input`, `loading-spinner`, `modal`, `page-header`, `select`, `sidebar`, `stat-card`, `table`, `table-cell`, `table-row`, `textarea`, `toast-container`, and `user-menu`.
