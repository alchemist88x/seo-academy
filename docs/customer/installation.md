# SEO Academy — Customer Zero-Terminal Installation Guide

Welcome to **SEO Academy v5.6.0**! SEO Academy is engineered for complete zero-terminal, browser-based installation. You do **not** need SSH, terminal access, command-line tools, or manual database setup to install and launch your academy.

---

## 1. Quick Installation Overview

Installing SEO Academy requires only three simple steps:

1. **Deploy Files:** Upload or clone the repository to your hosting account (cPanel, Plesk, Apache shared hosting, or VPS).
2. **Set Document Root:** Configure your web server virtual host or domain document root to the project's `public/` directory.
3. **Open Your Domain:** Navigate to your domain in any modern web browser (e.g. `https://your-domain.com`). The automated wizard launches immediately.

---

## 2. Pre-flight Architecture & Safe Bootstrapper

When you open your domain for the first time:

- **Missing Vendor Handling:** If you deployed source without dependencies, the pre-flight bootstrapper catches this and displays a clean Dependency Pre-flight page instead of a fatal PHP exception.
- **Automatic Environment Generation:** The installer reads `.env.example`, copies it to `.env`, and generates a cryptographically secure 256-bit AES `APP_KEY` automatically. You never need to manually copy or edit `.env` before installation.
- **Route Protection:** Normal application routes (such as `/login`, `/admin`, and course portals) are automatically blocked until installation prerequisites and finalization are complete.

---

## 3. The 9-Step Guided Browser Wizard

The installation wizard guides you step-by-step through a resilient, resumable process:

### Step 1: Server & PHP Requirements
- Confirms PHP 8.3 or higher.
- Inspects all mandatory extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo`, `session`, `tokenizer`, `xml`, `zip`.
- Checks driver-specific extensions (`pdo_sqlite` for SQLite, `pdo_mysql` for MySQL).
- Verifies real write access across `storage/` and `bootstrap/cache/`.

### Step 2: Composer Dependencies Check
- Validates the presence of `composer.json`, `vendor/autoload.php`, and `installed.json`.
- Confirms versions of `laravel/framework`, `livewire/livewire`, `dompdf/dompdf`, and `spatie/laravel-permission`.

### Step 3: Automatic Environment Setup
- Verifies `.env` generation and application encryption key.
- Ensures the configuration file is protected from public browser access.

### Step 4: Database Selection & Configuration
- **SQLite (Recommended):** Zero-configuration, high-performance database stored securely in `storage/app/private/database.sqlite`. Fully self-contained.
- **MySQL 8.4+ InnoDB:** Enter your host, port, database name, username, and password. Click **Test Connection** to verify connectivity, server version (8.4+), and InnoDB support before continuing.
- **Database Switching Protection:** If an initialized database already exists, switching drivers requires explicit confirmation to protect existing data.

### Step 5: Database Initialization & Migrations
- Executes the registered 86-migration catalog sequentially and idempotently.
- Installs the 4 standard security roles (`admin`, `trainer`, `support_staff`, `student`).
- **Data Safety:** Never executes `migrate:fresh` or drops existing tables.

### Step 6: Filesystem & Storage Setup
- Creates and verifies write permissions on private directories:
  - `storage/app/private/`
  - `storage/app/private/media/`
  - `storage/app/private/backups/`
  - `storage/framework/cache/`
  - `storage/framework/sessions/`
  - `storage/framework/views/`
  - `storage/logs/`
  - `bootstrap/cache/`

### Step 7: Academy Profile Configuration
- Define your Academy Name and slug.
- Configure primary contact/support email.
- Set timezone, default locale (`en`, `es`, `fr`, `de`), currency code, and regional date/time formats.

### Step 8: Primary Administrator Account
- Enter your full name, administrator email, and a secure password (minimum 8 characters).
- Creates the super administrator account and assigns the `admin` role.

### Step 9: Finalization & Security Lock
- Executes an end-to-end system healthcheck.
- Safely clears configuration and view caches.
- Writes the immutable sentinel lock file: `storage/app/private/installed.lock`.
- Permanently locks the installer and redirects to the sign-in screen.

---

## 4. System Diagnostics

You can inspect your instance health at any time during installation or post-installation by visiting:
```text
https://your-domain.com/install/diagnostics
```

Click **Download Sanitized Report (JSON)** to generate a sanitized diagnostic export. All passwords, session tokens, and cryptographic keys are strictly excluded.

---

## 5. Post-Installation Recommendations

- [ ] **Configure Cron (Scheduled Tasks):** Add the Laravel schedule worker to your hosting cron:
  ```text
  * * * * * cd /path/to/seo-academy && php artisan schedule:run >> /dev/null 2>&1
  ```
- [ ] **Configure Outgoing Email (SMTP):** Sign in as Administrator and navigate to **Academy Settings -> Email** to configure transactional emails.
- [ ] **Enforce HTTPS:** Ensure SSL is installed on your domain and set `SESSION_SECURE_COOKIE=true` in `.env`.
