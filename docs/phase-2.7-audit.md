# SEO Academy — Phase 2.7 Audit Report
**LMS Operational Foundation: Comprehensive System Audit**

**Date:** 2026-11-13  
**Target:** `C:\laragon\www\seo-academy`  
**Scope:** Phase 2.7 Modules (Notifications, Private Media, Centralized Audit Logging, System Settings & Health Monitoring, Backup & Recovery, Production Readiness)

---

## 1. Executive Summary

SEO Academy is a commercial, self-hosted LMS designed for independent installation by customers where **one installation represents exactly one academy**. Phases 2.1 through 2.6 established the core single-academy multi-role foundation (Admin, Trainer, Support Staff, Student), administrator-controlled enrollments, course structure editor, trainer workflows, support desk operations, and strict support message retention.

Phase 2.7 focuses on completing the operational foundation required for reliable, secure, manageable, and shared-hosting-compatible software. This audit evaluates existing capabilities, identifies reusable components, flags security and compatibility gaps, and outlines the precise implementation roadmap for Phase 2.7.

---

## 2. Detailed Module Audits

### 2.1 Module A — Centralized Notification Management

#### Existing Functionality
- `notifications` table (`2026_01_01_000110_create_notifications_table.php`) exists with UUID morphs (`notifiable_type`, `notifiable_id`), data JSON payload, and timestamps.
- `academy_notifications` table (`2026_01_01_000111_create_academy_notifications_table.php`) exists with fields: `id`, `academy_id`, `audience`, `target`, `category`, `title`, `message`, `created_by`, `created_at`.
- `notification_reads` table (`2026_01_01_000112_create_notification_reads_table.php`) exists with composite primary key `[notification_id, user_id]` and `read_at`.
- `User` model uses `Illuminate\Notifications\Notifiable`.
- Events in `EnrollmentManagementService`, `SupportTicketService`, and `ProcessExpiredEnrollments` emit records into `academy_notifications`.
- `resources/views/components/header.blade.php` contains a static mockup of a notification bell ("All caught up", "No new notifications at the moment.").

#### Existing Reusable Components
- `App\Domain\Notifications\Models\AcademyNotification`
- `App\Domain\Notifications\Models\NotificationRead`
- `User::notifications()` via `Notifiable`

#### Missing Functionality
- No Notification Center UI (viewing notifications list, unread indicators, mark-as-read, mark-all-as-read, pagination, filtering by category).
- No reactive unread notification count badge in application header.
- No user-scoped notification preferences schema, model, or UI (email vs. in-app preferences per category).
- No automated email delivery integration for notifications.
- No administrator SMTP connection testing tool.
- No notification deduplication helper or event identifier tracking for non-enrollment notifications.

#### Security & Compatibility Assessment
- **Security Issue (High):** Header bell mockup is static. Any new notification queries must be strictly scoped to the authenticated user's ID (`target = user.id` or user's role audience). Never allow one user to view or mark read another user's notifications.
- **Security Issue (Medium):** SMTP passwords must never be stored in plaintext in database or exposed in Livewire state or logs.
- **Compatibility Issue (Shared-Hosting):** Queued notification mail must support `sync` or `database` queues without requiring Redis or permanent supervisor workers.

#### Findings Classification
- `notifications` schema: **Already implemented and acceptable**
- `AcademyNotification` & `NotificationRead` models: **Implemented but needs improvement** (needs helper methods for read/unread scoping, event linking)
- Notification Center UI: **Missing**
- Notification Preferences: **Missing**
- Configurable SMTP Mail Delivery & Connection Tester: **Missing**
- Notification Deduplication: **Implemented but needs improvement** (partially in `ProcessExpiredEnrollments`, missing elsewhere)

---

### 2.2 Module B — Private File and Media Management

#### Existing Functionality
- `media` table (`2026_01_01_000160_create_media_table.php`) tracks `model_type`, `model_id`, `collection`, `disk`, `path`, `filename`, `mime_type`, `size_bytes`, `metadata`.
- `App\Domain\Platform\Models\Media` model.
- `PrivateMediaService` (`app/Domain/Video/Services/PrivateMediaService.php`) handles streaming, byte-range chunking, and strict authorization for course lessons, lesson materials, class videos, and assignment files.
- `PrivateMediaController` (`/media/{media}`) verifies user authentication and delegates to `PrivateMediaService`.
- Disks configured: `local` (`storage/app/private`), `private_media` (`storage/app/private/media`), `private_backups` (`storage/app/private/backups`), `public` (`storage/app/public`).

#### Existing Reusable Components
- `App\Domain\Platform\Models\Media`
- `App\Domain\Video\Services\PrivateMediaService`
- `PrivateMediaController`
- Storage disks in `config/filesystems.php`

