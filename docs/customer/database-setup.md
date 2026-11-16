# SEO Academy — Database Selection & Configuration Guide

SEO Academy supports two database engines out of the box: **SQLite 3.35+** and **MySQL 8.4+ InnoDB**.

Both database engines are fully configurable through the browser installation wizard without needing command-line intervention.

---

## 1. Comparing SQLite and MySQL

| Feature | SQLite 3.35+ (Recommended Default) | MySQL 8.4+ InnoDB |
|---|---|---|
| **Target Hosting** | Shared hosting, cPanel, VPS, single servers | High-traffic VPS, dedicated database servers |
| **Setup Complexity** | Zero configuration &mdash; automatic file creation | Requires database server, user, and credentials |
| **Location** | `storage/app/private/database.sqlite` | Remote or local MySQL service (port 3306) |
| **Backup Simplicity** | 100% portable file included in backup archives | Requires mysqldump or database server export |
| **Concurrency** | Optimized for small-to-mid academy cohorts | Built for large concurrent student cohorts |
| **Transactions & Integrity** | Foreign keys & ACID compliant | Foreign keys & ACID compliant |

---

## 2. Option A: SQLite Setup (Zero-Terminal)

SQLite is the default and recommended engine for most self-hosted academies.

### How It Works:
1. In the Installation Wizard (Step 4), select **SQLite**.
2. The installer automatically verifies:
   - The PHP `pdo_sqlite` extension is enabled.
   - The parent folder `storage/app/private/` exists and is writable.
   - The database file `storage/app/private/database.sqlite` is created with safe permissions.
   - The installed SQLite library version is >= 3.35.0.
3. The absolute path is saved into `.env` automatically:
   ```dotenv
   DB_CONNECTION=sqlite
   DB_DATABASE=/path/to/project/storage/app/private/database.sqlite
   ```

> [!SECURITY]
> The SQLite database is always placed under `storage/app/private/` outside the web root. It can never be downloaded or accessed directly through HTTP.

---

## 3. Option B: MySQL 8.4+ Setup

For organizations deploying with an existing MySQL database cluster or expecting high concurrent traffic:

### Prerequisites:
- MySQL version 8.4 or later.
- InnoDB storage engine enabled (default in MySQL 8.4+).
- A dedicated database and database user with permissions: `CREATE`, `ALTER`, `DROP`, `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `INDEX`, `REFERENCES`.

### Wizard Configuration:
1. In Step 4, select **MySQL 8.4+ InnoDB**.
2. Provide the connection credentials:
   - **Host:** `127.0.0.1` (or your remote database hostname).
   - **Port:** `3306` (standard MySQL port).
   - **Database Name:** e.g. `seo_academy`.
   - **Username:** e.g. `academy_user`.
   - **Password:** Your database user password.
3. Click **Test Connection** to verify credentials, server version, and InnoDB engine support before proceeding.
4. When you save, the installer securely writes the credentials to `.env`.

---

## 4. Database Switching Protection

SEO Academy protects your data from accidental loss:

- If you switch database drivers during the wizard after tables have been initialized, the installer detects the existing records.
- Switching will **not** silently overwrite or delete the previous database.
- You must check the explicit confirmation box to switch drivers.
- Existing records are retained in their respective databases.
