# Security Architecture — SEO Academy

SEO Academy is independently installed and operated by each customer. One installation supports exactly one academy.

SEO Academy enforces defense-in-depth across authentication, role-based access control (RBAC), single-academy isolation, private media streaming, and optional AI integrations.

---

## 1. 4-Role RBAC Matrix

Roles are seeded by `Database\Seeders\RoleSeeder` on the `web` guard using `spatie/laravel-permission` (`^8.5`) and assigned to `App\Models\User` (`HasRoles` trait). Academy role memberships are also recorded in the `academy_users` pivot table.

| Role | Scope | Dashboard Route | Route Middleware | Capabilities & Boundaries |
|---|---|---|---|---|
| `admin` | **Single-Academy** | `GET /admin/dashboard` (`admin.dashboard`, `AdminDashboard`) | `auth`, `academy.context`, `academy.access`, `role:admin` | Full management within the installation's academy (`AcademyPolicy`, `CoursePolicy`, `StudentProfilePolicy`, `TrainerProfilePolicy`, `AcademyUserPolicy`, `InvitationPolicy`, `SupportTicketPolicy`). |
| `trainer` | **Single-Academy** | `GET /trainer/dashboard` (`trainer.dashboard`, `TrainerDashboard`) | `auth`, `academy.context`, `academy.access`, `role:trainer` | Course, module, assignment, quiz, and student progress management for assigned courses within the academy; can access academy course media. |
| `support_staff` | **Single-Academy** | `GET /support/dashboard` (`support.dashboard`, `SupportDashboard`) | `auth`, `academy.context`, `academy.access`, `role:support_staff` | Dedicated support operations dashboard; scoped to the academy's support workflows. |
| `student` | **Enrollment-scoped** | `GET /student/dashboard` (`student.dashboard`, `StudentDashboard`) | `auth`, `academy.context`, `academy.access`, `role:student` | Accesses only own profile/tickets/submissions and media for courses where `Enrollment` exists in the academy. |

### Role Routing & Middleware
- `GET /dashboard` (`routes/web.php`), `LoginController::login()`, and `App\Livewire\Auth\Login::authenticate()` all route each role to its dedicated dashboard (`admin.dashboard`, `trainer.dashboard`, `support.dashboard`, or `student.dashboard`).
- Cross-role dashboard access is rejected with `403 Forbidden` by Spatie's `role` middleware.
- Public self-registration (`/register`) is disabled; users are provisioned by admins or via signed invitation links (`/invitations/{token}/accept`).
- Suspended users (`is_active = false`) are blocked at login and by `EnsureAcademyAccess` (`403 Forbidden`).
- Academy middleware aliases registered in `bootstrap/app.php`:
  - `academy.context` → `App\Http\Middleware\ResolveAcademyContext`
  - `academy.access` → `App\Http\Middleware\EnsureAcademyAccess`

---

## 2. Route Registration Hardening

Public self-registration (`/register`), academy switching (`/academy/switch/{academy}`), and demo showcase routes (`/demo`) are completely removed and not registered in any environment:

- In all environments (`local`, `production`, `staging`), `/demo` and `/register` return `404 Not Found`.
- User provisioning occurs strictly via the web installer (`/install`), authenticated administrator invitation (`/admin/members`, `/admin/trainers`, `/admin/students`), or token acceptance (`/invitations/{token}/accept`).

---

## 3. Private Media Security

Course videos, lesson PDFs, and class materials are never stored in `public/` or `storage/app/public/`.

### Private Disk & Webroot Isolation (`config/filesystems.php`)
- `private_media` disk points to `storage/app/private/media` (`driver => 'local'`, `serve => false`, `visibility => 'private'`).
- `storage/app/private/.htaccess` denies direct Apache HTTP access (`Require all denied` / `Deny from all`).
- Direct requests to `/storage/...` cannot reach private media.

### Authenticated Streaming (`PrivateMediaController` & `PrivateMediaService`)
All private media is served exclusively through `GET /media/{media}` (`routes/web.php` → `PrivateMediaController::show()` → `PrivateMediaService::stream()`), protected by `auth` middleware.

