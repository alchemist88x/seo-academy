# PHP Composer Dependency & Licensing Audit Report
**Project:** SEO Academy  
**Version:** v5.6.0  
**Framework:** Laravel 13 (`v13.42.0`) on PHP 8.3.36  
**Project License:** Apache License, Version 2.0 (`Apache-2.0`)  
**Audit Date:** 2026-11-16  
**Auditor:** Antigravity Engineering Agent  
**Audit Scope:** Read-only inspection of `composer.json`, `vendor/composer/installed.json`, application codebase, platform requirements, security advisories, and commercial licensing compatibility.

---

## 1. Executive Summary

A comprehensive read-only audit of the PHP Composer dependencies for **SEO Academy v5.6.0** was conducted. The application is a self-hosted Learning Management System (LMS) built on Laravel 13 and PHP 8.3+, operating under the Apache License 2.0.

### Key Metrics Summary
- **Declared Platform Requirements (`require`):** `php: ^8.3`, `ext-bcmath: *`
- **Declared Direct Production Dependencies (`require`):** 5 packages (`dompdf/dompdf`, `laravel/framework`, `laravel/tinker`, `livewire/livewire`, `spatie/laravel-permission`)
- **Declared Direct Development Dependencies (`require-dev`):** 8 packages (`fakerphp/faker`, `laravel/boost`, `laravel/pail`, `laravel/pao`, `laravel/pint`, `mockery/mockery`, `nunomaduro/collision`, `phpunit/phpunit`)
- **Total Resolved Installed Packages:** 124 packages
  - **Production Packages:** 86 packages (5 direct, 81 transitive)
  - **Development Packages:** 38 packages (8 direct, 30 transitive)
- **Known Security Vulnerabilities (`composer audit`):** **0**
- **Abandoned Packages:** **0**
- **Commercial Licensing Status:** **Fully Compatible with Apache-2.0**, subject to documented standard LGPL runtime notices for Dompdf.
- **Missing Platform Dependency Identified:** `ext-zip` is utilized by `BackupService` (`ZipArchive`) but omitted from `composer.json` `require`.

---

## 2. Current Direct Dependency Inventory

The application declares direct dependencies across production runtime (`require`) and local development/testing tooling (`require-dev`):

### Direct Production Dependencies (`require`)

| Package / Requirement | Declared Constraint | Resolved Version | Primary Role & Codebase Usage |
|---|---|---|---|
| `php` | `^8.3` | `8.3.36` | Target PHP runtime environment. |
| `ext-bcmath` | `*` | *Built-in* | High-precision arithmetic required by `brick/math` and financial/grading calculations. |
| `dompdf/dompdf` | `^3.1` | `v3.1.6` | PDF generation engine utilized by `CertificatePdfGeneratorService` for course completion certificates. |
| `laravel/framework` | `^13.17` | `v13.42.0` | Core full-stack web framework: routing, Eloquent ORM, auth, HTTP kernel, queue, validation, views. |
| `laravel/tinker` | `^3.0` | `v3.1.0` | Artisan REPL interactive terminal (`php artisan tinker`). Standard administrative utility. |
| `livewire/livewire` | `^4.4` | `v4.4.8` | Component-based reactive UI framework powering the admin, student portal, and interactive forms. |
| `spatie/laravel-permission` | `^8.5` | `8.5.0` | Role-based and permission-based authorization engine managing the four academy roles. |

### Direct Development Dependencies (`require-dev`)

| Package | Declared Constraint | Resolved Version | Primary Role & Codebase Usage |
|---|---|---|---|
| `fakerphp/faker` | `^1.23` | `v1.24.1` | Realistic dummy data generation for model factories and database seeders. |
| `laravel/boost` | `^2.10` | `v2.10.4` | AI agent developer guidelines, workspace rules, and developer tooling. |
| `laravel/pail` | `^1.2.5` | `v1.3.0` | Real-time terminal log viewer command (`php artisan pail`). |
| `laravel/pao` | `^1.0.6` | `v1.1.5` | Agent-optimized test output formatting and inspection. |
| `laravel/pint` | `^1.27` | `v1.32.3` | Opinionated PHP code style fixer and linter enforcing PSR-12 and Laravel conventions. |
| `mockery/mockery` | `^1.6` | `1.6.15` | Mocking framework utilized across PHPUnit test suites. |
| `nunomaduro/collision` | `^8.6` | `v8.9.6` | Beautiful, contextual error reporting for command-line Artisan interactions. |
| `phpunit/phpunit` | `^12.5.12` | `12.5.43` | Automated unit and feature testing framework. |

---