#### Missing Functionality
- Centralized File Management administrative interface (`/admin/files`) allowing administrators to inspect, search, filter (by type, disk, uploader, date), and view file metadata.
- Secure upload validation service (MIME verification, file content inspection, randomized storage paths, path traversal prevention, disallowing PHP/executable uploads).
- Storage usage reporting dashboard component (total managed storage, breakdown by category/course, file count, free disk space detection where supported).

#### Security & Compatibility Assessment
- **Security Issue (High):** File uploads must strictly reject executable extensions (`.php`, `.phtml`, `.phar`, `.sh`, `.exe`, `.cgi`) and MIME spoofing.
- **Security Issue (High):** Public download links must never be generated for private media; all access must continue going through `PrivateMediaController` and `PrivateMediaService`.
- **Compatibility Issue (Shared-Hosting):** Filesystem storage must default to private local disk without assuming AWS S3 is configured.

#### Findings Classification
- `PrivateMediaService` authorization & streaming: **Already implemented and acceptable**
- `Media` model & database table: **Already implemented and acceptable**
- Centralized File Management UI (`/admin/files`): **Missing**
- Secure Upload Validation Service: **Missing**
- Storage Usage Reporting: **Missing**

---

### 2.3 Module C — Centralized Audit Logging

#### Existing Functionality
- `audit_log` table (`2026_01_01_000150_create_audit_log_table.php`) with fields: `id` (UUID PK), `user_id` (foreign key `users(id)->nullOnDelete()`), `action`, `subject_type`, `subject_id`, `properties` (JSON), `ip_address`, `user_agent`, `created_at`.
- `App\Domain\Platform\Models\AuditLog` model.
- `RecordsAuditLog` trait (`app/Domain/Platform/Concerns/RecordsAuditLog.php`) used across enrollment management, expiry processing, and support ticket services.

#### Existing Reusable Components
- `App\Domain\Platform\Models\AuditLog`
- `App\Domain\Platform\Concerns\RecordsAuditLog`

#### Missing Functionality
- Administrator Audit Log Viewer (`/admin/audit-logs`) with search, pagination, date range filtering, actor filtering, action filtering, and module filtering.
- Before/after property change serializer with strict sensitive field exclusions (passwords, tokens, keys, SMTP credentials).
- Actor name snapshotting in `properties` so actor identity is preserved even if the user account is later deleted (`user_id = NULL`).
- Protection against non-admin access (no student, trainer, or support_staff can view or query audit records).
- Immutability guarantee (no update or delete actions exposed in UI or policies).

#### Security & Compatibility Assessment
- **Security Issue (Critical):** Audit records must never contain passwords, hashes, session IDs, or SMTP secrets.
- **Security Issue (High):** Audit logs must be viewable only by `admin`.
- **Compatibility Issue:** JSON querying across SQLite and MySQL must use portable Eloquent/JSON queries.

#### Findings Classification
- `audit_log` schema: **Already implemented and acceptable**
- `RecordsAuditLog` trait: **Implemented but needs improvement** (needs sensitive field filtering and actor name snapshotting)
- Administrator Audit Log Viewer: **Missing**
- Audit Immutability Policy: **Missing**

---

### 2.4 Module D — System Settings and Health Monitoring

#### Existing Functionality
- `settings` table (`2026_11_12_120005_create_settings_table.php`) with `academy_id`, `group`, `key`, `value` (text), `type`, `is_public`, and unique constraint `[academy_id, group, key]`.
- `App\Domain\Platform\Models\Setting` model.
- `AcademySettings` Livewire component for branding and profile.
- Configuration files for database, mail, filesystems, and queues.

#### Existing Reusable Components
- `App\Domain\Platform\Models\Setting`
- Base UI design patterns in `AcademySettings`

#### Missing Functionality
- System Health Dashboard (`/admin/system/health`) displaying truthful metrics:
  - Application info (PHP version, Laravel version, environment, debug mode, maintenance mode, app URL).
  - Database connectivity (driver, status ping, migration count, pending migrations).
  - Storage health (default disk, private media write check, backup disk write check, managed file volume, free disk space).
  - Queue status (driver, pending database jobs, failed jobs).
  - Scheduler health (last run timestamp from sentinel file/setting, cron setup instructions for shared hosting).
  - PHP requirements verification (extensions: pdo, pdo_sqlite/pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, fileinfo, curl, zip).
- Secure SMTP Settings Interface (`/admin/system/settings` or dedicated tab) with encrypted password storage, masked display, connection tester, and test email dispatcher.

