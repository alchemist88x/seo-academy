# Operational Guide — Backups & Recovery

SEO Academy provides robust, self-hosted disaster recovery, database snapshots, and private file backup capabilities.

---

## 1. Backup Dashboard & Capabilities
- Accessible exclusively to administrators at `/admin/system/backups`.
- Displays completed backup archives with filenames, backup sets, storage disks, database drivers, file sizes, creation timestamps, and SHA-256 integrity checksums.
- **Actions:**
  - **Create Full Backup Set:** Generates an atomic pair of database snapshot and private media archive linked by a shared `backup_set_id`.
  - **Backup DB Only:** Generates a database snapshot (SQLite VACUUM INTO or MySQL InnoDB consistent export).
  - **Backup Files Only:** Generates a `.zip` archive of `storage/app/private/media`.
  - **Validate:** Verifies file existence, verifies SHA-256 checksum against record, runs SQLite `PRAGMA integrity_check` & `PRAGMA foreign_key_check`, verifies MySQL header/footer completion, or validates ZipArchive consistency and file paths.
  - **Download:** Streams the archive to the administrator securely via `/admin/system/backups/{backup}/download`.
  - **Restore:** Initiates an exclusive, locked restoration procedure with application-wide maintenance mode and pre-restore safety snapshots.

---

## 2. Backup Mechanisms & Technical Architecture

### MySQL InnoDB Consistent Snapshot
- Avoids external `mysqldump` CLI dependencies, operating within pure PHP streaming buffers.
- Guarantees consistent point-in-time snapshots across all tables using:
  ```sql
  SET TRANSACTION ISOLATION LEVEL REPEATABLE READ;
  START TRANSACTION WITH CONSISTENT SNAPSHOT;
  ```
- **Strict Table Engine Compatibility:** Before starting the dump, `information_schema.TABLES` is inspected. If non-transactional table engines (e.g. MyISAM or MEMORY) are detected, **the backup is rejected immediately** (`RuntimeException`), preventing partial or inconsistent snapshots.
- **Single Connection Lifecycle:** The exact same PDO connection instance is held open for the transaction, table discoveries, and all streaming table cursors until explicit `COMMIT;`.
- **Data Literal Preservation & Escaping:** 
  - All identifiers are quoted (` ```` `).
  - Explicit column type mapping is performed (`SHOW COLUMNS`): binary/blob columns are hex-encoded (`0x...`), numeric/decimal columns are output verbatim, `NULL` values are emitted as `NULL` literals, and strings/JSON are quoted via PDO `$pdo->quote()`.
- **Memory Safety & Streaming Buffering:** Rows are streamed using buffered 500-row multi-value inserts, preventing PHP memory exhaustion on large datasets.
- **Atomic Cleanup on Failure:** If an exception occurs at any point, the transaction is rolled back (`ROLLBACK;`), the file handle is closed, and any partially written file on disk is immediately unlinked (`@unlink($targetPath)`). Failed backups are never marked `completed`.

### SQLite Zero-Downtime Backup
- Creates a consistent snapshot using SQLite `VACUUM INTO 'target.sqlite'`, creating a single defragmented file that automatically incorporates all uncheckpointed WAL pages.
- Fallback streaming exporter available for in-memory or active transaction environments.
- Automatically verifies SQLite file health via `PRAGMA integrity_check` and `PRAGMA foreign_key_check` before marking backup as completed.

### Private Media Archives
- Recursively compresses `storage/app/private/media/` into private zip archives outside public web roots.
- Sanitizes file paths, rejecting directory traversal (`..`), absolute paths, null bytes, and drive letters.

---

## 3. Restoration Safety & Disaster Recovery Protocol

1. **Path Traversal & Archive Integrity Guards:**
   - Filenames are strictly validated using `basename()` and checked for `..` sequences to prevent arbitrary file overwrites.
   - Incoming archives are pre-validated before any live system or storage directory is modified.
2. **Application-Wide Write & Concurrency Exclusion:**
   - When restoration begins, the system immediately puts the application into **maintenance mode** (`artisan down --status=503 --retry=60`), writing `storage/framework/down`.
   - Normal incoming web requests receive an immediate HTTP 503 response.
   - The restore-state middleware (`PreventWritesDuringRestore`) blocks any concurrent state-modifying requests (`POST`, `PUT`, `PATCH`, `DELETE`).
   - The scheduler and scheduled commands (e.g. `enrollments:process-expired`) check `BackupService::isRestorationInProgress()` and halt write activity during active restore locks.
   - Restorations acquire an exclusive non-blocking file lock on `storage/framework/backup_restore.lock` (`flock(LOCK_EX | LOCK_NB)`). Any concurrent restore requests fail immediately.
   - Upon completion (or failure), maintenance mode is lifted (`artisan up`) and the file lock is released in a `finally` block.
