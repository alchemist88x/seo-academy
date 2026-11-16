# Installation Guide — SEO Academy

SEO Academy is a commercial, self-hosted Learning Management System (LMS) independently installed and operated by each customer.
**One installation supports exactly one academy.** There is no multi-tenancy, cross-academy sharing, or central service dependency.

This guide provides end-to-end instructions for deploying and installing SEO Academy on conventional PHP hosting environments (shared hosting, VPS, or dedicated servers) using either the built-in 8-stage interactive web wizard (`/install`) or the artisan CLI.

---

## 1. Server Requirements

Ensure the hosting environment satisfies the following minimum requirements prior to installation:

| Requirement | Specification | Notes |
|---|---|---|
| **PHP Version** | `>= 8.3.0` | Matches Composer platform requirement (`^8.3`), 64-bit recommended |
| **Common Required PHP Extensions** | `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `zip` | Essential for core framework and media processing across all drivers |
| **Database-Specific Extensions** | **SQLite:** `pdo_sqlite`<br>**MySQL:** `pdo_mysql` | **Driver-specific:** Selecting SQLite does *not* require `pdo_mysql`; selecting MySQL does *not* require `pdo_sqlite`. |
| **Database Engine** | **SQLite 3.35+** (zero-configuration local file) OR **MySQL 8.0+** | If using MySQL, **InnoDB** engine and `utf8mb4_unicode_ci` collation are mandatory. |
| **Web Server** | Apache (with `mod_rewrite`) or Nginx | Document root MUST point to `/public`. |
| **Process Permissions** | Read/write permissions for web server user (`www-data`, `apache`, or hosting account user) | On `storage/` and `bootstrap/cache/` directories. |

### Writable Directory Permissions
The following directories must be writable by the PHP web server process:
```bash
chmod -R 775 storage/
chmod -R 775 storage/app/
chmod -R 775 storage/app/private/
chmod -R 775 storage/framework/
chmod -R 775 storage/framework/cache/
chmod -R 775 storage/framework/sessions/
chmod -R 775 storage/framework/views/
chmod -R 775 storage/logs/
chmod -R 775 bootstrap/cache/
```

---

## 2. Web Server Configuration

To prevent exposure of application code, configuration, and environment files, **the web server document root must be set to the `public/` directory, never the application root**.

### Apache Configuration
Ensure `mod_rewrite` is enabled. A typical virtual host entry:
```apache
<VirtualHost *:80>
    ServerName academy.yourdomain.com
    DocumentRoot /var/www/seo-academy/public

    <Directory /var/www/seo-academy/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/seo_academy_error.log
    CustomLog ${APACHE_LOG_DIR}/seo_academy_access.log combined
