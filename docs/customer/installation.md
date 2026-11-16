# SEO Academy — Customer Installation Guide

Welcome to **SEO Academy v5.4.0**! This guide walks you through installing and configuring your independent LMS installation using the built-in graphical Installation Wizard or the command line.

---

## 1. Quick Installation Overview

Installing SEO Academy requires three simple steps:
1. **Upload & Extract:** Upload the release archive (`seo-academy-v5.4.0.zip`) to your hosting server and point your web server document root to the `/public` directory.
2. **Set Directory Permissions:** Ensure the `storage/` and `bootstrap/cache/` directories are writable by your web server user.
3. **Run the Wizard:** Navigate to your website URL (e.g. `https://your-domain.com/install`) and complete the 6-step setup.

---

## 2. Step-by-Step Installation Walkthrough

### Step 1: Upload and Extract Files
- Upload `seo-academy-v5.4.0.zip` to your server.
- Extract the archive into your chosen project root (for example, `/var/www/seo-academy` or `/home/user/seo-academy`).
- Configure your web server virtual host so that the public web root points directly to the `/public` subfolder.

> [!IMPORTANT]
> Never set the document root to the main application directory. Pointing directly to `/public` ensures that configuration files, templates, and private databases cannot be accessed directly via web browsers.

### Step 2: Configure Directory Permissions
Using SSH or your hosting File Manager, grant write permissions to the web server process (e.g. `www-data`, `nginx`, or your cPanel user):

```bash
chmod -R 775 storage bootstrap/cache
```

On shared hosting, directories are typically owned by your user account and standard `755` permissions are sufficient.

### Step 3: Run the Graphical Installation Wizard
Open your browser and navigate to:
```text
https://your-domain.com/install
```

The wizard will guide you through:
- **Server Health Check:** Confirms your PHP version (8.3+) and required extensions (`pdo`, `bcmath`, `openssl`, `mbstring`, `fileinfo`, `gd`, `zip`).
- **Database Selection:**
  - **SQLite:** Zero configuration needed. The installer will create and secure your database file in `storage/app/private/database.sqlite`.
  - **MySQL:** Enter your MySQL server host (`127.0.0.1`), port (`3306`), database name, username, and password. You can click **Test Connection** to verify your credentials before proceeding.
- **Academy Profile:** Enter your Academy Name, Support Email, Timezone, and Locale.
- **Initial Administrator Account:** Create your primary superuser account with your name, email, and a secure password.
- **Database Migration & Initialization:** The wizard applies all 86 core database schemas, registers system roles, and provisions your initial academy.

Upon completion, an installation lock file is written to `storage/app/private/installed.lock`. This prevents anyone from re-running the installer or altering your database.

---

## 3. Alternative: Command-Line Installation (CLI)

For system administrators deploying on headless VPS servers, installation can also be completed via the terminal:

1. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
2. Generate your unique cryptographic application key:
   ```bash
   php artisan key:generate
   ```
3. Configure your database settings in `.env` (SQLite or MySQL).
4. Run the database migrations and seed default roles:
   ```bash
   php artisan migrate --force
   php artisan db:seed --class=Database\\Seeders\\RoleSeeder --force
   ```
5. Place the installation sentinel lock:
   ```bash
   touch storage/app/private/installed.lock
   ```

---

## 4. Post-Installation Checklist

- [ ] **Configure Scheduled Tasks (Cron):** Add the Laravel scheduler to your server crontab (see [Shared Hosting Guide](file:///C:/laragon/www/seo-academy/docs/customer/shared-hosting.md) or [VPS Deployment Guide](file:///C:/laragon/www/seo-academy/docs/customer/vps-deployment.md)).
- [ ] **Configure Email (SMTP):** Navigate to **Admin Dashboard -> Academy Settings** to set up your outgoing mail server for notifications and certificates.
- [ ] **Verify SSL/HTTPS:** Ensure your SSL certificate is active and set `SESSION_SECURE_COOKIE=true` in `.env`.
- [ ] **Create Your First Course:** Log in as your Administrator account to begin adding curriculum, modules, lessons, and assigning trainers.
