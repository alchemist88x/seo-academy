# Migration Mapping: MWA Academy (PHP/SQLite) → SEO Academy (Laravel)
**Audit date:** 2026-11-12  

---

## 1. Database Tables → Laravel Migrations

> The legacy system uses a single polymorphic `academy_records` table (with JSON `data` blobs) for 21 entity types. In Laravel, these should be normalized into dedicated relational tables.

| Old SQLite Table | Proposed Laravel Migration(s) | Notes & Risk |
|---|---|---|
| `academy_staff` | `2026_01_01_000001_create_users_table.php` (unified with `role` / Spatie `roles`) | **Medium risk:** Legacy `Super Admin` exists only in `config.local.php`, not in `academy_staff`. Must seed Super Admin into `users` table during migration. |
| `academy_students` | `2026_01_01_000001_create_users_table.php` + `create_student_profiles_table.php` | **High risk:** Legacy `student_id` (`MWA-XXXXXXXXXXXX`) is used as a foreign key string across `messages`, `rewards_events`, `student_video_progress`, etc. Preserve `student_code` column and map to `user_id` during data import. |
| `academy_records` (`kind='courses'`) | `create_courses_table.php` | Extract JSON fields (`course_status`, `label`, `description`, `thumbnail`). |
| `academy_records` (`kind='course_levels'`) | `create_course_levels_table.php` | Normalized FK `course_id`. |
| `academy_records` (`kind='modules'`) | `create_modules_table.php` | Normalized FK `course_level_id`, `order` (1-15). |
| `academy_records` (`kind='lessons'`) | `create_lessons_table.php` | Normalized FK `module_id`; extract `video`, `thumbnail`, legacy `notes`. |
| `academy_records` (`kind='classes'`) | `create_classes_table.php` | Normalized FK `module_id`, `batch_id`; extract `date`, `time`, `duration`, legacy `video_url`. |
| `academy_records` (`kind='batches'`) | `create_batches_table.php` | Normalized FK `course_id`. |
| `academy_records` (`kind='attendance'`) | `create_attendances_table.php` | **Medium risk:** Legacy links by student name/email/ID string. Resolve to `user_id` during ETL. |
| `academy_records` (`kind='assignments'`) | `create_assignments_table.php` | Normalized FKs `course_id`, `module_id`. |
| `academy_records` (`kind='submissions'`) | `create_assignment_submissions_table.php` | Normalized FKs `assignment_id`, `user_id`. |
| `academy_records` (`kind='quizzes'`, `'questions'`) | `create_quizzes_table.php`, `create_quiz_questions_table.php` | Normalize questions out of JSON blobs. |
| `academy_records` (`kind='progress'`) | `create_course_progress_table.php` | **Low risk:** Legacy progress is manually entered; Laravel should compute from `lesson_progress`. |
| `academy_records` (`kind='certificates'`) | `create_certificates_table.php` | Normalized FK `user_id`, `course_id`. |
| `academy_records` (`kind='announcements'`) | `create_announcements_table.php` | — |
| `academy_records` (`kind='tickets'`) & `support_tickets` | `create_support_tickets_table.php` | **Medium risk:** Two separate ticket systems exist (`academy_records` kind=`tickets` vs `support_tickets`). Consolidate into one table. |
| `academy_records` (`kind='admissions'`, `'settings'`, `'portal_menus'`, `'activity'`) | `create_admissions_table.php`, `create_settings_table.php`, `create_portal_menus_table.php` | — |
| `lesson_materials` | `create_lesson_materials_table.php` | FK `lesson_id` -> `lessons.id` (cascade delete). |
| `class_materials` | `create_class_materials_table.php` | FK `class_id` -> `classes.id` (cascade delete). |
| `class_videos` | `create_class_videos_table.php` | FK `class_id` -> `classes.id` (cascade delete). |
| `student_video_progress` | `create_video_progress_table.php` | Composite unique `(user_id, lesson_key)`. Keep `lesson_key` or map to `lesson_id`. |
| `student_video_events` | `create_video_events_table.php` | Composite unique `(user_id, event_id)` for idempotency. |
| `student_practice_checks` | `create_practice_checks_table.php` | Composite unique `(user_id, platform, task_key)`. |
| `messages` | `create_ai_messages_table.php` | FK `user_id`. |
| `chat_metrics` | `create_ai_chat_metrics_table.php` | FK `user_id`. |
| `approved_materials` | `create_ai_knowledge_materials_table.php` | FK `uploaded_by` -> `users.id`. |
| `faqs` | `create_ai_faqs_table.php` | — |
| `rewards_rules` | `create_reward_rules_table.php` | `rule` (unique string), `points` (int). |
| `rewards_events` | `create_reward_events_table.php` | Unique `(user_id, activity_key)`. |
| `rewards_streaks` | `create_reward_streaks_table.php` | `user_id` PK. |
| `academy_notifications` | `create_academy_notifications_table.php` | Consider Laravel's built-in `notifications` table or custom table for batch/all broadcasts. |
| `academy_notification_reads` | `create_academy_notification_reads_table.php` | Composite PK `(notification_id, user_id)`. |
| `academy_audit`, `academy_audit_detail` | `create_audit_logs_table.php` | Consolidate into a single `audit_logs` table (or `spatie/laravel-activitylog`). |
| `admin_attempts`, `student_login_attempts` | *(Drop / Replace)* | Replace with Laravel's `RateLimiter` / `ThrottleRequests` middleware (cache/Redis/DB backed). |

