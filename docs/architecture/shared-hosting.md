# Shared Hosting & Deployment Guide — SEO Academy

SEO Academy is engineered to run reliably on standard PHP/Apache/Nginx shared hosting as well as VPS and dedicated servers, without requiring root access, Node.js, Redis, Memcached, or Docker at runtime.

---

## 1. Runtime Requirements

### Required on Server
- **PHP:** `8.3+` with standard extensions (`pdo_sqlite` and/or `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`)
- **Web Server:** Apache (with `mod_rewrite` and `.htaccess` support) or Nginx
- **Database:** SQLite 3.35+ (single-server/small academy) **or** MySQL 8.0+ with InnoDB (recommended for production)
- **Cron:** Standard cPanel/system cron capability (1-minute interval)

### NOT Required at Runtime
- **No Node.js / npm:** Frontend CSS and JS are pre-built via `npm run build` into `public/build/`.
- **No Redis / Memcached:** Sessions, cache, and queues use the database driver.
- **No Supervisor / Horizon / Octane / Docker:** Background jobs run via cron-triggered `queue:work --stop-when-empty`.
- **No Root Access:** All writable paths reside inside `storage/` and `bootstrap/cache/`.

---

## 2. Database-Backed Sessions, Cache, and Queues

Default `.env.example` and `config/` settings are pre-configured for zero-daemon shared hosting:

| Subsystem | Config File | Default Driver | Backing Table(s) |
|---|---|---|---|
| **Sessions** | `config/session.php` | `SESSION_DRIVER=database` | `sessions` |
| **Cache** | `config/cache.php` | `CACHE_STORE=database` | `cache`, `cache_locks` |
| **Queues** | `config/queue.php` | `QUEUE_CONNECTION=database` | `jobs`, `job_batches`, `failed_jobs` |

Ensure your production `.env` retains these database-backed drivers unless you intentionally provision Redis on a dedicated host:

```env
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

---

## 3. Cron Setup

Configure two 1-minute cron jobs in cPanel or `crontab -e` to drive Laravel's scheduler and process queued jobs without a persistent daemon:

```cron
* * * * * cd /path/to/seo-academy && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /path/to/seo-academy && php artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
```

- `--stop-when-empty` exits cleanly once the `jobs` table is drained, preventing runaway PHP processes on shared hosting.
- `--tries=3` retries transient failures before moving jobs to `failed_jobs`.

---

## 4. Webroot & Private Persistent Storage

### Document Root
Point the domain/subdomain webroot strictly to `public/` (never the repository root). This keeps `.env`, `vendor/`, `app/`, `database/`, and `storage/` outside HTTP-accessible paths.

### Private Storage Outside Webroot
All sensitive runtime files are stored under `storage/app/private/`, configured in `config/filesystems.php` and `config/database.php`:

| Purpose | Path | Disk / Config |
|---|---|---|
| **Private Course Media** | `storage/app/private/media/` | `private_media` (`serve => false`, `visibility => 'private'`) |
| **Private Backups** | `storage/app/private/backups/` | `private_backups` (`serve => false`, `visibility => 'private'`) |
| **SQLite Database** | `storage/app/private/database.sqlite` | `connections.sqlite.database` |

### Defense-in-Depth `.htaccess` Protection
Even if a misconfigured server exposes `storage/`, `storage/app/private/.htaccess` explicitly denies all direct HTTP requests:

```apache
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
```

> **Note:** `storage/app/private/.gitignore` also excludes `database.sqlite`, `database.sqlite-wal`, and `database.sqlite-shm` from version control. Never run `php artisan storage:link` for private media—only `storage/app/public/` is linked to `public/storage`.

### Permissions (Linux)
Ensure the PHP/web server user owns `storage/` and `bootstrap/cache/`:
- Directories: `0750` (especially `storage/app/private/` so SQLite can create `-wal` and `-shm` sidecar files)
- SQLite database file: `0640`

---

## 5. SQLite vs MySQL Deployment Guidance

| Criteria | SQLite (`DB_CONNECTION=sqlite`) | MySQL (`DB_CONNECTION=mysql`) |
|---|---|---|
| **Best For** | Small single-academy installs, demos, low-concurrency sites | Production multi-academy deployments, concurrent learners, high queue volume |
| **Setup** | Zero external DB server; uses `storage/app/private/database.sqlite` | Managed MySQL 8.0+ (InnoDB, `utf8mb4_unicode_ci`) |
| **Concurrency** | `WAL` mode + `busy_timeout=5000` (single writer at a time) | Row-level InnoDB locking; handles concurrent writes cleanly |
| **Scaling Limit** | **Single-server only** — never place `database.sqlite` on NFS/SMB shares | Supports separate DB host and multi-worker concurrency |

---

## 6. Backup & Restore Commands

### SQLite (Online-Safe Backup)
Never copy `database.sqlite` with `cp` while the app is live (WAL pages may not be checkpointed). Use `sqlite3 .backup` or `VACUUM INTO`:

```bash
# Online backup
sqlite3 storage/app/private/database.sqlite ".backup 'storage/app/private/backups/seo-academy-$(date +%F).sqlite'"

# Or via VACUUM INTO (produces a compacted standalone snapshot)
sqlite3 storage/app/private/database.sqlite "VACUUM INTO 'storage/app/private/backups/seo-academy-$(date +%F).sqlite';"
```

Restore by placing the application in maintenance mode (`php artisan down`), replacing `storage/app/private/database.sqlite` (and removing stale `-wal`/`-shm` files), and running `php artisan up`.

### MySQL (`mysqldump`)
Use `--single-transaction` for a consistent point-in-time InnoDB snapshot without locking tables:

```bash
# Backup
mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 \
  -u your_db_user -p seo_academy > storage/app/private/backups/seo-academy-$(date +%F).sql

# Restore
mysql --default-character-set=utf8mb4 -u your_db_user -p seo_academy < seo-academy-YYYY-MM-DD.sql
```

### Private Media Backup
Back up `storage/app/private/media/` alongside your database snapshot so `media` table records and underlying files stay in sync:

```bash
tar -czf storage/app/private/backups/media-$(date +%F).tar.gz -C storage/app/private media
```
