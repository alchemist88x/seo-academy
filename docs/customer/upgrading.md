# SEO Academy — Customer Upgrade Guide

This guide describes how to safely upgrade your existing **SEO Academy** installation when a new version or maintenance release is published.

---

## 1. Upgrade Philosophy & Guarantees

SEO Academy follows strict non-destructive release engineering:
- **Additive Migrations:** Database migrations never drop customer course data, student enrollments, or grade records during version upgrades.
- **Automated Schema Evolution:** The built-in upgrade command automatically detects pending database updates, executes them in dependency order, and clears compiled caches.
- **Fail-Closed Protection:** Upgrades check system requirements before touching the database.

---

## 2. Recommended 5-Step Upgrade Workflow

### Step 1: Place Application into Maintenance Mode
To prevent students from taking quizzes or submitting assignments while files are being updated:
```bash
php artisan down --secret="my-upgrade-secret-token"
```
*(You can access the application during maintenance by visiting `https://your-domain.com/my-upgrade-secret-token`)*.

### Step 2: Take a Pre-Upgrade Backup
Always capture a snapshot before upgrading:
```bash
php artisan backup:run
```
Confirm the backup completed successfully in `storage/app/private/backups/`.

### Step 3: Extract New Release Files
Download the latest release archive (e.g. `seo-academy-v5.4.x.zip`) and overwrite your existing application files, **EXCEPT**:
- Do **not** overwrite `.env`.
- Do **not** overwrite `storage/` directory contents.

*(If uploading via cPanel File Manager, extract the zip over your installation directory; your existing `.env` and `storage/` directory will remain intact)*.

### Step 4: Run the Automated Upgrade Command
In your terminal, execute the unified upgrade command:
```bash
php artisan app:upgrade
```

The upgrade command automatically:
1. Validates PHP runtime and database extensions.
2. Verifies the existing installation sentinel.
3. Applies all pending additive database migrations (`migrate --force`).
4. Re-seeds any new system permissions or role updates.
5. Clears compiled routes, view templates, and configuration caches (`optimize:clear`).
6. Touches the upgrade sentinel `storage/app/private/upgraded.lock`.

### Step 5: Exit Maintenance Mode
Return the academy to live operation:
```bash
php artisan up
```

---

## 3. Post-Upgrade Verification

1. Log into your Administrator account.
2. Check **Admin Dashboard -> System Health** (`/admin/system/health`) to confirm that database connectivity, disk space, and queue status are green.
3. Verify that student course navigation and gradebook matrix load without errors.
