# Database Architecture — SEO Academy

SEO Academy is independently installed and operated by each customer. One installation supports exactly one academy.

## 1. Supported Engines

SEO Academy officially supports **two** relational database engines:

| Engine | Role | Minimum Version |
|---|---|---|
| **SQLite** | Default for simple installations, local development, and small single-server deployments | SQLite 3.35+ (with `pdo_sqlite`) |
| **MySQL** (InnoDB) | Recommended for production, multi-trainer/high-concurrency academies, and larger datasets | MySQL 8.0+ (with `pdo_mysql`) |

> **Out of Scope:** PostgreSQL (`pgsql`), SQL Server (`sqlsrv`), and other drivers are intentionally excluded from `config/database.php` and `.env.example`.

---

## 2. Engine Selection Guidance

- **Use MySQL (InnoDB)** for production deployments, multi-worker queue processing, concurrent student/trainer activity, and academies expecting growth beyond a single-server setup.
- **Use SQLite** for zero-dependency local development, automated testing (`:memory:`), demos, and small single-server installations with modest write concurrency.

---

## 3. SQLite Configuration & Operational Considerations

Configured in `config/database.php` under `connections.sqlite`:

- **Storage Location:** `storage/app/private/database.sqlite` (via `storage_path('app/private/database.sqlite')`), kept **outside `public/`** so the database file can never be downloaded over HTTP.
- **Private Storage Protection:** `storage/app/private/.gitignore` excludes database files (`database.sqlite`, `-wal`, `-shm`) from version control, and `storage/app/private/.htaccess` denies direct Apache access as defense in depth.
- **File Permissions:** Ensure the web/PHP process user owns `storage/app/private/` and has read/write permissions on both the directory and the `.sqlite` file (SQLite creates `-wal` and `-shm` sidecar files in the same directory). On Linux, `0750` on the directory and `0640` on the file is recommended.
- **Concurrency & Locking:**
  - `journal_mode => 'WAL'` (Write-Ahead Logging) allows concurrent readers alongside a single writer.
  - `busy_timeout => 5000` (5 seconds) prevents immediate `SQLITE_BUSY` errors under brief write contention.
  - `foreign_key_constraints => true` enforces referential integrity (`PRAGMA foreign_keys = ON`).
  - `synchronous => 'NORMAL'` balances durability and write throughput in WAL mode.
- **Single-Server Limitation:** SQLite relies on local POSIX/Windows file locking; do **not** place `database.sqlite` on NFS/SMB network shares or share it across multiple application servers.
- **Safe Backup Strategy:** Never copy `database.sqlite` with raw `cp` while the application is writing, as this can produce a corrupt or inconsistent snapshot with uncheckpointed `-wal` pages. Use SQLite's online backup API or `VACUUM INTO`:
  ```bash
  sqlite3 storage/app/private/database.sqlite ".backup '/path/to/backups/seo-academy-$(date +%F).sqlite'"
  # Or via SQL:
  sqlite3 storage/app/private/database.sqlite "VACUUM INTO '/path/to/backups/seo-academy-$(date +%F).sqlite';"
  ```

---

## 4. MySQL Configuration & Operational Considerations

Configured in `config/database.php` under `connections.mysql`:

- **Supported Versions:** MySQL 8.0+ (verified on MySQL 8.4.3).
- **Storage Engine:** `engine => 'InnoDB'` (ACID transactions, row-level locking, and foreign key enforcement).
- **Character Set & Collation:** `utf8mb4` / `utf8mb4_unicode_ci` for full Unicode support (including 4-byte emoji/multilingual text).
- **Strict Mode & Prepared Statements:**
  - `strict => true` enforces strict SQL mode (`STRICT_TRANS_TABLES`, etc.).
  - `options => [PDO::ATTR_EMULATE_PREPARES => false]` uses native server-side prepared statements for type fidelity and security.
- **Schema Compatibility Rules:**
  - All 61 migrations use Laravel Schema Builder abstractions (no PostgreSQL-specific SQL).
  - Primary keys use UUIDs (`uuid('id')->primary()`) or standard integer keys; composite indexes/PKs stay well within InnoDB's 3072-byte `utf8mb4` key limit.
  - `text`, `mediumText`, `longText`, and `json` columns never define `->default(...)` (forbidden in MySQL strict mode).
  - Fractional/monetary values use `decimal(...)` rather than `float`/`double`.
- **Environment Configuration (`.env`):**
  ```env
  DB_CONNECTION=mysql
  DB_HOST=127.0.0.1
  DB_PORT=3306
  DB_DATABASE=seo_academy
  DB_USERNAME=your_db_user
  DB_PASSWORD=your_db_password
  ```