---

## 2. API Endpoints → Laravel Routes & Controllers

| Old Endpoint & Action | Proposed Laravel Route | Controller@Method | Risk Notes |
|---|---|---|---|
| `student-auth.php?action=login` | `POST /login` | `Auth\StudentAuthController@login` | Standard Laravel auth / Fortify. |
| `student-auth.php?action=logout` | `POST /logout` | `Auth\StudentAuthController@logout` | Standard Laravel auth. |
| `student-auth.php?action=status` | `GET /api/auth/status` | `Auth\StudentAuthController@status` | Not needed if using Blade/Livewire SSR, keep if SPA/API remains. |
| `admin.php?action=login` | `POST /admin/login` | `Admin\AdminAuthController@login` | Unified `users` login with role middleware or separate guard. |
| `admin.php?action=logout` | `POST /admin/logout` | `Admin\AdminAuthController@logout` | — |
| `admin.php?action=overview` | `GET /admin/ai/overview` | `Admin\AiAssistantController@overview` | — |
| `admin.php?action=faq` / `delete-faq` | `POST /admin/ai/faqs`, `DELETE /admin/ai/faqs/{faq}` | `Admin\AiFaqController@store` / `@destroy` | — |
| `admin.php?action=upload` / `delete-material` | `POST /admin/ai/materials`, `DELETE /admin/ai/materials/{material}` | `Admin\AiMaterialController@store` / `@destroy` | **Low risk:** Ensure `pdftotext` or PHP PDF parser (`smalot/pdfparser`) is available. |
| `admin.php?action=ticket-status` | `PATCH /admin/tickets/{ticket}/status` | `Admin\SupportTicketController@updateStatus` | — |
| `academy-admin.php?action=dashboard` | `GET /admin` | `Admin\DashboardController@index` | — |
| `academy-admin.php?action=list&kind=*` | `GET /admin/{resource}` | Dedicated resource controllers (`CourseController@index`, `StudentController@index`, etc.) | **Medium risk:** Replacing polymorphic `kind` query with 15+ clean RESTful controllers improves maintainability. |
| `academy-admin.php?action=save` | `POST /admin/{resource}`, `PUT /admin/{resource}/{id}` | Respective `Store`/`Update` controller methods + FormRequests | — |
| `academy-admin.php?action=delete` | `DELETE /admin/{resource}/{id}` | Respective `destroy` controller methods | Ensure cascading deletes or soft deletes. |
| `academy-admin.php?action=lesson-material-*` | `POST/PATCH/DELETE /admin/lessons/{lesson}/materials/*` | `Admin\LessonMaterialController` | — |
| `academy-admin.php?action=class-material-*` | `POST/PATCH/DELETE /admin/classes/{class}/materials/*` | `Admin\ClassMaterialController` | — |
| `academy-admin.php?action=class-video-*` | `POST/PATCH/DELETE /admin/classes/{class}/videos/*` | `Admin\ClassVideoController` | — |
| `academy-admin.php?action=rewards` / `reward-*` | `GET /admin/rewards`, `PUT /admin/rewards/rules`, `POST /admin/rewards/award` | `Admin\RewardController` | — |
| `academy-admin.php?action=notifications` / `notification-send` | `GET /admin/notifications`, `POST /admin/notifications` | `Admin\NotificationController` | — |
| `academy-admin.php?action=analytics` | `GET /admin/analytics` | `Admin\AnalyticsController@index` | — |
| `academy-admin.php?action=audit` / `audit-detail` | `GET /admin/audit-logs` | `Admin\AuditLogController@index` | — |
| `admin-upload.php?action=chunk` / `finish` | `POST /admin/uploads/chunks`, `POST /admin/uploads/finish` | `Admin\MediaUploadController@chunk` / `@finish` | **High risk:** Large file uploads (up to 1 GB). Verify PHP `upload_max_filesize`, `post_max_size`, and Nginx/Apache timeouts; consider S3 presigned URLs or `pion/laravel-chunk-upload`. |
| `admin-upload.php?action=single` | `POST /admin/uploads/single` | `Admin\MediaUploadController@single` | Validate MIME + magic bytes via Laravel validation + Fileinfo. |
| `portal-content.php` | `GET /courses`, `GET /lessons`, etc. (or `/api/portal-content`) | `Student\PortalController` / `CourseController` / `LessonController` | Enforce enrollment policies via Laravel Policies (`CoursePolicy`, `LessonPolicy`). |
| `video-progress.php` | `GET /api/video-progress`, `POST /api/video-progress` | `Student\VideoProgressController@index` / `@store` | Keep `event_id` idempotency check. |
| `chat.php?action=bootstrap` / `send` / `clear` | `GET /api/ai/chat`, `POST /api/ai/chat`, `DELETE /api/ai/chat` | `Student\AiChatController@index` / `@send` / `@clear` | Wrap OpenAI call in `App\Services\AiAssistantService`. |
| `chat.php?action=support` | `POST /api/support-tickets` | `Student\SupportTicketController@store` | — |
| `engagement.php?action=visit` | `GET /api/engagement/visit` (or Middleware on student routes) | `Student\EngagementController@visit` | **Low risk:** Can run via middleware or Livewire mount once per day per user. |
| `engagement.php?action=notifications` | `GET /api/notifications` | `Student\NotificationController@index` | Add `POST /api/notifications/{id}/read` to actually use `notification_reads`. |
| `engagement.php?action=practice-checklist` | `GET/POST /api/practice-checklist` | `Student\PracticeChecklistController@index` / `@toggle` | — |

