# Deployment & Security Architecture — SEO Academy

SEO Academy is independently installed and operated by each customer. One installation supports exactly one academy.

## 1. FOSS-First Stack (Zero Proprietary Runtime Lock-In)

SEO Academy is built exclusively on Free and Open-Source Software (FOSS) components with no mandatory proprietary SaaS or closed-source runtime dependencies:

| Layer | Technology | License / Role |
|---|---|---|
| **Runtime & Framework** | PHP 8.3+, Laravel 11 | MIT — modular monolith backend, routing, queues, Eloquent ORM |
| **UI & Interactivity** | Livewire, Tailwind CSS, Alpine.js | MIT — server-driven reactive UI; assets pre-built at deploy time |
| **Authorization** | `spatie/laravel-permission` | MIT — role-based access control (`admin`, `trainer`, `support_staff`, `student`) |
| **Database** | SQLite 3.35+ / MySQL 8.0+ (InnoDB) | Public Domain / GPL — dual-engine support via standard PDO |
| **Testing** | PHPUnit | BSD-3-Clause — unit and feature test suite |

All frontend assets are compiled into `public/build/` during CI or local build, so production servers do **not** require Node.js or npm at runtime.

---

## 2. Shared-Hosting Compatibility

The application is designed to run cleanly on standard PHP/Apache/Nginx shared hosting without root access, Docker, Redis, or persistent daemon supervisors:

- **Database-backed runtime drivers:**
  - `QUEUE_CONNECTION=database`
  - `SESSION_DRIVER=database`
  - `CACHE_STORE=database`
- **Cron-driven scheduler & queue processing:**
  ```cron
  * * * * * cd /path/to/seo-academy && php artisan schedule:run >> /dev/null 2>&1
  * * * * * cd /path/to/seo-academy && php artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
  ```
- **No external runtime daemons required:** No Redis, Memcached, Horizon, Octane, Node.js, or Docker containers are needed in production.
- **Document root:** Point the web server's document root to `public/`. Sensitive directories (`storage/`, `.env`, `vendor/`) remain outside the web root, with `.htaccess` protection inside `storage/app/private/` as defense in depth.

---

## 3. Single-Academy Isolation & Roles

SEO Academy runs as a single-academy self-hosted installation with strict role boundaries:

- **Active Academy Context (`TenantContext`):**
  - `App\Domain\Tenants\Services\TenantContext` resolves the installation's single `Academy` via `Academy::current()` (`getAcademy()`, `setAcademy()`, `id()`, `clear()`).
  - `Academy` enforces a singleton guard in `booted()` so attempting to create a second academy throws `\DomainException`.
  - `ResolveAcademyContext` middleware (`academy.context`) initializes `TenantContext` from `Academy::current()`.
  - `EnsureAcademyAccess` middleware (`academy.access`) verifies that the user is authenticated, active (`is_active === true`), and an active academy exists, returning `403 Forbidden` otherwise.
- **Role Memberships (`academy_users`):**
  - Users are linked to the installation's academy via `academy_users` and assigned one of the 4 Spatie roles (`admin`, `trainer`, `support_staff`, `student`).
  - `User::belongsToAcademy()`, `User::academyRole()`, and `User::hasAcademyRole()` provide consistent membership and role checks.
  - Public self-registration (`/register`), academy switching (`/academy/switch/{academy}`), and demo routes (`/demo`) are disabled/not registered.
- **Model Scoping & Authorization Policies:**
  - Academy-owned models (`Course`, `StudentProfile`, `SupportTicket`, `AcademyNotification`, `AiFaq`, `AiMaterial`) use the `App\Domain\Tenants\Concerns\BelongsToAcademy` trait (`academy()` relation and `scopeForAcademy()` query scope).
  - `AcademyPolicy`, `CoursePolicy`, `StudentProfilePolicy`, `TrainerProfilePolicy`, `AcademyUserPolicy`, `InvitationPolicy`, and `SupportTicketPolicy` enforce role-based access within the academy.

---

## 4. Private Media & Byte-Range Video Streaming

Course videos, lesson PDFs, and backups are stored **outside** `public/` so direct URL guessing cannot bypass enrollment or tenant checks:

- **Private Disks (`config/filesystems.php`):**
  - `private_media` → `storage_path('app/private/media')` (`driver => 'local'`, `serve => false`, `visibility => 'private'`)
  - `private_backups` → `storage_path('app/private/backups')` (`driver => 'local'`, `serve => false`, `visibility => 'private'`)
- **Authenticated Streaming (`PrivateMediaController` & `PrivateMediaService`):**
  - `GET /media/{media}` requires authentication (`auth` middleware; guests redirect to `/login`).
  - `PrivateMediaService` authorizes the request by checking active user status (`is_active`), `admin`/`trainer` role, or student enrollment before reading from `private_media` (returning `403 Forbidden` for unauthorized users).
  - Supports HTTP `Range` headers (e.g., `Range: bytes=0-9`) for HTML5 `<video>` seeking and resumable playback, returning `206 Partial Content` with `Accept-Ranges: bytes`, `Content-Range`, and `Content-Length`, or `200 OK` when no range is requested.

---

## 5. Optional, Provider-Independent AI

The AI assistant is completely optional and decoupled from any single vendor:

- **Configuration (`config/lms.php` & `.env`):**
  - `LMS_AI_ENABLED=false` (disabled by default; no API keys required to install or run the LMS).
  - `LMS_AI_DRIVER=null|ollama|openai_compatible`
  - `LMS_AI_MAX_INPUT_CHARS` enforces input length limits before any provider call.
- **Provider Abstraction (`AiProviderInterface`):**
  - `App\Domain\AI\Contracts\AiProviderInterface` is bound in `AppServiceProvider::register()`.
  - When `lms.ai_enabled` is `false` or `driver` is `null`, `NullAiProvider` is resolved and returns a graceful disabled message without making network requests.
  - `OpenAiCompatibleProvider` communicates over standard HTTP (`Illuminate\Support\Facades\Http`) with either a local **Ollama** instance or any **OpenAI-compatible** endpoint, avoiding proprietary SDK lock-in.
  - `AiAssistantService` enforces `max_input_characters` and delegates generation to the active `AiProviderInterface` implementation.