1. **Enrollment + Role Authorization (`PrivateMediaService::authorize()`):**
   - Suspended users (`!$user->is_active`) are denied (`403 Forbidden`).
   - `User`-owned media (`$media->model instanceof User`): authorized only if `$model->id === $user->id`.
   - **Course-linked media** (`Course`, `Module`, `Lesson`, `LessonMaterial`, `LiveClass`, `ClassMaterial`, `ClassVideo`, `Quiz`, `Assignment`):
     - `admin` and `trainer` roles in the academy are authorized.
     - `student` users are authorized **only** if an `Enrollment` exists for `$user->studentProfile` and `$courseId` (non-enrolled students receive `403 Forbidden`).
   - **Academy-linked media** (`Academy` or models with `academy_id`): authorized for active users belonging to the installation's academy.

2. **`realpath()` Path Traversal Guard (`PrivateMediaService::resolveFilePath()`):**
   - Rejects non-`private_media` disks, empty paths, and `..` segments (`404`).
   - Resolves `realpath($disk->path(''))` and `realpath($disk->path($relativePath))` with normalized forward slashes, verifying that the resolved file path is strictly contained within the `private_media` root directory. Symlinks or traversal attempts escaping the root return `404`.

3. **Byte-Range Streaming (`206` & `416`):**
   - `PrivateMediaService::streamByteRange()` supports single-range HTTP `Range: bytes=start-end` requests for HTML5 `<video>` seeking, returning `206 Partial Content` with `Accept-Ranges: bytes`, `Content-Range`, and `Content-Length`.
   - Invalid, multi-range/overlapping (e.g., `bytes=0-5,10-15`), suffix `<= 0`, or out-of-bounds ranges return `416 Range Not Satisfiable` with `Content-Range: bytes */{fileSize}`.

4. **MIME Allowlist & `nosniff`:**
   - Only `SAFE_INLINE_MIMES` (`video/mp4`, `video/webm`, `application/pdf`, `image/jpeg`, `image/png`, `image/webp`, `text/plain`) are served with `Content-Disposition: inline`.
   - Any other MIME type (e.g., `text/html`, `image/svg+xml`) is forced to `Content-Type: application/octet-stream` and `Content-Disposition: attachment` to prevent stored XSS.
   - All responses include `X-Content-Type-Options: nosniff` and `Cache-Control: private, max-age=3600`.

---

## 4. Optional AI Security

The AI assistant is optional, provider-independent (`null`, `ollama`, `openai_compatible`), and disabled by default.

- **Disabled by Default:**
  - `config/lms.php` sets `ai_enabled => (bool) env('LMS_AI_ENABLED', false)` and `ai_driver => env('LMS_AI_DRIVER', 'null')`.
  - When disabled or set to `null`, `AppServiceProvider` binds `NullAiProvider`, which makes zero outbound network calls.
- **Per-Tenant Encrypted Credentials (`AcademyAiCredential`):**
  - `App\Domain\AI\Models\AcademyAiCredential` (`academy_ai_credentials` table) stores per-academy `driver`, `endpoint`, `model`, `api_key`, and `is_active` (default `false`), with a unique `[academy_id, driver]` constraint.
  - `api_key` uses Laravel's `'encrypted'` cast (encrypted at rest via `APP_KEY`) and is listed in `$hidden = ['api_key']` so it is never exposed in `toArray()` or `toJson()`.
  - Uses `BelongsToAcademy` (`academy_id` FK with cascade delete) for strict tenant isolation.
- **No Frontend Key Exposure:**
  - All provider HTTP requests are executed server-side in `OpenAiCompatibleProvider` (`Illuminate\Support\Facades\Http`); API keys are never sent to Blade views, Livewire public properties, or browser JS.
- **Rate Limiting & Input Caps (`AiAssistantService::ask()`):**
  - Rejects empty prompts and enforces `config('lms.max_input_characters', 4000)` before any provider call.
  - Enforces per-user rate limiting via `Illuminate\Support\Facades\RateLimiter` (`ai-assistant:{userId}`, `config('lms.max_messages_per_minute', 10)`), throwing `TooManyRequestsHttpException` when exceeded.
- **No Silent Cost on Provider Switch:**
  - `AiAssistantService::ask()` explicitly checks `config('lms.ai_enabled', false)` before invoking the bound provider—switching `LMS_AI_DRIVER` or configuring an endpoint/key cannot trigger paid API calls while `LMS_AI_ENABLED=false`.
  - `AiAssistantService::isEnabled(?AcademyAiCredential $credential)` requires both `lms.ai_enabled === true` and `$credential->is_active === true` (when a credential is supplied).
