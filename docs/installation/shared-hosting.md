# Shared Hosting Deployment Guide — SEO Academy

SEO Academy is independently installed and operated by each customer. One installation supports exactly one academy.

SEO Academy is built to run on standard cPanel / Apache / Nginx shared hosting without root access, Node.js, Redis, Memcached, Supervisor, or Docker.

*(See also [../architecture/shared-hosting.md](../architecture/shared-hosting.md) for architectural background.)*

---

## 1. Zero-Daemon Default Configuration

`.env.example` configures all runtime subsystems to use the database so no background daemons or in-memory stores are required:

```env
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

| Subsystem | Backing Tables |
|---|---|
| Sessions | `sessions` |
| Cache | `cache`, `cache_locks` |
| Queues | `jobs`, `job_batches`, `failed_jobs` |

---

## 2. Webroot & Private Storage (`storage/app/private/`)

1. **Set Document Root to `public/`:** Never point your domain's webroot to the project root. This keeps `.env`, `app/`, `database/`, `vendor/`, and `storage/` outside HTTP-accessible paths.
2. **Private Storage Directory:** `storage/app/private/` holds:
   - `database.sqlite` (when using SQLite)
   - `installed.lock` (created by the `/install` wizard)
   - `media/` (`private_media` disk, `serve => false`, `visibility => 'private'`)
   - `backups/` (`private_backups` disk, `serve => false`, `visibility => 'private'`)
3. **Defense-in-Depth `.htaccess`:** `storage/app/private/.htaccess` denies all direct HTTP requests (`Require all denied` / `Deny from all`).
4. **Permissions:** Set directories under `storage/` and `bootstrap/cache/` to `0750` and `database.sqlite` (if used) to `0640`.

---

## 3. Cron Jobs (cPanel / Standard Cron)

Add these two 1-minute cron jobs in cPanel → **Cron Jobs**:

```cron
* * * * * cd /home/USERNAME/seo-academy && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/USERNAME/seo-academy && /usr/local/bin/php artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
```

- `--stop-when-empty` drains pending jobs and exits cleanly, preventing long-running PHP process kills on shared hosting.
- Replace `/usr/local/bin/php` with your host's PHP 8.3+ CLI binary path if different.

---

## 4. Pre-Built Assets (No Node.js on Server)

Run `npm ci && npm run build` locally or in CI, then upload the generated `public/build/` directory. Node.js and npm are not required on the shared host.

---

## 5. Database Choice on Shared Hosting

- **MySQL 8.0+ (Recommended):** Best for concurrent learners, higher-traffic single-academy deployments, and database-backed queues.
- **SQLite 3.35+:** Zero external database setup; uses `storage/app/private/database.sqlite` with `WAL` mode. Only use on a single local filesystem (never on NFS/SMB).