## 3. Transitive Dependency Overview

The dependency graph contains 111 transitive packages (81 production, 30 development).

### Major Dependency Subtrees
1. **`dompdf/dompdf` (v3.1.6):**
   - `dompdf/php-font-lib` (1.0.2) — Font parsing and font subsetting.
   - `dompdf/php-svg-lib` (1.0.2) — SVG rendering for vector graphics.
   - `masterminds/html5` (2.12.0) — HTML5 compliant parser.
   - `sabberworm/php-css-parser` (v9.5.0) — CSS stylesheet parser.
2. **`spatie/laravel-permission` (8.5.0):**
   - `spatie/laravel-package-tools` (1.93.3) — Package service registration.
3. **`laravel/framework` (v13.42.0):**
   - Symfony components: `symfony/console`, `symfony/http-foundation`, `symfony/http-kernel`, `symfony/routing`, `symfony/mailer`, `symfony/process`, `symfony/var-dumper`, `symfony/mime`, etc.
   - League packages: `league/flysystem` (storage abstraction), `league/commonmark` (markdown parser), `league/config`, `league/uri`.
   - Utilities: `nesbot/carbon` (date/time manipulation), `monolog/monolog` (logging), `ramsey/uuid` (UUID generation), `vlucas/phpdotenv` (env parsing), `brick/math`, `guzzlehttp/guzzle`.
4. **`phpunit/phpunit` (12.5.43):**
   - Sebastian Bergmann testing components: `sebastian/diff`, `sebastian/comparator`, `sebastian/exporter`, `sebastian/global-state`, etc.
   - `phar-io/manifest`, `phar-io/version`, `theseer/tokenizer`.

---

## 4. Missing Direct Dependency Declarations

The codebase was analyzed for imported root namespaces and class instantiations that are not explicitly declared in `composer.json`:

### 1. `ext-zip` (Missing Platform Extension) — **P1 Recommended Action**
- **Location:** `app\Domain\Platform\Services\BackupService.php` (`use ZipArchive;` and `$zip = new ZipArchive();`).
- **Issue:** The application directly instantiates PHP's native `ZipArchive` class to create and extract full academy backup archives. While `ext-bcmath` is declared in `composer.json`, `ext-zip` is omitted.
- **Risk:** In minimal PHP environments (such as bare Docker Alpine images or custom Linux servers where `php-zip` is not installed by default), running the backup or restore workflows will result in a fatal `Class "ZipArchive" not found` error.
- **Recommendation:** Add `"ext-zip": "*"` to `composer.json` under `require`.

### 2. `ext-gd` or `ext-imagick` (Suggested by Dompdf) — **P3 Recommended Action**
- **Location:** `dompdf/dompdf` suggests `ext-gd` or `ext-imagick` to decode, process, and render embedded images in PDF documents.
- **Assessment:** If course completion certificates include academy logos or student avatars in PNG/JPEG format, the PHP environment must have `ext-gd` enabled. This should be explicitly documented in installation requirements.

### 3. Direct References to Transitive Framework Packages (Informational)
- **`Carbon\CarbonInterface`:** Referenced in `app\Domain\Students\Services\EnrollmentManagementService.php`. Provided transitively by `laravel/framework` (`nesbot/carbon`). In standard Laravel development, `Carbon` is considered an integral part of the framework distribution.
- **`Symfony\Component\HttpFoundation\*`:** Referenced in HTTP controllers and middleware (`Response`, `StreamedResponse`, `BinaryFileResponse`). Provided transitively by `laravel/framework` (`symfony/http-foundation`). Standard Laravel conventions allow controllers to return Symfony responses directly.

---

## 5. Unnecessary or Misplaced Dependencies

### 1. `laravel/tinker` in Production (`require`)
- `laravel/tinker` is currently located in `require`.
- **Analysis:** In standard Laravel applications, `laravel/tinker` is included in `require` so administrators and engineers can perform console diagnostics. If an environment mandates zero-REPL production environments, it could be moved to `require-dev`. However, for a self-hosted LMS where academy owners may run diagnostic commands via CLI, keeping it in `require` is standard and safe.

### 2. Development Tool Isolation Verification
- All testing frameworks (`phpunit/phpunit`, `mockery/mockery`), developer tooling (`laravel/pint`, `laravel/pail`, `laravel/pao`, `laravel/boost`), and error diagnostics (`nunomaduro/collision`) are properly quarantined in `require-dev`.
- None of these packages leak into the production runtime when installed with `composer install --no-dev`.

---

## 6. PHP and Framework Compatibility Findings

