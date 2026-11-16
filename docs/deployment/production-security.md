# Production Security Guidelines — SEO Academy

This document outlines key security considerations for operating SEO Academy in production.

---

## 1. Secrets & Credentials Protection
- **`APP_KEY`:** Never change or lose your `APP_KEY`. Encrypted database settings (including SMTP passwords) depend on this key.
- **Environment File:** `.env` must never be placed inside the public webroot. Web servers must block access to `.env*`.
- **Database Credentials:** Never expose database connection strings in public logs, error pages, or frontend JavaScript.
- **SMTP Passwords:** Stored using AES-256 (`Crypt::encryptString`) in the `settings` table and masked in the UI (`••••••••`).

---

## 2. File Upload Hardening
- **Executable Blocking:** Extensions including `.php`, `.phtml`, `.phar`, `.sh`, `.exe`, `.cgi` are strictly blocked server-side by `FileManagementService`.
- **MIME Verification:** Uploads are checked against prohibited MIME types (`text/x-php`, `application/x-executable`, etc.).
- **Randomized Storage Names:** Storage paths use non-guessable UUIDs rather than trusting client filenames.
- **Private Media Streaming:** All media downloads pass through `PrivateMediaService` with server-side authorization checks for enrollment and course ownership.

---

## 3. Administrative Access Controls
- Navigation and routes under `/admin/*` are strictly protected with `role:admin`.
- Role escalation is prevented server-side via Spatie permissions and role checks.
- Audit logging records administrative events with client IP and User Agent, redacting sensitive parameters.

---

## 4. Disaster Recovery
- Point-in-time database snapshots are kept outside the webroot (`storage/app/private/backups`).
- Restoring a backup requires explicit confirmation and creates an automatic pre-restore safety snapshot.

