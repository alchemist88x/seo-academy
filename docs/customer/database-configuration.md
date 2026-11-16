# SEO Academy — Database Configuration Guide

SEO Academy supports two robust database engines: **SQLite 3.35+** and **MySQL 8.4+ InnoDB**. This guide covers provisioning, optimizing, and securing each engine.

---

## 1. Engine Comparison

| Feature | SQLite 3 | MySQL 8.4+ / MariaDB 10.11+ |
| :--- | :--- | :--- |
| **Setup Complexity** | Zero configuration; single file | Requires database server and user account |
| **Ideal Hosting** | Shared hosting, small-to-medium LMS | VPS, cloud instances, enterprise LMS |
| **Concurrent Users** | Up to 500 active users | 10,000+ active users |
| **Storage Location** | `storage/app/private/database.sqlite` | Managed by MySQL server daemon |
| **Backups** | Native atomic file copy (`VACUUM INTO`) | Consistent single-connection transaction dump |

---

## 2. Option A: SQLite Configuration

SQLite is the default out-of-the-box database engine.

### File Location
In your `.env` file:
```dotenv
DB_CONNECTION=sqlite
# When DB_DATABASE is omitted, SEO Academy automatically binds to:
# storage/app/private/database.sqlite
```

### Security & Path Protection
Ensure that the directory `storage/app/private/` is **never** accessible directly via your web server. When following the recommended document root configuration (where the web server points to `/public`), files located in `storage/` are completely protected from external download.

### Concurrency Tuning
SEO Academy automatically sets the following pragmas for SQLite:
- `PRAGMA foreign_keys = ON;`
- `PRAGMA journal_mode = WAL;` (Write-Ahead Logging for high-throughput concurrency)
- `PRAGMA busy_timeout = 5000;` (5-second retry window to prevent lock timeouts)

---

## 3. Option B: MySQL 8.4+ InnoDB Configuration

For high-concurrency production deployments, MySQL 8.4+ (or MariaDB 10.11+) is recommended.

### 1. Creating Database and User Account
Connect to MySQL as root and execute:

```sql
CREATE DATABASE seo_academy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'seo_user'@'localhost' IDENTIFIED BY 'StrongPassword123!@#';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, REFERENCES 
ON seo_academy.* TO 'seo_user'@'localhost';

FLUSH PRIVILEGES;
```

### 2. Updating `.env`
Configure your MySQL credentials in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=seo_academy
DB_USERNAME=seo_user
DB_PASSWORD=StrongPassword123!@#
```

### 3. Server Configuration Recommendations (`my.cnf`)
Ensure the following settings are present in your MySQL server configuration:
```ini
[mysqld]
default_storage_engine = InnoDB
innodb_file_per_table = 1
innodb_buffer_pool_size = 1G # Adjust according to available server RAM
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
```
