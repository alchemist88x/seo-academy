# SEO Academy — Backup & Disaster Recovery Guide

SEO Academy features an integrated backup and disaster recovery system designed to protect course materials, student submissions, gradebook records, and issued certificates against hardware failure or accidental data loss.

---

## 1. Creating Backups

### Method A: Via the Web Interface
1. Log in as an Administrator.
2. Navigate to **Admin Dashboard -> System -> Backups** (`/admin/system/backups`).
3. Click **Create New Backup**.
4. Choose backup content:
   - **Database Only:** Takes a point-in-time snapshot of the database.
   - **Full Archive (Database + Media):** Packages the database along with all uploaded course attachments, video progress, and student submissions.
5. The backup archive will appear in the table once generated. You can download the `.zip` archive directly to your local computer.

### Method B: Via the Terminal (CLI)
To run a backup manually or automate it via external scripts:

```bash
php artisan backup:run
```

To take a database-only backup via CLI:
```bash
php artisan backup:run --only-db
```

Backup archives are stored safely in `storage/app/private/backups/`.

---

## 2. Automated Daily Backups

To automate backups, ensure your system crontab runs the scheduler. SEO Academy automatically schedules a daily database backup at midnight (`00:00 UTC`).

If you wish to create a dedicated cron job for nightly full backups:
```bash
0 2 * * * php /path/to/seo-academy/artisan backup:run >> /path/to/seo-academy/storage/logs/backup.log 2>&1
```

---

## 3. Disaster Recovery: Restoring from a Backup

Restoration is non-destructive until validation succeeds, taking an atomic safety rollback snapshot before overwriting files.

### Restoring via CLI
To list available backups:
```bash
php artisan backup:list
```

To restore a specific backup file:
```bash
php artisan backup:restore backup-2026-11-16-120000.zip
```

The restore process:
1. Validates archive integrity and zip header checksums.
2. Acquires exclusive process lock (`backup_restore.lock`).
3. Takes a pre-restore safety snapshot of the active database.
4. Restores the database schema and data.
5. Restores private student and media files.
6. Runs database integrity verification checks.
7. Clears application route and view caches.

---

## 4. The Critical Role of `APP_KEY` in Disaster Recovery

> [!WARNING]
> Always store a secure offline copy of your application's `APP_KEY` (located in your `.env` file).

### What `APP_KEY` Affects:
- **Public Certificate Verification:** Does **not** require the original key; public verification uses one-way SHA-256 blind hashes (`verification_token_hash`).
- **Backup Archive Extraction:** Archives are standard zip packages and can be extracted on any server without `APP_KEY`.
- **Decrypted Data at Rest:** If a backup is restored to a server with a different or regenerated `APP_KEY`, encrypted database columns (such as stored SMTP passwords or raw certificate codes) cannot be decrypted.
- **Rule of Thumb:** When restoring to a brand-new server, always copy the original `APP_KEY` from your old `.env` into the new server's `.env` file before restoring.