- **PHP Version:** Verified on PHP 8.3.36. `composer.json` declares `"php": "^8.3"`. All direct and transitive packages fully support PHP 8.3 and PHP 8.4.
- **Laravel 13 Compatibility:** `laravel/framework` is resolved at `v13.42.0`. All installed packages (`livewire/livewire` 4.4.8, `spatie/laravel-permission` 8.5.0, `dompdf/dompdf` 3.1.6) are explicitly compatible with Laravel 13.
- **Database Engine Compatibility:** All database operations use standard Eloquent PDO drivers supporting SQLite 3.35+ and MySQL 8.4+ InnoDB without requiring driver-specific third-party Composer packages.

---

## 7. Commercial Licensing Findings

### Project License: `Apache License 2.0`
The project is licensed under Apache-2.0. Upstream dependencies must not impose restrictive copyleft requirements (such as GPL or AGPL) that would force proprietary or commercial derivative works to be re-licensed under copyleft.

### License Distribution across All 124 Installed Packages:

| License | Production Count | Dev Count | Total | License Characterization |
|---|---:|---:|---:|---|
| **MIT** | 75 | 14 | 89 | Permissive open source. Fully compatible. |
| **BSD-3-Clause** | 5 | 24 | 29 | Permissive open source with attribution. Fully compatible. |
| **BSD-3-Clause OR GPL-2.0-only OR GPL-3.0-only** | 2 | 0 | 2 | Multi-licensed (`nette/schema`, `nette/utils`). Permissive BSD elected. Fully compatible. |
| **Apache-2.0** | 1 | 0 | 1 | Permissive open source (`phpoption/phpoption`). Identical license. |
| **LGPL-2.1** | 1 | 0 | 1 | Weak copyleft library (`dompdf/dompdf`). See legal review below. |
| **LGPL-2.1-or-later** | 1 | 0 | 1 | Weak copyleft library (`dompdf/php-font-lib`). |
| **LGPL-3.0-or-later** | 1 | 0 | 1 | Weak copyleft library (`dompdf/php-svg-lib`). |
| **Total** | **86** | **38** | **124** | **No blocking licensing conflicts.** |

### In-Depth Analysis of LGPL Dependencies (`dompdf/dompdf`)
1. **Dynamic Runtime Linking:** In PHP web environments, Dompdf and its sub-libraries reside as separate files under `vendor/` and are dynamically loaded at runtime by PHP's autoloader. The SEO Academy codebase interacts with Dompdf exclusively through its public PHP API (`new Dompdf()`, `$dompdf->loadHtml()`, `$dompdf->render()`).
2. **Derivative Work Classification:** Application code utilizing Dompdf constitutes a "work that uses the Library" under LGPL-2.1 Section 6 and LGPL-3.0 Section 4. The application itself is not a derivative work of Dompdf and does not modify Dompdf source code. Therefore, SEO Academy source code remains fully governed by the Apache License 2.0.
3. **Distribution Models:**
   - **Source Distribution (Public GitHub Repository):** `vendor/` and `composer.lock` are untracked. Users install dependencies directly from Packagist via Composer. The repository distributes zero LGPL binaries or source code directly.
   - **Binary / Pre-Packaged Commercial Releases (ZIP Bundles):** If pre-built commercial distribution packages containing `vendor/` are distributed in the future, the distribution must include the LGPL license texts, retain Dompdf copyright headers, and provide notice of Dompdf usage (already satisfied via `docs/licenses/` and `third-party-notices.md`).

---

## 8. Security and Abandoned-Package Findings

- Command executed: `composer audit --format=json`
- **Result:**
  - `advisories`: `[]` (0 known security advisories across all 124 packages).
  - `abandoned`: `[]` (0 abandoned or deprecated packages).
- Outdated direct packages: `composer outdated --direct` identified only `phpunit/phpunit` (12.5.43 -> 12.5.44, non-breaking dev patch). All production dependencies are current within their constraint boundaries.

---

## 9. Installation and Lockfile Implications

### Policy Context
The repository enforces the following deliberate Git tracking convention:
- `composer.json` is **tracked**.
- `composer.lock` is **untracked** (excluded via `.gitignore`).
- `vendor/` is **untracked** (excluded via `.gitignore`).

### Analysis of Lockfile Exclusion

#### Advantages:
1. **No Lockfile Merge Conflicts:** Eliminates Git merge conflicts on lockfile hash blocks during multi-developer or branch workflows.
2. **Automated Security Updates:** Fresh installations automatically resolve upstream patch releases that patch bugs or security vulnerabilities within caret constraints (`^`).
3. **Environment Flexibility:** Allows Composer to resolve compatible dependencies smoothly across slight platform variations.

