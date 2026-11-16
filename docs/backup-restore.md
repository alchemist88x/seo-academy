# Backup & Restore Guide — SEO Academy

SEO Academy is independently installed and operated by each customer. One installation supports exactly one academy.

All database backups should be stored outside the webroot—for example in `storage/app/private/backups/` (configured as the `private_backups` disk in `config/filesystems.php`, protected by `storage/app/private/.htaccess`).

> **Critical:** Always back up `.env` (specifically `APP_KEY`) alongside your database. Encrypted fields such as `AcademyAiCredential::$api_key` (`'encrypted'` cast) cannot be decrypted without the original `APP_KEY`.

---

## 1. SQLite Backup & Restore

Default SQLite database path: `storage/app/private/database.sqlite` (configured with `WAL` journal mode and `busy_timeout=5000` in `config/database.php`).

### Online-Safe Backup
**Never** copy `database.sqlite` with `cp` while the application is running—uncheckpointed WAL pages in `database.sqlite-wal` may be missed, causing corruption. Use `sqlite3`:

```bash
# Option 1: Online backup API
sqlite3 storage/app/private/database.sqlite ".backup 'storage/app/private/backups/seo-academy-$(date +%F-%H%M).sqlite'"

# Option 2: Compacted standalone snapshot via VACUUM INTO
sqlite3 storage/app/private/database.sqlite "VACUUM INTO 'storage/app/private/backups/seo-academy-$(date +%F-%H%M).sqlite';"
```

### Restore SQLite
```bash
php artisan down

# Remove stale WAL/SHM sidecar files before replacing the database
rm -f storage/app/private/database.sqlite-wal storage/app/private/database.sqlite-shm
cp storage/app/private/backups/seo-academy-YYYY-MM-DD-HHMM.sqlite storage/app/private/database.sqlite
chmod 640 storage/app/private/database.sqlite

php artisan up
```

---

## 2. MySQL Backup & Restore

### Online-Safe Backup (`mysqldump`)
Use `--single-transaction` for a consistent point-in-time InnoDB snapshot without locking tables:

```bash
mysqldump \
  --single-transaction \
  --routines \
  --triggers \
  --default-character-set=utf8mb4 \
  -h 127.0.0.1 -P 3306 -u DB_USERNAME -p DB_DATABASE \
  | gzip > storage/app/private/backups/seo-academy-$(date +%F-%H%M).sql.gz
```

### Restore MySQL
```bash
php artisan down

gunzip -c storage/app/private/backups/seo-academy-YYYY-MM-DD-HHMM.sql.gz \
  | mysql -h 127.0.0.1 -P 3306 -u DB_USERNAME -p DB_DATABASE

php artisan up
```

---

## 3. Private Media & Configuration Backup

In addition to the database, back up:
1. **`storage/app/private/media/`** — Uploaded course videos, lesson PDFs, and private attachments (`private_media` disk).
2. **`storage/app/private/installed.lock`** — Installation lock marker.
3. **`.env`** — Environment configuration and `APP_KEY` (store encrypted in a secure vault).

```bash
tar -czf storage/app/private/backups/private-media-$(date +%F).tar.gz storage/app/private/media/
```

---

## 4. Verification Checklist

After restoring to a staging or recovery environment:
1. Run `php artisan migrate:status` to confirm all migrations are accounted for.
2. Verify `storage/app/private/installed.lock` exists so `/install` remains locked.
3. Verify login and private media playback (`GET /media/{media}`).