</VirtualHost>
```

### Nginx Configuration
```nginx
server {
    listen 80;
    server_name academy.yourdomain.com;
    root /var/www/seo-academy/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 3. Web Installation Wizard (`/install`)

When navigating to your academy URL for the first time without an existing installation, the application automatically routes you to the interactive 8-stage installation wizard at `/install`.

The installation wizard walks you through the following stages:

### Stage 1: Welcome & Architecture
- Highlights the single-academy architectural model.
- Explains that the installation is fully self-hosted, independent, and does not require third-party SaaS services.

### Stage 2: Server Requirements Pre-Flight Check
- Automatically audits the hosting environment:
  - Verifies PHP version `>= 8.3.0`.
  - Verifies common mandatory PHP extensions (`pdo`, `zip`, `fileinfo`, `mbstring`, `openssl`, etc.).
  - Audits driver extensions: shows availability of `pdo_sqlite` and `pdo_mysql`. Only the extension matching your chosen driver is required.
  - Verifies write permissions on `storage/`, `storage/app/private/`, `storage/framework/`, `storage/logs/`, and `bootstrap/cache/`.
- Clear diagnostic indicators allow resolving any server permission or extension gaps before proceeding.

### Stage 3: Database Configuration & Existing Database Protection
Select between SQLite and MySQL:
- **SQLite (Recommended for zero-configuration, small/medium academies):**
  - Stored securely in `storage/app/private/database.sqlite`.
  - Automatically created and verified for write access.
- **MySQL 8.0+ (Recommended for larger deployments and high traffic):**
  - Requires Host, Port (default 3306), Database Name, Username, and Password.
  - Interactive **Test Connection** button probes PDO connectivity, verifies InnoDB engine support via `SHOW ENGINES`, and checks DDL/DML permissions using a temporary probe table (`_mwa_perm_probe`).
  - Passwords and database credentials are sanitised and never exposed in logs or test response payloads.

#### Strict Existing Database Protection & Ownership Detection
The installer actively audits and verifies database ownership before migrating or executing changes:
1. **Foreign Table & Unrelated Application Detection:** If the target database contains tables unrelated to SEO Academy (e.g. WordPress, customer CRM, or other applications), the installer **immediately blocks execution**. It will never silently overwrite, drop, truncate, or migrate an unrelated database.
2. **Coincidental Laravel Application Protection (Migration Comparison):** Generic Laravel applications often share table names like `users`, `cache`, and `migrations`. To prevent false ownership attribution:
   - The installer inspects the `migrations` table and cross-references all executed migration names against the 65 official SEO Academy migrations (`DatabaseInstallerService::getOfficialMigrations()`).
   - If *any* executed migration is not part of the official SEO Academy migration registry, the database is flagged as an `unrelated_database` and all execution is blocked.
3. **Schema Architecture Validation (UUID vs. Integer ID):** SEO Academy utilizes RFC 4122 UUID primary keys (`char(36)` / `varchar(36)`) for its user identity layer. The installer inspects the `users.id` column definition. If an existing `users` table uses integer auto-increment (`bigint unsigned`) common to standard Laravel templates, the installer rejects the database as unrelated.
4. **Dedicated Installation Identity Marker (`installation_identity`):**
   - Before executing migrations on a fresh database, the installer positively establishes database ownership by writing an immutable identity record into `installation_identity` (`app = 'seo-academy'`, unique `installation_uuid`, `status = 'in_progress'`).
   - The installer validates this marker before attempting to resume an interrupted installation.
   - A database that merely resembles SEO Academy (even one containing all 65 migrations and UUID tables) is strictly rejected as `ambiguous` if it lacks this verified identity marker.
5. **Persistent Completion Marker (`system.installed_at`):** SEO Academy records an installation marker in the `settings` table upon successful installation and updates `installation_identity` to `status = 'completed'`. This cleanly separates completed academies from interrupted migrations.
6. **No Unsafe Force-Install:** There is no force-override option to prevent accidental destruction of customer or third-party data.

### Stage 4: Academy Profile
Configure the unique academy identity:
- **Academy Name** (e.g., "Apex SEO Training Academy").
- **Academy Slug** (e.g., "apex-seo", auto-suggested).
- **Academy Support Email** (e.g., "support@yourdomain.com", used for student notifications).
- **Timezone** (e.g., "UTC", "America/New_York", "Europe/London").
- **Locale** (default `en`).
- *Note:* The system strictly enforces the single-academy model; exactly one academy instance will be created.

### Stage 5: Initial Administrator Account
Create the root administrator account:
- **Administrator Name** (Full name).
- **Administrator Email** (Valid email address, automatically marked as email-verified).
- **Password & Confirmation** (Minimum 8 characters, securely hashed with Bcrypt/Argon2).
- Automatically assigned the foundational `admin` Spatie role.

### Stage 6: System Defaults & Production Runtime
Confirms runtime configuration for shared hosting:
- Queue connection configured to `database` (safe for cron-based processing without supervisor/root access).
- Session storage configured to `database` / `file`.
- Prepopulates standard Spatie authorization roles: `admin`, `trainer`, `support_staff`, and `student`.
- Initializes a clean production state without mock or sample courses.

### Stage 7: Installation Execution & Partial Failure Recovery
Upon clicking **Complete Installation**:
1. Pre-flight driver requirements and database connection are verified.
2. Updates `.env` atomically using a temporary file in the same directory, escaping special characters and setting safe permissions.
3. Clears framework configuration cache.
4. Executes pending database migrations in sequence (`php artisan migrate --force`).
5. Seeds authorization roles and permissions (`RoleSeeder`).
6. Creates or attaches the single `Academy` record.
7. Provisions the initial `admin` user with the `admin` role and active account status.
8. Records persistent installed state in the database (`settings` table `system.installed_at`).
9. Writes the atomic lock file `storage/app/private/installed.lock`.

### Stage 8: Completion Screen (`/install/complete`)
- Displays installation success confirmation.
- Displays Academy Name and Administrator Email.
- Directs the administrator to the login page (`/login`).
- Highlights the post-installation checklist (cron setup, mail configuration).

---

## 4. Security, Reinstallation Protection & Fail-Closed Guarding

SEO Academy implements a defense-in-depth, fail-closed reinstallation prevention system:

### Layer 1: Filesystem Lock (`installed.lock`)
- **Location:** `storage/app/private/installed.lock` (outside the public webroot).
- **Guarded Routes:** `EnsureNotInstalled` middleware intercepts all requests to `/install*`.
- When installed, requests to `/install` are redirected to `/login` (or return `403 Forbidden` for API endpoints).
- The completion session flag (`install_completed`) permits **only** `GET /install/complete`. All execution routes (`POST /install/database`, `POST /install/test-connection`, etc.) and intermediate stages remain strictly blocked.

### Layer 2: Persistent Database State & Strict Self-Healing
- **Fail-Closed State Resolution (`isInstalled()`):**
  If `installed.lock` is deleted or missing from the filesystem, `DatabaseInstallerService` queries the configured database to determine its status:
  - **`installed`**: Confirms that: (1) exactly one `Academy` record exists, (2) an active `admin` user exists, and (3) the `system.installed_at` marker exists in `settings`. In this case, the service **self-heals by regenerating `installed.lock`** and denies reinstallation.
  - **`database_unavailable`**: If the database connection fails (network timeout, authentication failure, or host down), the system **fails closed**: `isInstalled()` returns `true`, and reinstallation is strictly denied. An unreachable database will NEVER be treated as uninstalled or exposed to reinstallation.
  - **`ambiguous`**: If a partial marker exists without an academy, or an academy exists without a marker, or users exist without an academy, the system flags the state as `ambiguous`. It **refuses to regenerate the lock file** (avoiding false assertion of health) while **blocking reinstallation** (`isInstalled() == true`).
  - **`partial_installation`**: Only recognized if migrations have started but no academy or admin records exist, and no completion marker exists.
  - **`uninstalled`**: Clean, empty database with no migrations, tables, or records.

---

## 5. Partial Installation Recovery & Safe Resumption

If an installation is interrupted midway (e.g., server timeout, process interruption, or transient database disruption):

### 1. Resumable vs. Ambiguous States

| State | Condition | Installer Action | Data Impact |
|---|---|---|---|
| **Clean Partial Migrations** | Migrations table exists with official migrations; 0 academy records, 0 user records. | **Safely Resumes:** Continues remaining migrations, seeds roles, provisions academy and admin. | No existing data to conflict. |
| **Interrupted Setup (Matching Identity)** | Migrations completed; Academy and Admin partially created; `installation_identity` verified; submitted Academy slug/name match existing record; submitted Admin email matches existing record and submitted password matches `Hash::check()`. | **Safely Resumes:** Reuses existing Academy and Admin records non-destructively. Does NOT overwrite name, email, password, or active status. Finalizes marker and writes lock file. | Fully non-destructive; customer records are preserved. |
| **Missing Installation Identity** | Database contains matching SEO Academy tables and migrations, but lacks a valid `installation_identity` marker. | **Halts & Blocks:** Classified as `ambiguous`. Refuses automatic resumption to prevent accidental data collision or hijacking. | Untouched. |
| **Unrelated Database** | Database contains non-SEO Academy tables, unrecognized migrations, foreign identity marker, or `users.id` is not UUID. | **Halts & Blocks:** Throws actionable `RuntimeException`. Reinstallation rejected. | Untouched. |
| **Ambiguous Academy Identity** | Database has existing Academy record, but submitted slug or name differs. | **Halts & Blocks:** Refuses to alter or overwrite existing academy. Directs customer to provide correct credentials. | Untouched. |
| **Ambiguous Administrator Identity** | Database has existing users, but submitted email is unrecognized, or submitted password fails `Hash::check()`. | **Halts & Blocks:** Refuses to overwrite or hijack existing administrator credentials. | Untouched. |
| **Ambiguous Marker Inconsistency** | `system.installed_at` exists but `Academy` record missing, or `Academy` exists without marker. | **Halts & Blocks:** Installer stops; lock regeneration blocked until state is verified. | Untouched. |
| **Database Unavailable** | Configured database host or SQLite file unreachable. | **Fails Closed:** Blocks installation; returns database connection error. | Untouched. |

### 2. Recovery Procedures Without Customer Data Loss

When an installation is interrupted or encounters an ambiguous state:

#### A. When Database Credentials / Target Are Known
1. Check `storage/logs/laravel.log` to identify the exact cause of interruption (e.g., execution timeout, memory limit, or temporary connection dropped).
2. Re-visit `/install`.
3. Provide the **exact same Academy details and Administrator credentials** entered in the initial attempt.
4. The installer verifies positive association:
   - Validates that the submitted administrator email matches the existing user.
   - Validates the password using `Hash::check()` without altering stored hash values or user status.
   - Reuses the existing academy profile without overwriting customizations.
   - Completes remaining setup, writes the persistent `system.installed_at` marker, and writes `installed.lock`.

#### B. When an Ambiguous State Requires Manual Intervention
If someone attempted to install against an existing academy with mismatched credentials:
1. Verify database integrity via CLI:
   ```bash
   php artisan tinker --execute="echo App\Domain\Platform\Models\Academy::count() . ' academies, ' . App\Domain\User\Models\User::count() . ' users';"
   ```
2. If the database is an already-active academy whose lock was deleted:
   - Run CLI command or verify credentials: the installer will self-heal the lock once the administrator logs in or `isInstalled()` executes with full matching state.
   - Alternatively, regenerate the lock file manually:
     ```bash
     mkdir -p storage/app/private
     echo '{"installed_at":"'$(date -u +"%Y-%m-%dT%H:%M:%SZ")'","driver":"manual_recovery"}' > storage/app/private/installed.lock
     ```
3. If an existing database has tables and migrations but is missing the `installation_identity` marker (e.g. legacy installation or manual migration):
   - Verify the database is indeed your SEO Academy instance using tinker:
     ```bash
     php artisan tinker --execute="echo App\Domain\Platform\Models\Academy::first()?->name;"
     ```
   - If positive ownership is established, claim the database by inserting the identity marker:
     ```bash
     php artisan tinker --execute="DB::table('installation_identity')->updateOrInsert(['id' => 'app'], ['value' => 'seo-academy', 'created_at' => now()->toIso8601String()]); DB::table('installation_identity')->updateOrInsert(['id' => 'installation_uuid'], ['value' => (string) Illuminate\Support\Str::uuid(), 'created_at' => now()->toIso8601String()]); DB::table('installation_identity')->updateOrInsert(['id' => 'status'], ['value' => 'completed', 'created_at' => now()->toIso8601String()]);"
     ```
   - Ensure the lock file is written:
     ```bash
     mkdir -p storage/app/private
     echo '{"installed_at":"'$(date -u +"%Y-%m-%dT%H:%M:%SZ")'","driver":"manual_identity_recovery"}' > storage/app/private/installed.lock
     ```
4. If an incomplete test installation needs a clean restart:
   - Drop the test database or delete `storage/app/private/database.sqlite` (ONLY if no production data exists!).
   - Re-run `/install` from Stage 1.

---

## 6. Alternative CLI Installation

For automated or scripted deployments, the installation can be completed via CLI without the web wizard:

1. Configure `.env`:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
2. Set database parameters in `.env` (`DB_CONNECTION=sqlite` or `DB_CONNECTION=mysql`).
3. Run migrations and role seeders:
   ```bash
   php artisan migrate --force
   php artisan db:seed --class=RoleSeeder --force
   ```
4. Create the Academy and Initial Admin user (via artisan tinker or custom seeder).
5. Lock the installer manually:
   ```bash
   mkdir -p storage/app/private
   echo '{"installed_at":"'$(date -u +"%Y-%m-%dT%H:%M:%SZ")'","driver":"cli"}' > storage/app/private/installed.lock
   ```

---

## 7. Post-Installation Setup

### Cron Automation (Essential for Shared Hosting)
Add a single cron entry to trigger Laravel's scheduled background jobs (backup cleanup, enrollment expiration checks, notification pruning):
```bash
* * * * * cd /path/to/seo-academy && php artisan schedule:run >> /dev/null 2>&1
```

If background queue processing is required without a continuous daemon:
```bash
* * * * * cd /path/to/seo-academy && php artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
```

### Outgoing Mail Configuration
Update your `.env` file with your SMTP provider credentials:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="support@yourdomain.com"
MAIL_FROM_NAME="SEO Academy Support"
```

---

## 8. Troubleshooting & Common Scenarios

| Issue | Cause | Resolution |
|---|---|---|
| **`/install` redirects to `/login`** | `installed.lock` or initialized database exists | The application is already installed. Reinstallation is blocked to protect data. |
| **"The target database contains unrelated existing tables"** | Target MySQL or SQLite database contains non-SEO Academy tables (e.g. WordPress) | SEO Academy will not modify or overwrite an existing database. Provide a clean, dedicated database. |
| **"The target database already contains an initialized SEO Academy"** | Target database already contains an active academy instance | To protect existing academy data, reinstallation is blocked. |
| **"Server requirements are not met for the selected driver"** | Chosen database extension (e.g. `pdo_mysql` for MySQL or `pdo_sqlite` for SQLite) is missing | Enable the required extension in your `php.ini`. Note: SQLite does not require `pdo_mysql`, and MySQL does not require `pdo_sqlite`. |
| **"Engine is not InnoDB"** | MySQL database engine defaults to MyISAM or non-transactional engine | Ensure default storage engine is InnoDB in MySQL server (`default_storage_engine=InnoDB`). |
| **"Directory not writable" during pre-flight** | Permissions on `storage/` or `bootstrap/cache/` are too restrictive | Run `chmod -R 775 storage bootstrap/cache` or assign web server user ownership (`chown -R www-data:www-data storage bootstrap/cache`). |
| **"Installation interrupted midway"** | Execution timeout or connection disruption | Re-open `/install` and submit with the same credentials; the installer will verify positive association and resume safely. |
| **"The target database already contains existing user accounts"** | An existing user was found whose email does not match, or whose password fails `Hash::check()` | Reinstallation will not hijack or overwrite an existing administrator. Ensure you are entering the original administrator's credentials. |
| **"The target database contains unrelated existing tables or non-matching migrations"** | Database belongs to another application (or a different Laravel project) | Provide a dedicated, empty database for SEO Academy. Existing data is protected and untouched. |
| **"Installation state is ambiguous"** | Database has inconsistent state (e.g. marker exists without academy record) | Check database integrity via CLI (`php artisan tinker`). If healthy, generate `installed.lock` manually or restore from backup. |
| **"Database connection unavailable" during install check** | Database host down, credentials wrong in `.env`, or connection timed out | The system fails closed to prevent accidental exposure of `/install`. Restore database connectivity; `/install` will redirect to `/login` once connected. |