3. **Database Driver Compatibility Check:**
   - Restoring a backup created under MySQL onto an SQLite installation (or vice-versa) is immediately rejected.
4. **Automatic Safety Snapshot & Atomic Rollback:**
   - Before modifying any live database or media folder, an automated pre-restore safety snapshot is created.
   - For SQLite, the active database file is backed up to a temporary rollback file. If the newly copied database fails post-restoration `PRAGMA integrity_check` or `PRAGMA foreign_key_check`, the original database is automatically restored.
5. **Two-Phase Complete Set Recovery (`restoreBackupSet`) with Dual Rollback:**
   - Validates both database and file archives upfront.
   - Creates safety backups for both database and media before restoring either.
   - Creates a staging safety directory copy of `storage/app/private/media`.
   - Media restoration extracts into a temporary staging folder (`storage/app/private/media_staging_*`) to verify archive structure before modifying the live media folder.
   - **Dual Rollback Invariant:** If EITHER the database or media restoration encounters an error, **BOTH** components are restored to their pre-restoration safety snapshots, verified, and logged to `audit_log`.
6. **Sidecar Purging:**
   - Active SQLite connections are purged via `DB::purge('sqlite')`, and stale `-wal` and `-shm` sidecars are deleted before replacement.

---

## 4. Retention Policy & Safety Guarantees
- Retains the most recent N backups (default: 5).
- **Logical Recovery Point Invariant:** Backups sharing a `backup_set_id` are tracked as unified recovery sets. If complete backup sets exist, retention will never delete the last valid complete set.
- **Absolute Safety Invariant:** Never deletes the only remaining completed backup, guaranteeing that a recovery point always exists.
- Protects backups currently being restored or locked from pruning.
- Securely unlinks physical disk files upon record deletion.
- Failed cleanups are logged to the system error log.

---

## 5. Supported Schema Objects & Exclusions

### What IS Restored:
- All base database tables and table structures.
- Primary keys, foreign key constraints, unique constraints, and indexes.
- Column types: integers, decimals, floats, strings, text, timestamps, and JSON data.
- Nullability (`NULL` vs `NOT NULL`) and column defaults.
- Historical data snapshots, including nullable `user_id` on support messages with preserved `author_name`.
- Hex-encoded binary and blob contents.

### Explicit Exclusions (Not Supported by Exporter):
- Stored procedures, functions, database triggers, and database-level event schedulers are **not** exported or restored. (SEO Academy relies on Laravel migrations and scheduler commands, not database triggers or stored procedures).
- Database users, passwords, and server-level privilege grants (`GRANT ...`) are not included in backups.

---

## 6. Manual Recovery from Interrupted Restoration

If an unexpected process termination (e.g., hard power outage or OS SIGKILL) leaves the application locked:

1. **Verify Process State:** Ensure no active PHP process is running a restoration task.
2. **Release Restore Lock:** Delete the file `storage/framework/backup_restore.lock` if it exists.
3. **Lift Maintenance Mode:** Run `php artisan up` from the terminal (or delete `storage/framework/down`).
4. **Locate Safety Backups:** Pre-restore safety backups are stored in `storage/app/private/backups/` and can be inspected via `php artisan tinker` or the administrator dashboard.
5. **Verify Database Integrity:** Run `PRAGMA integrity_check` on SQLite or verify MySQL connectivity to confirm the active database is operational.

---

## 7. Encryption Key (`APP_KEY`) Continuity & Key Rotation

### 7.1 Mandatory Backup of `APP_KEY`
The application key (`APP_KEY` in `.env`) is the root secret for authenticated symmetric encryption (`AES-256-CBC` via `Illuminate\Support\Facades\Crypt`). The following data are encrypted at rest with this key:
- **SMTP credentials and mail passwords** stored in the `settings` table.
- **Certificate verification tokens** (`encrypted_verification_code`) stored in the `certificates` table.
- **Session cookies and encrypted payload tokens**.

**Crucial Invariant:** Always store a secure offline backup of `.env` (specifically `APP_KEY`) alongside database and media backup archives. Restoring a database snapshot onto an instance with a mismatched or regenerated `APP_KEY` will result in `DecryptException` when reading SMTP credentials or re-generating historical certificate PDFs.

### 7.2 Public Verification Resilience During Key Loss
In the catastrophic event of a lost `APP_KEY`:
- **Public Certificate Verification Remains 100% Operational:** Certificate verification relies on the immutable SHA-256 hash (`verification_token_hash`), which is a key-independent one-way cryptographic digest. Scanned QR codes and URL verification lookups continue resolving and verifying credentials with zero downtime.
- **Decryption Recovery:** To recover SMTP credentials or reissue certificates, the administrator must input new SMTP settings in `/admin/settings/mail`, which will re-encrypt with the new `APP_KEY`.