- **Recommended Backup & Restore (`mysqldump`):**
  Use `--single-transaction` for a consistent point-in-time InnoDB snapshot without locking tables:
  ```bash
  mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 \
    -u your_db_user -p seo_academy > seo-academy-$(date +%F).sql
  ```
  Restore:
  ```bash
  mysql --default-character-set=utf8mb4 -u your_db_user -p seo_academy < seo-academy-YYYY-MM-DD.sql
  ```

---

## 5. Phase 2.5 Enrollment Schema & Concurrency

The `enrollments` table manages student course access with strict lifecycle controls and concurrency guarantees across both SQLite and MySQL:

- **Additive Migration (`2026_11_12_190000_update_enrollments_table_for_phase25.php`):**
  - `academy_id` (`char(36)`, foreign UUID to `academies` with `cascadeOnDelete()`) ensures multi-tenant data hygiene.
  - `assigned_by` (`char(36)`, nullable foreign UUID to `users` with `nullOnDelete()`) records the acting administrator.
  - `starts_at` & `expires_at` (`datetime`, nullable): defines enrollment validity window; `expires_at` is indexed for performant hourly scanning by `enrollments:process-expired`.
  - `status` (`varchar(32)`, default `'pending'`, indexed): tracks active lifecycle state (`pending`, `active`, `suspended`, `completed`, `expired`, `cancelled`).
  - Transition metadata columns: `cancelled_at`, `cancellation_reason`, `suspended_at`, `suspension_reason`, and `completed_at` provide audit fidelity directly on the record.
  - `notes` (`text`, nullable, no default): administrative annotations.
- **Pessimistic Concurrency Locking:**
  - Courses with an `enrollment_limit` use `Enrollment::query()->where('course_id', $course->id)->whereIn('status', ['active', 'pending'])->lockForUpdate()->count()` inside a database transaction (`DB::transaction`).
  - In MySQL (InnoDB), this applies row-level shared/exclusive locks on existing enrollment rows to prevent over-enrollment race conditions under high concurrent administrative batch operations. In SQLite, the enclosing write transaction locks the database file safely.
- **Audit Log Integration:**
  - Status transitions (`enrollment.status_changed`), assignments (`enrollment.assigned`, `enrollment.bulk_assigned`), and date extensions (`enrollment.dates_updated`) write structured event snapshots into `audit_log` with the administrator's `user_id`, before/after states, and client IP.

---

## 6. Phase 2.6 Support Operations & Messages Schema

Phase 2.6 extends support ticket tracking and introduces threaded communication for support staff and students:

- **Additive Migration (`2026_11_13_120000_update_support_tickets_for_phase26.php`):**
  - `priority` (`varchar(32)`, default `'medium'`, indexed): categorized as `low`, `medium`, `high`, `urgent`.
  - `assigned_to` (`char(36)`, nullable foreign UUID to `users` with `nullOnDelete()`): designates the staff member responsible for ticket handling.
  - `assigned_at`, `resolved_at`, `closed_at`, `last_replied_at` (`datetime`, nullable): timestamps tracking full ticket lifecycle and response SLA metrics.
  - Normalized legacy status values (`'Pending'` -> `'open'`, `'In Progress'` -> `'in_progress'`, `'Resolved'` -> `'resolved'`).
- **Threaded Messages Schema (`support_ticket_messages` table):**
  - `id` (`char(36)`, UUID primary key via `HasUuids`).
  - `support_ticket_id` (`char(36)`, foreign UUID referencing `support_tickets` with `cascadeOnDelete()`).
  - `user_id` (`char(36)`, foreign UUID referencing `users` with `cascadeOnDelete()`).
  - `message` (`text`, nullable: false, no default, strictly compatible with MySQL strict mode).
  - `is_internal` (`boolean`, default `false`): distinguishes public student-facing replies from staff-only notes.
  - Timestamps: `created_at` and `updated_at`.
  - **Index Design:** Composite index `['support_ticket_id', 'is_internal', 'created_at']` with explicit custom constraint name `stm_ticket_internal_created_idx` to stay well under MySQL's 64-character identifier limitation.
- **Privacy Enforcement & Dual-Engine Reliability:**
  - `SupportTicketMessage::scopePublicOnly()` explicitly enforces `where('is_internal', false)` to guarantee internal staff annotations never leak into student-facing query results.
  - Schema operates idempotently and commits/rolls back cleanly across SQLite (:memory: and WAL file) and MySQL 8.0+ (InnoDB) without foreign key deadlock or DDL truncation.
