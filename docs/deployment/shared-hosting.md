# Shared-Hosting Deployment Guide — SEO Academy

SEO Academy is intentionally designed to run smoothly on conventional cPanel, Plesk, or standard PHP shared hosting without root access, Redis, Docker, or permanent daemon workers.

---

## 1. Requirements
- PHP 8.3 or higher.
- Required PHP Extensions: `pdo`, `pdo_sqlite` or `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `curl`, `zip`.
- Web Server: Apache or Nginx.
- Document Root pointed to the `/public` directory.

---

## 2. Shared-Hosting Crontab Setup
Shared hosting does not have persistent supervisor daemons. Configure a standard cron job in cPanel or Plesk that runs every minute:

```bash
* * * * * cd /path/to/seo-academy && php artisan schedule:run >> /dev/null 2>&1
```

This single cron job handles:
1. Hourly automated processing of expired course enrollments (`enrollments:process-expired`).
2. Scheduler diagnostic sentinel health checks (`system:health-sentinel`).
3. Scheduled database and queue maintenance tasks.

---

## 3. Database Selection on Shared Hosting
- **SQLite:** Recommended for single-academy installations with light-to-moderate traffic. Zero database server configuration required. Stored at `storage/app/private/database.sqlite` (WAL mode enabled).
- **MySQL 8.0+:** Recommended for higher traffic or multi-trainer academies. Use InnoDB engine and utf8mb4 charset.

---

## 4. File Permissions
Ensure the web process (e.g. `www-data`, `nobody`, or your cPanel user) has write permissions to:
```bash
chmod -R 775 storage bootstrap/cache
```
Application PHP source files should remain read-only (`644` for files, `755` for directories).
Direct HTTP access to `storage/app/private/` is blocked by Apache `.htaccess`.

