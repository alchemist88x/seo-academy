# Single-Academy Architecture — SEO Academy

SEO Academy is independently installed and operated by each customer. One installation supports exactly one academy.

---

## 1. Singleton Academy Model & Context Resolution

Academy context is managed per request by `App\Domain\Tenants\Services\TenantContext` (registered as a request-scoped singleton in `AppServiceProvider::register()`) and initialized by `App\Http\Middleware\ResolveAcademyContext` (`academy.context`).

1. **Single-Academy Guard (`Academy::booted()`):**
   - `App\Domain\Academies\Models\Academy` enforces a singleton constraint in its `creating` model event: if an `Academy` record already exists in the database, attempting to create a second one throws `\DomainException`.
   - `Academy::current()` returns the single active academy (or falls back to the first `Academy` record).
2. **Deterministic Context Resolution (`TenantContext::getAcademy()`):**
   - `TenantContext::getAcademy()` always resolves `Academy::current()`, regardless of HTTP `Host` headers, subdomains, or session values.
   - `TenantContext::isPlatformScope()` and `TenantContext::isResolvedFromHost()` always return `false`.
   - `TenantContext::resolveAcademyFromHost()` returns `Academy::current()`.
3. **No Academy Switching or Public Registration:**
   - The `POST /academy/switch/{academy}` route does not exist (`404 Not Found`).
   - Public self-registration (`POST /register`) does not exist (`404 Not Found`); users are provisioned by an `admin` or via signed invitation links (`GET /invitations/{token}/accept`).

---

## 2. Access Control & Middleware Rules

- **Never Trust Client-Supplied Academy IDs:** Query parameters, request headers, session keys (`current_academy_id`), and form body `academy_id` values cannot switch or override the installation's academy.
- **Route Middleware Enforcement (`EnsureAcademyAccess`):** The `academy.access` middleware (`App\Http\Middleware\EnsureAcademyAccess`) verifies:
  1. The user is authenticated (`$user !== null`).
  2. The user account is active (`$user->is_active === true`; suspended users receive `403 Forbidden`).
  3. An active `Academy` exists in `TenantContext::getAcademy()` (`403 Forbidden` otherwise).
- **4-Role RBAC (`admin`, `trainer`, `support_staff`, `student`):**
  - There is no `platform_admin` or `academy_admin` role; `admin` is the sole administrative role for the installation.
  - Authorization policies (`AcademyPolicy`, `AcademyUserPolicy`, `CourseCategoryPolicy`, `CoursePolicy`, `ModulePolicy`, `LessonPolicy`, `InvitationPolicy`, `StudentProfilePolicy`, `TrainerProfilePolicy`, `SupportTicketPolicy`) enforce role-based and ownership/enrollment-based boundaries within the single academy.

---

## 3. Domain Scoping Inventory

All academy-owned domain models continue to carry `academy_id` (referencing the installation's `Academy`) and use `BelongsToAcademy` (`scopeForAcademy()`) for consistent relational integrity:

| # | Area | Table(s) | Model(s) | Scoping & Authorization |
|---|---|---|---|---|
| 1 | **Academy** | `academies` | `App\Domain\Academies\Models\Academy` | Singleton (`Academy::current()`); `AcademyPolicy` restricts updates to `admin`. |
| 2 | **Memberships & Invitations** | `academy_users`, `invitations` | `AcademyUser`, `Invitation` | Managed by `admin` via `AcademyUserPolicy` & `InvitationPolicy`; roles limited to `admin`, `trainer`, `support_staff`, `student`. |
| 3 | **Students** | `student_profiles` | `App\Domain\Students\Models\StudentProfile` | `BelongsToAcademy` + `StudentProfilePolicy` (`admin` full CRUD, `student` own profile only). |
| 4 | **Trainers** | `academy_users`, `users` | `AcademyUser`, `User` | `trainer` Spatie role + `TrainerProfilePolicy` (`admin` full CRUD, `trainer` own profile). |
| 5 | **Courses & Categories** | `courses`, `course_categories`, `course_trainers`, `course_prerequisites` | `Course`, `CourseCategory`, `CourseTrainer`, `CoursePrerequisite` | `BelongsToAcademy` + `CoursePolicy` / `CourseCategoryPolicy` (`admin` full CRUD, assigned `trainer` can manage assigned courses). |
| 6 | **Modules & Lessons** | `modules`, `lessons`, `lesson_materials`, `lesson_prerequisites`, `lesson_progress` | `Module`, `Lesson`, `LessonMaterial`, `LessonPrerequisite`, `LessonProgress` | Parent `course_id` -> `courses.academy_id`; `ModulePolicy` & `LessonPolicy` restrict mutations to `admin` or assigned `trainer`. |
| 7 | **Enrollments** | `enrollments` | `Enrollment` | Links `student_profiles` and `courses` within the single academy. |
| 8 | **Support Tickets** | `support_tickets` | `SupportTicket` | `BelongsToAcademy` + `SupportTicketPolicy` (`admin`, `support_staff`, and ticket owner). |
| 9 | **Private Media** | `media` | `Media` | Stored outside webroot on `private_media`; `PrivateMediaService` enforces active user, `admin`/`trainer` role, or student `Enrollment` (plus free-preview rules). |
| 10 | **AI & Settings** | `academy_ai_credentials`, `settings`, `portal_menus`, `ai_faqs`, `ai_materials` | `AcademyAiCredential`, `Setting`, `PortalMenu`, `AiFaq`, `AiMaterial` | Scoped to the installation's `Academy`; `AcademyAiCredential::$api_key` encrypted at rest. |