#### Security & Compatibility Assessment
- **Security Issue (Critical):** Never allow arbitrary editing of `.env` file from the browser.
- **Security Issue (High):** SMTP passwords must use Laravel's `'encrypted'` cast or `Crypt::encryptString()` when persisted in `settings`. Never send plain secrets to Livewire frontend.
- **Truthful Status:** Clearly indicate when scheduler or queue status is "Not Configured" or "Unable to Determine" rather than faking health.

#### Findings Classification
- `settings` table & `Setting` model: **Already implemented and acceptable**
- System Health Dashboard: **Missing**
- Secure SMTP Settings & Connection Tester: **Missing**
- Scheduler Sentinel & Diagnostic Service: **Missing**

---

### 2.5 Module E — Backup and Recovery

#### Existing Functionality
- CLI/Manual documentation in `docs/backup-restore.md`.
- `private_backups` disk configured in `config/filesystems.php` (`storage/app/private/backups`).
- `.htaccess` in `storage/app/private/` prevents direct HTTP access on Apache.

#### Existing Reusable Components
- Disk configuration for `private_backups`.
- Architectural guidance in `docs/backup-restore.md`.

#### Missing Functionality
- Database schema for tracking backups (`backups` table: ID, filename, disk, type, driver, size_bytes, status, validation_result, created_at).
- `BackupService` with:
  - SQLite consistent snapshot (using `VACUUM INTO` or online backup, respecting WAL mode, running `PRAGMA integrity_check`).
  - MySQL InnoDB streaming backup (using pure PHP PDO streaming export to avoid memory bloat and eliminate external `mysqldump` CLI dependencies on shared hosting).
  - Private media file archive (creating `.zip` of `storage/app/private/media`, excluding caches, logs, sessions, and `.env`).
  - Archive integrity verification.
  - Configurable retention policy (deletes old backups, never deletes a backup currently creating/restoring, never deletes the last known-good backup).
  - Safe restoration workflow with pre-restore safety snapshot, maintenance mode toggle, driver validation, archive traversal prevention, and post-restore integrity check.
- Administrative UI (`/admin/system/backups`) for initiating backups, inspecting history, downloading securely, and restoring with strict confirmation.

#### Security & Compatibility Assessment
- **Security Issue (Critical):** Backups must never be stored in `public/` or downloaded via public static URLs.
- **Security Issue (Critical):** Restoration must be restricted to `admin` only and protected against path traversal (`../`) in zip archives.
- **Compatibility Issue (Shared-Hosting):** Shared hosting environments frequently lack `mysqldump` or `exec()` permissions. The MySQL backup solution must provide a reliable pure-PHP PDO streaming fallback.

#### Findings Classification
- Storage disk & `.htaccess`: **Already implemented and acceptable**
- `BackupService` (SQLite, MySQL, Files, Verification): **Missing**
- Backup Metadata Schema & Model: **Missing**
- Backup Restoration Engine & Safety Checks: **Missing**
- Administrative Backup Management UI: **Missing**

---

### 2.6 Module F — Production Readiness & Shared-Hosting Compatibility

#### Existing Functionality
- Standard Laravel 13 directory layout with `public/index.php`.
- Precompiled Vite assets in `public/build`.
- CSRF middleware and authentication guards.
- Dual-engine compatibility test suite (`DatabaseCompatibilityTest`).

#### Existing Reusable Components
- Vite build setup (`npm run build`).
- `DatabaseCompatibilityTest` provider pattern.

#### Missing Functionality
- Production security and permission checklist documentation (`docs/deployment/production-security.md`).
- Shared-hosting cron and deployment guide (`docs/deployment/shared-hosting.md`).
- Structured logging configuration avoiding secret disclosure.
- Dedicated operational documentation for Notifications, Files, Audit, Health, and Backups.

#### Findings Classification
- Asset compilation & build: **Already implemented and acceptable**
- Dual-engine testing: **Already implemented and acceptable**
- Shared-hosting deployment docs: **Implemented but needs improvement**
- Dedicated operational docs: **Missing**

---

## 3. Required Migrations & Database Changes

All changes must be strictly additive and reversible:

1. **`2026_11_13_150000_create_notification_preferences_table.php`**
   - User-level notification preferences (in-app vs email per category: `enrollment`, `support`, `course`, `system`).
   - Foreign key: `user_id` -> `users(id)->cascadeOnDelete()`.
   - Index on `user_id`.

2. **`2026_11_13_160000_create_backups_table.php`**
   - Tracks backup records: `id` (UUID), `filename`, `disk`, `type` (`database`, `files`, `full`), `database_driver` (`sqlite`, `mysql`), `size_bytes`, `status` (`completed`, `failed`), `checksum`, `metadata` (JSON), `created_at`.
   - Indexes on `created_at` and `status`.