---

## 3. Roles & Permissions

| Old Role | Laravel Role (`spatie/laravel-permission` or `role` column) | Access Scope | Risk Notes |
|---|---|---|---|
| `Super Admin` (config.local.php) | `super-admin` | Full system access (`Gate::before` returns true), staff management | **Medium risk:** Must migrate hardcoded credentials out of config file into seeded DB user with secure password reset. |
| `Admin` | `admin` | Full academy & AI admin except managing `super-admin` / `staff` | — |
| `Trainer` | `trainer` | `courses`, `course_levels`, `modules`, `lessons`, `classes`, `attendance`, `assignments`, `submissions`, `quizzes`, `progress`, media uploads | Enforce via Laravel Policies / middleware. |
| `Support Staff` | `support-staff` | `tickets`, AI Assistant overview (`admin.php`) | Restrict from course/student CRUD. |
| `student` | `student` | Student portal, enrolled courses, video progress, AI chat, practice labs | **High risk:** Legacy `demo_sessions` auto-creates `local-*` guest students on localhost. Do NOT replicate auto-login in Laravel production; use proper seeded test accounts instead. |

---

## 4. Storage Paths → Laravel Storage Disks

| Old Path | Proposed Laravel Disk & Path | Visibility | Risk Notes |
|---|---|---|---|
| `assets/uploads/lessons/*.mp4` | `Storage::disk('lessons')` (`storage/app/private/lessons/videos/`) or S3 | **Private** (served via signed/authenticated controller route) | **High risk:** Legacy `.htaccess` allows direct HTTP access to `assets/uploads/lessons/` if URL is guessed (40-hex filename provides obscurity only). Moving to private disk + Laravel auth-checked streaming (`response()->file()` / X-Sendfile) prevents unauthorized video sharing. |
| `assets/uploads/lessons/*.pdf` | `Storage::disk('lessons')` (`storage/app/private/lessons/materials/`) | **Private** (authenticated download route) | Same as above; check enrollment before serving PDF. |
| `assets/uploads/lessons/*.{jpg,png,webp}` | `Storage::disk('public')` (`storage/app/public/thumbnails/`) | **Public** (`storage:link`) | Safe to serve publicly via symlink. |
| `storage/knowledge/*` | `Storage::disk('local')` (`storage/app/knowledge/`) | **Private** | Only accessed by backend RAG service. |
| `storage/upload_chunks/*` | `Storage::disk('local')` (`storage/app/chunks/`) | **Private** | Add scheduled Artisan command (`chunks:prune`) to clean abandoned uploads >24h old (missing in legacy). |
| `knowledge/mwa-course-knowledge.md` | `database/seeders/` or `resources/knowledge/mwa-course-knowledge.md` | **Private** | Seed into `ai_knowledge_materials` or keep as base markdown resource. |

---

## 5. Session Keys → Laravel Session Keys