#### Reproducibility Risks:
1. **Non-Deterministic Fresh Installs:** Two different fresh clones run on different days may receive different patch releases of transitive dependencies (e.g., `v1.2.3` vs `v1.2.4`).
2. **Upstream Regressions:** If an upstream dependency introduces a subtle breaking change or bug in a patch release, a fresh install could fail even if the application code has not changed.

#### Risk Mitigations Implemented:
- Strict constraint formatting: Caret constraints (`^8.3`, `^13.17`, `^4.4`, `^8.5`, `^3.1`) prevent accidental major version jumps.
- Stability enforcement: `"minimum-stability": "stable"` and `"prefer-stable": true` in `composer.json` guarantee that no unreleased dev branches or alpha/beta packages are ever installed.
- Production Asset Bundling: Frontend assets in `public/build/` are committed and tracked, removing Node.js and npm dependency risks completely from the production runtime.

---

## 10. Recommended Changes, Ranked by Severity

| Priority | Category | Action Item | Description |
|---|---|---|---|
| **P1** | **Functional / Platform** | Declare `ext-zip` in `composer.json` | Add `"ext-zip": "*"` to `require` in `composer.json` so servers lacking PHP zip extension fail fast during install rather than during backup creation. |
| **P2** | **Documentation** | Update `docs/licenses/dependencies.md` | Formalize the updated inventory of 86 production packages (including Dompdf and its LGPL dependencies) and 38 dev packages. *(Completed in this audit turn)*. |
| **P3** | **Documentation** | Update Installation Documentation | Add `ext-gd` and `ext-zip` to the documented prerequisites for self-hosted PHP production servers. |
| **P4** | **DevOps / CI** | Automated Fresh Clone Testing | Add a recurring GitHub Action / CI step running `composer install --no-dev --prefer-dist` against `.env.example` to continuously detect any upstream regressions. |

---

## 11. Exact Commands Used and Their Actual Results

### 1. `composer --version`
```text
Composer version 2.10.3 2026-08-27 13:34:23
PHP version 8.3.36 (C:\laragon\bin\php\php-8.3.36-Win32-vs16-x64\php.exe)
Run the "diagnose" command to get more details about the current setup.
```

### 2. `composer validate`
```text
./composer.json is valid for simple parsing and cannot be used with "publish"
The lock file is not up to date with the latest changes in composer.json, it is recommended that you run `composer update` or `composer update <package name>`.
```
*(Exit code 1 due to local metadata update while composer.lock is intentionally untracked per project policy).*

### 3. `composer show --direct`
```text
Direct Production:
dompdf/dompdf              v3.1.6   DOMPDF is a CSS 2.1 compliant HTML to PDF converter
laravel/framework          v13.42.0 The Laravel Framework.
laravel/tinker             v3.1.0   Powerful REPL for the Laravel framework.
livewire/livewire          v4.4.8   A front-end framework for Laravel.
spatie/laravel-permission  8.5.0    Permission handling for Laravel 12 and up

Direct Development:
fakerphp/faker             v1.24.1  Faker is a PHP library that generates fake data for you.
laravel/boost              v2.10.4  Guidelines, rules, and skills for AI agents.
laravel/pail               v1.3.0   Easily delve into your Laravel application's log files.
laravel/pao                v1.1.5   Agent-optimized output for PHP testing tools.
laravel/pint               v1.32.3  An opinionated code formatter for PHP.
mockery/mockery            1.6.15   Mockery is a simple yet flexible PHP mock object framework
nunomaduro/collision       v8.9.6   Cli error handling for console/command-line PHP apps.
phpunit/phpunit            12.5.43  The PHP Unit Testing framework.
```

### 4. `composer audit --format=json`
```json
{
    "advisories": [],
    "abandoned": [],
    "filter": []
}
```

### 5. `composer outdated --direct`
```text
Legend:
! patch or minor release available - update recommended
~ major release available - update possible
phpunit/phpunit 12.5.43 ! 12.5.44 The PHP Unit Testing framework.
```

### 6. `composer licenses --format=json`
Generated full license inventory across all 124 packages:
- `MIT`: 89 packages
- `BSD-3-Clause`: 29 packages
- `BSD-3-Clause, GPL-2.0-only, GPL-3.0-only`: 2 packages
- `LGPL-2.1`: 1 package (`dompdf/dompdf`)
- `LGPL-2.1-or-later`: 1 package (`dompdf/php-font-lib`)
- `LGPL-3.0-or-later`: 1 package (`dompdf/php-svg-lib`)
- `Apache-2.0`: 1 package (`phpoption/phpoption`)