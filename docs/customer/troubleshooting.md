# SEO Academy — Troubleshooting & Diagnostics

This guide provides practical solutions to common issues that may arise during installation or day-to-day operation of **SEO Academy**.

---

## 1. Installation Troubleshooting

### Issue: Pre-flight shows "Application Dependencies Required (vendor/ missing)"
- **Cause:** You downloaded source files without pre-bundled Composer packages.
- **Solution:**
  - **Option 1 (Recommended):** Download the official prebuilt release archive (`seo-academy-v5.6.0.zip`) from the GitHub Releases page. This package includes the complete, tested production `vendor/` directory.
  - **Option 2:** If your server has the Composer CLI available, click **Install Dependencies via Server Composer** on the pre-flight screen.
  - **Option 3:** Run `composer install --no-dev --optimize-autoloader` locally on your development machine, then upload the `vendor/` directory to your server.

### Issue: "Attempt to write a readonly database" (SQLite)
- **Cause:** The web server user lacks write permissions to `storage/app/private/` or the `database.sqlite` file.
- **Solution:** SQLite requires write access to **both** the database file and its parent folder to create temporary journal and write-ahead log files (`.sqlite-wal`, `.sqlite-shm`):
  ```bash
  chmod -R 775 storage/app/private
  ```
  On shared hosting, use the cPanel File Manager to ensure `storage/app/private/` has `755` or `775` permissions.

### Issue: MySQL Error 1045 (Access denied for user) or Connection Refused
- **Cause:** Incorrect database credentials, or the MySQL user does not have permission to connect from the current host.
- **Solution:**
  - Verify your database credentials by clicking **Test Connection** in Step 4 of the wizard.
  - Ensure the database user has permissions on `127.0.0.1` or `localhost`:
    ```sql
    GRANT ALL PRIVILEGES ON seo_academy.* TO 'academy_user'@'127.0.0.1' IDENTIFIED BY 'your-password';
    FLUSH PRIVILEGES;
    ```
  - Ensure the database exists prior to connecting.

### Issue: "Reinstallation is blocked" or 403 Forbidden on `/install`
- **Cause:** An installation lock file exists at `storage/app/private/installed.lock`.
- **Solution:** For security reasons, SEO Academy permanently locks the installer once completed. If you genuinely need to perform a fresh reinstall on a clean server, delete `storage/app/private/installed.lock`.

---

## 2. General Runtime Issues

### Issue: Blank white screen or 500 Internal Server Error
1. Check the application log located at `storage/logs/laravel.log`.
2. Ensure `.env` exists and contains a valid 32-character base64 application key (`APP_KEY`). The installer creates this automatically, but if damaged, generate one:
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
  - If your site is behind a cloud proxy (Cloudflare, AWS ALB), ensure `APP_URL` in `.env` matches your full HTTPS URL (e.g. `APP_URL=https://your-academy.com`).

---

## 3. System Diagnostics Tool

Visit `/install/diagnostics` to review:
- Exact PHP runtime version and loaded extensions.
- Database connection status and table counts.
- Writable storage directories.
- Package integrity.

Click **Download Sanitized Report (JSON)** to generate a sanitized diagnostic export suitable for technical review.