| Old Session Name / Key | Laravel Equivalent | Risk Notes |
|---|---|---|
| Cookie `MWA_LEARNING` & `MWA_ADMIN` | Single Laravel session (`laravel_session`) with separate guards (`web` for students, `admin` for staff) OR unified `web` guard with role-based redirects | **Medium risk:** Legacy uses two separate PHP session names so an admin and student can be logged in simultaneously in the same browser. If single guard is used, simultaneous login in one browser is no longer possible (usually desirable, but worth noting for testing). |
| `$_SESSION['role']` | `Auth::user()->role` (or `Auth::guard('admin')->check()`) | — |
| `$_SESSION['student_id']` | `Auth::id()` / `Auth::user()->student_code` | — |
| `$_SESSION['student_name']` | `Auth::user()->name` | — |
| `$_SESSION['admin_id']` | `Auth::guard('admin')->id()` | Legacy stored `hash('sha256', $username)`. |
| `$_SESSION['admin_username']` | `Auth::user()->username` | — |
| `$_SESSION['admin_role']` | `Auth::user()->role` | — |
| `$_SESSION['csrf']` | `csrf_token()` / `@csrf` / `X-CSRF-TOKEN` | Standard `VerifyCsrfToken` middleware. |

---

## 6. Config Keys → Laravel `.env` Variables

| Old `config.php` / `config.local.php` Key | Laravel `.env` Variable | Risk Notes |
|---|---|---|
| `db_path` | `DB_CONNECTION`, `DB_DATABASE` | Supporting SQLite (`storage/app/private/database.sqlite`) for simple/local deployments and MySQL (InnoDB) for production. |
| `openai_api_key` | `OPENAI_API_KEY` | Stored in `config/services.php` (`services.openai.key`). |
| `openai_model` | `OPENAI_MODEL=gpt-4.1-mini` | `services.openai.model`. |
| `openai_endpoint` | `OPENAI_ENDPOINT=https://api.openai.com/v1/responses` | `services.openai.endpoint`. |
| `demo_sessions` | *(Remove)* / `APP_DEMO_AUTO_LOGIN=false` | **High security risk** if accidentally enabled in production. Remove completely or guard strictly behind `app()->isLocal()`. |
| `admin_username`, `admin_password_hash` | *(Remove from config)* | Seed into `users` table via `DatabaseSeeder` / Artisan command. |
| `max_messages_per_minute` | `AI_RATE_LIMIT_PER_MINUTE=10` | Configure in `RateLimiter::for('ai-chat', ...)`. |
| `max_input_characters` | `AI_MAX_INPUT_CHARS=4000` | Enforced in FormRequest validation. |
| `support_email` | `MAIL_SUPPORT_ADDRESS=support@mwa.academy` | `config/mail.php`. |

---

## 7. Frontend JS State → Livewire / Alpine.js Equivalent

| Old JS File / State | Livewire / Alpine.js Equivalent | Risk Notes |
|---|---|---|
| `lms-pages.js` (monolithic SPA router + `data-page` switching) | Laravel Blade layouts (`layouts/app.blade.php`) + dedicated Livewire full-page components per route (`/dashboard`, `/courses/{course}`, `/lessons/{lesson}`, etc.) | **Medium risk:** Replacing client-side `data-page` DOM rendering with server-rendered Blade + Livewire eliminates FOUC, improves SEO/accessibility, and removes the need for `portal-content.php` mega-payload. |
| `mwa-assistant.js` (floating AI chat widget + history + support ticket modal) | Livewire component `<livewire:ai-assistant-widget />` in main layout + Alpine.js for open/close toggle & auto-scroll | **Low risk:** Use `wire:submit` with loading states (`wire:loading`) and `wire:poll` or Livewire events for smooth UX. |
| `video-lessons.html` + heartbeat progress JS | Alpine.js `x-data="videoPlayer(...)"` wrapping `<video>` element, dispatching `timeupdate` / 30s interval `fetch` or `$wire.recordProgress(...)` | **Low risk:** Keep `crypto.randomUUID()` for `event_id` deduplication. |
| `practice.html` checklists (Instagram / Facebook / Google) | Livewire component `<livewire:practice-checklist :platform="$platform" />` with `wire:click="toggleTask(...)"` | Optimistic UI with Alpine.js `x-model` backed by Livewire `wire:model.live`. |
| `admin.js` (CRUD modal `#recordDialog`, `#toast`, dynamic `#formFields`) | FilamentPHP Admin Panel **or** Livewire Admin Components + Alpine.js modal/toast | **Medium risk:** If using custom Livewire instead of Filament, replicate the dynamic form modal cleanly per resource rather than one generic JSON form builder. |
| `admin-upload.php` chunked uploader JS | Alpine.js chunked file uploader (`File.slice()` in 8 MB chunks) posting to Laravel upload route with progress bar | Test thoroughly with CSRF token refresh for long (>30 min) 1 GB uploads. |
| `mwa-ai-admin.js` (FAQ, Materials, Tickets, Overview tabs) | Livewire `<livewire:admin.ai-overview />` with Alpine.js tab state (`x-data="{ tab: 'overview' }"`) | — |
