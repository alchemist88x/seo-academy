# SEO Academy — System Requirements

This document outlines the hosting and server requirements for installing and operating **SEO Academy v5.4.0** in production environments.

---

## 1. Operating System & Server Environment

SEO Academy is designed for broad compatibility with both shared hosting environments (cPanel, Plesk, DirectAdmin) and standalone Virtual Private Servers (VPS) or cloud servers running Linux, macOS, or Windows.

- **Operating System:**
  - Linux (Ubuntu 22.04/24.04 LTS, Debian 11/12, AlmaLinux/Rocky Linux 9, RHEL 9) — *Recommended*
  - Windows Server / Laragon / IIS (with PHP FastCGI)
  - macOS (local development / testing)
- **Web Server:**
  - Nginx 1.20+ (with PHP-FPM) — *Recommended*
  - Apache 2.4+ (with `mod_rewrite`, `mod_headers`, and PHP-FPM / `mod_php`)
  - LiteSpeed / OpenLiteSpeed
- **Document Root:** Must point directly to the `/public` directory of the application.

---

## 2. PHP Runtime Requirements

- **PHP Version:** **PHP 8.3.0 or higher** (PHP 8.3.36+ recommended).
- **PHP Extensions Required:**
  - `bcmath` — High-precision arithmetic for gradebook calculations
  - `ctype` — Character type checking
  - `curl` — Remote API communication and license validation
  - `dom` — XML and HTML parsing (required for PDF generation)
  - `fileinfo` — Secure MIME-type detection for student uploads
  - `filter` — Input sanitization
  - `gd` or `imagick` — Image processing, avatar generation, and PDF rendering
  - `hash` — Cryptographic hashing (SHA-256 for certificate verification)
  - `intl` — Internationalization, number formatting, and multi-language support
  - `json` — JSON payload serialization
  - `mbstring` — Multi-byte character handling
  - `openssl` — AES-256 encryption for certificate tokens and sensitive credentials
  - `pcre` — Regular expressions
  - `pdo` — Database abstraction layer
  - `pdo_sqlite` — Required when utilizing the SQLite database engine
  - `pdo_mysql` — Required when utilizing the MySQL database engine
  - `session` — User authentication and session management
  - `tokenizer` — Blade template compilation
  - `xml` / `xmlwriter` — XML document processing
  - `zip` — Backup creation and restoration archives

### Recommended PHP Configuration (`php.ini`)
```ini
memory_limit = 256M
upload_max_filesize = 50M
post_max_size = 55M
max_execution_time = 120
max_input_time = 120
date.timezone = UTC
```

---

## 3. Database Engine Requirements

SEO Academy supports two primary database engines. You can choose either engine during installation:

### Option A: SQLite (Default — Recommended for Shared Hosting & Small Academies)
- **Engine:** SQLite 3.35.0 or higher.
- **Server Access:** Zero configuration required; operates directly from `storage/app/private/database.sqlite`.
- **Concurrency:** Suitable for up to 500 active concurrent students.

### Option B: MySQL (Recommended for Large Academies & Enterprise VPS)
- **Engine:** MySQL 8.4+ or MariaDB 10.11+.
- **Storage Engine:** **InnoDB** (Mandatory).
- **Default Charset:** `utf8mb4` with collation `utf8mb4_unicode_ci`.
- **Database Privileges:** `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `DROP`, `INDEX`, `ALTER`, `REFERENCES`.

---

## 4. Hardware Sizing Guidelines

| Deployment Tier | Active Students | CPU Cores | RAM | Storage | Database Engine |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Shared Hosting** | 1 – 150 | 1 vCPU | 1 GB | 10 GB SSD | SQLite 3 |
| **Standard VPS** | 100 – 1,000 | 2 vCPU | 2 GB – 4 GB | 40 GB SSD | MySQL 8.4 / SQLite |
| **Enterprise VPS** | 1,000 – 10,000+ | 4+ vCPU | 8 GB – 16 GB | 100 GB+ NVMe | MySQL 8.4 InnoDB |

---

## 5. Network & Browser Requirements

- **Inbound Ports:** Port 80 (HTTP) and Port 443 (HTTPS). HTTPS is strongly recommended for all production deployments.
- **Outbound Connectivity:** Standard outbound HTTPS (Port 443) for updates, and SMTP (Port 587 or 465) for email dispatch.
- **Client Browsers:** Modern evergreen browsers (Chrome 100+, Firefox 100+, Safari 15+, Edge 100+). JavaScript must be enabled for interactive classroom features and Livewire reactivity.
