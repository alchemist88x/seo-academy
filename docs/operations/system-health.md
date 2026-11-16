# Operational Guide — System Health & Diagnostics

SEO Academy provides truthful administrative diagnostics for monitoring server health, database connectivity, and cron workers.

## 1. System Health Dashboard
- Accessible to administrators at `/admin/system/health`.
- Reports actual runtime status across six subsystems:
  1. **Application Core:** Application name, version, Laravel version, PHP version, environment, debug status, and maintenance mode.
  2. **Database Engine:** Configured driver, connection ping latency, database name, and completed migrations count.
  3. **Storage Volumes:** Writable status for `storage/app/private`, media disk, and backups disk, along with total managed files and available free disk space.
  4. **Queue Subsystem:** Configured driver, pending jobs count (for database queue), and failed jobs count.
  5. **Scheduler & Crontab:** Sentinel verification indicating whether cron has executed in the last 70 minutes, along with recommended crontab commands.
  6. **PHP Requirements Checklist:** Verification of all required extensions (`pdo`, `pdo_sqlite` or `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `curl`, `zip`), memory limit, and upload limits.

## 2. SMTP Settings Interface
- Accessible to administrators at `/admin/system/settings`.
- Outbound SMTP mail configuration (Host, Port, Encryption, Username, Password, Sender Address, Sender Name).
- Passwords are encrypted at rest using AES-256 (`Crypt::encryptString`) and masked in UI.
- Built-in connection tester dispatches a real test email to verify credentials and connectivity without leaking passwords.

