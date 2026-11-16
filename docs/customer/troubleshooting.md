# SEO Academy — Troubleshooting & Diagnostics

This guide provides solutions to common issues that may arise during installation or day-to-day operation of **SEO Academy**.

---

## 1. Common Installation Issues

### Issue: "Attempt to write a readonly database" (SQLite)
- **Cause:** The web server user does not have write permissions to the SQLite database file or its parent directory.
- **Solution:** SQLite requires write access to **both** the database file and the parent folder to create temporary write-ahead log files (`.sqlite-wal` and `.sqlite-shm`):
  ```bash
  chmod -R 775 storage/app/private
  chown -R www-data:www-data storage/app/private
  ```

### Issue: MySQL Error 1045 (Access denied for user)
- **Cause:** Incorrect database credentials, or the MySQL user does not have permissions to connect from the current host.
- **Solution:** Verify credentials in `.env`. Ensure your user has permissions granted on `localhost` or `127.0.0.1`:
  ```sql
  GRANT ALL PRIVILEGES ON seo_academy.* TO 'seo_user'@'127.0.0.1' IDENTIFIED BY 'your-password';
  FLUSH PRIVILEGES;
  ```

### Issue: Re-installation blocked / 403 Forbidden on `/install`
- **Cause:** An installation lock file exists at `storage/app/private/installed.lock`.
- **Solution:** For security reasons, SEO Academy permanently locks the installer once completed. If you genuinely need to perform a fresh reinstall on a clean server, delete `storage/app/private/installed.lock`.

---

## 2. General Runtime Issues

### Issue: Blank white screen or 500 Internal Server Error
1. Check the Laravel error log located at `storage/logs/laravel.log`.
2. Ensure `.env` exists and contains a valid 32-character base64 application key (`APP_KEY`). If missing, run:
   ```bash
   php artisan key:generate
   ```
3. Ensure `storage/` and `bootstrap/cache/` are writable:
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```

### Issue: CSRF token mismatch / Page Expired (419 Error)
- **Cause:** Session cookies are not being preserved across requests.
- **Solution:**
  - If accessing your site over plain HTTP (without SSL), ensure `SESSION_SECURE_COOKIE=false` in `.env`.
  - If your site is behind a cloud proxy (Cloudflare, AWS ALB), ensure trusted proxies are configured or verify your `APP_URL` in `.env` matches your full HTTPS URL (e.g. `APP_URL=https://your-academy.com`).

### Issue: Livewire components or Alpine dropdowns not reacting
- **Cause:** Outdated asset caches or missing Content Security Policy directives.
- **Solution:**
  1. Clear compiled asset caches:
     ```bash
     php artisan optimize:clear
     ```
  2. Ensure your web server does not inject conflicting custom CSP headers that block `'unsafe-eval'` or `'unsafe-inline'` required by Alpine.js and Livewire 4.

---

## 3. PDF Generation & Memory Issues

### Issue: Certificate PDF download fails with "Memory limit exhausted"
- **Cause:** Dompdf requires adequate PHP memory to render high-resolution certificate templates.
- **Solution:** Increase `memory_limit` in `php.ini` or `.env`:
  ```ini
  memory_limit = 256M
  ```

---

## 4. Diagnostic Commands

SEO Academy provides several diagnostic commands to inspect system health:

- **Check System Health:**
  ```bash
  php artisan system:health-sentinel
  ```
- **Clear All Application Caches:**
  ```bash
  php artisan optimize:clear
  ```
- **Inspect Scheduled Task Schedule:**
  ```bash
  php artisan schedule:list
  ```
- **Process Pending Certificates:**
  ```bash
  php artisan certificates:process-pdfs
  ```