No destructive modifications to existing tables. `audit_log`, `media`, `settings`, and `academy_notifications` tables are already structured well and will be reused without disruptive schema changes.

---

## 4. Proposed Implementation Architecture

```
app/
├── Domain/
│   ├── Notifications/
│   │   ├── Models/
│   │   │   ├── AcademyNotification.php (extend with helpers)
│   │   │   ├── NotificationRead.php
│   │   │   └── NotificationPreference.php (NEW)
│   │   ├── Services/
│   │   │   ├── NotificationDispatcherService.php (NEW)
│   │   │   └── SmtpTransportService.php (NEW)
│   │   └── Mail/
│   │       ├── AcademyNotificationMail.php (NEW)
│   │       └── SmtpTestMail.php (NEW)
│   ├── Platform/
│   │   ├── Models/
│   │   │   ├── AuditLog.php (extend)
│   │   │   ├── Media.php
│   │   │   ├── Setting.php
│   │   │   └── BackupRecord.php (NEW)
│   │   ├── Services/
│   │   │   ├── AuditLogService.php (NEW - serializer & queries)
│   │   │   ├── SystemHealthService.php (NEW)
│   │   │   ├── FileManagementService.php (NEW - upload validation & storage metrics)
│   │   │   └── BackupService.php (NEW - SQLite/MySQL/Files/Restore)
│   │   └── Concerns/
│   │       └── RecordsAuditLog.php (extend with actor snapshot & sensitive filtering)
├── Http/
│   └── Controllers/
│       ├── PrivateMediaController.php (preserve)
│       └── BackupDownloadController.php (NEW - secure admin-only stream)
└── Livewire/
    ├── Notifications/
    │   ├── NotificationCenter.php (NEW - user in-app notifications)
    │   └── NotificationPreferences.php (NEW)
    └── Admin/
        ├── FileManagementIndex.php (NEW)
        ├── AuditLogsIndex.php (NEW)
        ├── SystemHealthDashboard.php (NEW)
        ├── SystemSettingsIndex.php (NEW - SMTP & General)
        └── BackupsIndex.php (NEW - create, view, restore, retention)
```

---

## 5. Testing Plan

### 5.1 Unit and Feature Tests Required
1. **`tests/Feature/Operations/NotificationCenterTest.php`**
   - User isolation (student cannot see admin or other student notifications).
   - Read/unread state transitions, mark single read, mark all read.
   - Preference enforcement (disabling email prevents email dispatch).
   - Duplicate prevention.
   - SMTP failure handling does not crash parent transaction.

2. **`tests/Feature/Operations/FileManagementTest.php`**
   - Upload validation: valid PDF/images pass, PHP scripts and `.phtml` are rejected.
   - MIME spoofing prevention.
   - Storage metrics calculation.
   - Admin file list inspection, search, and pagination.
   - Non-admin authorization blocks (students/trainers cannot access `/admin/files`).

3. **`tests/Feature/Operations/AuditLoggingTest.php`**
   - Event creation and actor attribution.
   - Sensitive field redaction (`password`, `token`, `secret`, `smtp_password` never logged).
   - Actor metadata preserved when actor user is deleted (`nullOnDelete`).
   - Immutability: regular users cannot view, edit, or delete audit records.
   - Admin filtering by date, actor, action, and module.

4. **`tests/Feature/Operations/SystemHealthTest.php`**
   - Truthful reporting of PHP, Laravel, DB, Queue, Scheduler, Storage.
   - SMTP settings persistence with encryption at rest.
   - Secret masking in UI state.
   - Non-admin access rejected (403).

5. **`tests/Feature/Operations/BackupAndRecoveryTest.php`**
   - SQLite backup creation (`VACUUM INTO`), validation (`PRAGMA integrity_check`), and restore on disposable DB.
   - MySQL InnoDB streaming backup and restore on disposable test DB (`seo_academy_test`).
   - Private media file archiving and traversal protection.
   - Retention policy protects recent backups and never deletes the only good backup.
   - Restore aborts safely on invalid checksums or mismatched database drivers.
   - Non-admin access rejected.

6. **Dual Engine & Regression Testing**
   - `tests/Feature/DatabaseCompatibilityTest.php` updated to assert 65 migrations run and rollback cleanly on both SQLite and MySQL.
   - Full application suite verification (`php artisan test`).
   - Frontend Vite build (`npm run build`).

---

## 6. Audit Conclusion & Next Steps
The existing codebase provides strong foundations in `PrivateMediaService`, `AuditLog`, `AcademyNotification`, and `Setting`. Phase 2.7 will unify and build on these foundations with minimal friction, zero breaking changes to existing data, full shared-hosting support, and complete dual-engine database reliability.

We proceed immediately with Step 2 (Notifications) of the approved implementation plan.

