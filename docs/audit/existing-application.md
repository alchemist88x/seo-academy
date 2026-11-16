# Existing Application — Technical Inventory
**Project:** MWA Academy LMS Portal  
**Audit date:** 2026-11-12  
**Root:** `C:\laragon\www\portal`  
**DB:** `storage/mwa-assistant.sqlite` (SQLite 3, WAL mode, FK ON)

---

## 1. HTML Pages

All student-facing pages share the same shell pattern:  
`styles.css` + `lms.css` + `mwa-assistant.css` → `lms-pages.js` + `mwa-assistant.js`

| File | Title | data-page | Unique extra assets |
|---|---|---|---|
| `index.html` | MWA Learning LMS | `dashboard` | — |
| `dashboard.html` | Dashboard \| MWA Learning | `dashboard` | — |
| `student-login.html` | Student Sign In \| MWA Academy | *(inline JS)* | `student-login.css` |
| `my-courses.html` | My Courses \| MWA Learning | `myCourses` | — |
| `course.html` | (dynamic) | `course` | `course-data.js` |
| `level.html` | (dynamic) | `level` | — |
| `modules.html` | (dynamic) | `modules` | — |
| `module.html` | (dynamic) | `module` | — |
| `module-1.html`…`module-6.html` | (dynamic) | `module` | — |
| `module-classes.html` | (dynamic) | `moduleClasses` | — |
| `video-lessons.html` | Video Lessons \| MWA Learning | `videoLessons` | `video-lessons.css` |
| `assignments.html` | Assignments \| MWA Learning | `assignments` | — |
| `attendance.html` | Attendance \| MWA Learning | `attendance` | — |
| `quizzes.html` | Quizzes \| MWA Learning | `quizzes` | — |
| `certificates.html` | Certificates \| MWA Learning | `certificate` | — |
| `progress.html` | Progress \| MWA Learning | `progress` | — |
| `profile.html` | My Profile \| MWA Learning | `profile` | — |
| `support.html` | Support \| MWA Learning | `support` | — |
| `class-schedule.html` | Class Schedule \| MWA Learning | `classSchedule` | — |
| `ad-platforms.html` | Ad Platforms \| MWA Learning | `adPlatforms` | — |
| `facebook-ads-lab.html` | MBOOK \| MWA Learning | — | `course-data.js` |
| `minsta-ads.html` | Minsta Ads \| MWA Learning | — | — |
| `moogle-ads.html` | Moogle Ads \| MWA Learning | — | — |
| `practice.html` | Practice Here \| MWA Learning | `practice` | `practice.css` |
| `admin.html` | MWA Academy Admin Portal | — | `admin.css`, `course-data.js`, `admin.js` |
| `mwa-ai-admin.html` | (AI Admin) | — | `mwa-ai-admin.js` |

**JavaScript bundles:**

| File | Purpose |
|---|---|
| `lms-pages.js` | Main SPA router; renders all student pages by reading `data-page` |
| `mwa-assistant.js` | AI chat widget (floating panel) injected on every student page |
| `admin.js` | Academy admin SPA (courses, students, attendance, rewards, etc.) |
| `mwa-ai-admin.js` | AI assistant admin (FAQ, materials, tickets, overview) |
| `course-data.js` | Static course metadata / card images |
| `app.js` | Unused legacy file |
| `module.js` | Unused legacy file |
| `course.js` | Unused legacy file |

---

## 2. API Endpoints

> All endpoints live under `C:\laragon\www\portal\api\`.  
> Session names: `MWA_ADMIN` (admin.php, academy-admin.php, admin-upload.php) · `MWA_LEARNING` (all others).  
> CSRF: custom token in `$_SESSION['csrf']`; sent as `X-CSRF-Token` header on mutation requests.

### `api/student-auth.php`

| Action | Method | Auth | Description |
|---|---|---|---|
| `?action=status` | GET | none | Returns `{authenticated, student_id, name, profile, csrf}` or `{authenticated:false}` |
| `?action=login` | POST | none | Email+password login; rate-limited (10 attempts/15 min per IP hash); creates MWA_LEARNING session |
| `?action=logout` | POST | student session + CSRF | Destroys MWA_LEARNING session |

### `api/admin.php`

| Action | Method | Auth | Description |
|---|---|---|---|
| `?action=login` | POST | none | Username+password; checks config hash first, then `academy_staff` table; rate-limited (8/15 min). Creates MWA_ADMIN session |
| `?action=status` | GET | none | Returns `{admin:true/false, csrf}` |
| `?action=logout` | POST | admin + CSRF | Destroys MWA_ADMIN session |
| `?action=overview` | GET | admin | Chat messages + metrics + FAQs + tickets + materials + popular questions |
| `?action=faq` | POST | admin + CSRF | Insert FAQ (learning or portal category) |
| `?action=delete-faq` | POST | admin + CSRF | Delete FAQ by id |
| `?action=delete-material` | POST | admin + CSRF | Delete approved material + file |
| `?action=ticket-status` | POST | admin + CSRF | Update support ticket status (Pending / In Progress / Resolved) |
| `?action=upload` | POST (multipart) | admin + CSRF | Upload PDF/TXT/MD material (≤10 MB); extracts text via `pdftotext` if available; stores in `storage/knowledge/` |

### `api/academy-admin.php`

| Action | Method | Auth / Role | Description |
|---|---|---|---|
| `?action=status` | GET | none | Returns admin boolean + csrf + role |
| `?action=logout` | POST | admin + CSRF | Destroy session |
| `?action=list&kind=` | GET | admin | List academy_records by kind (500 rows max); auto-seeds course_levels when listing courses |
| `?action=list&kind=staff` | GET | Super Admin | List academy_staff records |
| `?action=lesson-materials&lesson_id=` | GET | admin (lessons) | List PDFs for a video lesson |
| `?action=class-materials&class_id=` | GET | admin (classes) | List PDFs for a class/topic |
| `?action=class-videos&class_id=` | GET | admin (classes) | List extra videos for a class/topic |
| `?action=dashboard` | GET | Super Admin / Admin | Counts, today's classes, attendance, pending assignments/tickets, recent activity |
| `?action=rewards` | GET | admin (students) | Rewards rules + events (filtered by student) + monthly leaderboard + streaks |
| `?action=notifications` | GET | admin (announcements) | All `academy_notifications` rows (300 max) |
| `?action=analytics` | GET | Super Admin / Admin | Engagement (30-day), chat summary, unanswered doubts, streaks, batch performance, leaderboard |
| `?action=ai-summary` | GET | admin (all roles) | Chat metrics, recent messages, FAQs, materials, open ticket count |
| `?action=audit` | GET | Super Admin / Admin | academy_audit log (100 rows) |
| `?action=audit-detail` | GET | Super Admin / Admin | academy_audit_detail log (300 rows) |
| `?action=save` | POST + CSRF | admin | Upsert any `academy_records` kind or `academy_staff`; validates per kind; handles student account sync |
| `?action=delete` | POST + CSRF | admin | Delete academy_records or academy_staff; cascades student account deletion |
| `?action=reward-rules` | POST + CSRF | admin (students) | Bulk-update rewards_rules points (0–10 000) |
| `?action=reward-adjust` | POST + CSRF | admin (students) | Manually award/deduct points for a student |
| `?action=reward-award` | POST + CSRF | admin (students) | Award points with explicit activity_key (idempotent) |
| `?action=notification-send` | POST + CSRF | admin (announcements) | Insert academy_notification (audience: all / batch / student) |
| `?action=lesson-material-update` | POST + CSRF | admin (lessons) | Update PDF title/description for a lesson PDF |
| `?action=lesson-material-delete` | POST + CSRF | admin (lessons) | Delete lesson PDF + file |
| `?action=lesson-material-reorder` | POST + CSRF | admin (lessons) | Reorder lesson PDFs |
| `?action=class-material-update/delete/reorder` | POST + CSRF | admin (classes) | Same operations on class PDFs |
| `?action=class-video-add/update/delete/reorder` | POST + CSRF | admin (classes) | CRUD + reorder extra topic videos |

### `api/admin-upload.php`

| Action | Method | Auth / Role | Description |
|---|---|---|---|
| `?action=chunk` | POST | admin (Super Admin / Admin / Trainer) + CSRF | Receive one 8 MB chunk; headers: `X-Upload-Id` (32-hex), `X-Chunk-Index`, `X-Total-Chunks`, `X-Total-Size`. Writes `.part` files to `storage/upload_chunks/` |
| `?action=finish` | POST + CSRF | admin | Assemble all chunks into temp file, validate MP4 `ftyp` magic, move to `assets/uploads/lessons/`, log audit |
| `?action=single` | POST (multipart) | admin + CSRF | Single upload for thumbnail (JPG/PNG/WebP ≤10 MB) or PDF notes (≤35 MB); linked to lesson_id or class_id |

### `api/chat.php`

| Action | Method | Auth | Description |
|---|---|---|---|
| `?action=bootstrap` | GET | student (or demo session) | Returns last 40 messages, csrf, support email, service configured flag |
| `?action=send` | POST + CSRF | student | Main AI chat; rate-limited (10 msg/min); checks academic-completion refusal → out-of-scope refusal → RAG retrieve → OpenAI Responses API (gpt-4.1-mini, 700 tokens max). Records to messages + chat_metrics |
| `?action=clear` | POST + CSRF | student | Delete student's messages + metrics |
| `?action=support` | POST + CSRF | student | Insert support_ticket |

### `api/engagement.php`

| Action | Method | Auth | Description |
|---|---|---|---|
| `?action=visit` | GET | student | Daily login event; awards points (rule: `daily_login`); updates streak; awards weekly bonus at streak multiples of 7 |
| `?action=notifications` | GET | student | Fetch notifications for this student (all / batch / individual target) |
| `?action=practice-checklist` | GET | student | Fetch student's practice checklist state per platform |
| `?action=practice-checklist` | POST + CSRF | student | Mark a practice task complete/incomplete (platform: instagram/facebook/google; fixed task keys per platform) |
| *(body `type=attendance/lesson/quiz_pass/practical`)* | POST + CSRF | student | Award engagement points; idempotent via UNIQUE(student_id, activity_key) |

### `api/portal-content.php`

| View | Method | Auth | Description |
|---|---|---|---|
| *(default)* | GET | student | Returns courses, course_levels, modules, lessons, classes, attendance, assignments, quizzes, announcements filtered and access-controlled per student's course + level + batch |
| `?view=menu` | GET | student | Returns only portal_menus records |

### `api/video-progress.php`

| Method | Auth | Description |
|---|---|---|
| GET | student | Return all video progress rows for this student |
| POST + CSRF | student | Upsert progress event; deduplicates by event_id; accumulates view_seconds; persists position, duration, completed flag |

---

## 3. Database Tables

### `messages`
| Column | Type | Constraints |
|---|---|---|
| id | INTEGER | PK AUTOINCREMENT |
| student_id | TEXT | NOT NULL |
| role | TEXT | NOT NULL CHECK IN ('user','assistant') |
| body | TEXT | NOT NULL |
| category | TEXT | NOT NULL DEFAULT 'learning' |
| module_name | TEXT | NOT NULL DEFAULT '' |
| created_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |

Index: `messages_student_idx (student_id, id)`

### `support_tickets`
| Column | Type | Constraints |
|---|---|---|
| id | INTEGER | PK AUTOINCREMENT |
| student_id | TEXT | NOT NULL |
| description | TEXT | NOT NULL |
| module_name | TEXT | NOT NULL DEFAULT '' |
| status | TEXT | NOT NULL DEFAULT 'Pending' (Pending/In Progress/Resolved) |
| created_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |
| updated_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |

### `approved_materials`
| Column | Type | Constraints |
|---|---|---|
| id | INTEGER | PK AUTOINCREMENT |
| filename | TEXT | NOT NULL (original user filename) |
| stored_path | TEXT | NOT NULL (40-hex random basename) |
| extracted_text | TEXT | NOT NULL DEFAULT '' |
| uploaded_by | TEXT | NOT NULL (admin_id SHA-256 hash) |
| created_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |

Stored under `storage/knowledge/`.

### `faqs`
| Column | Type | Constraints |
|---|---|---|
| id | INTEGER | PK AUTOINCREMENT |
| question | TEXT | NOT NULL |
| answer | TEXT | NOT NULL |
| category | TEXT | NOT NULL DEFAULT 'learning' |
| active | INTEGER | NOT NULL DEFAULT 1 |
| created_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |

### `chat_metrics`
| Column | Type | Constraints |
|---|---|---|
| id | INTEGER | PK AUTOINCREMENT |
| student_id | TEXT | NOT NULL |
| category | TEXT | NOT NULL |
| module_name | TEXT | NOT NULL DEFAULT '' |
| question | TEXT | NOT NULL DEFAULT '' |
| answered | INTEGER | NOT NULL DEFAULT 1 (0 = service error) |
| created_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |

Tracks: total queries, unique students, unanswered rate, per-module question distribution, popular questions.

### `admin_attempts`
| Column | Type | Constraints |
|---|---|---|
| id | INTEGER | PK AUTOINCREMENT |
| ip_hash | TEXT | NOT NULL (SHA-256 of REMOTE_ADDR) |
| created_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |

Used for admin login brute-force rate limiting (8 attempts/15 min).

### `academy_records`
| Column | Type | Constraints |
|---|---|---|
| id | INTEGER | PK AUTOINCREMENT |
| kind | TEXT | NOT NULL (see below) |
| title | TEXT | NOT NULL |
| data | TEXT | NOT NULL DEFAULT '{}' (JSON blob) |
| status | TEXT | NOT NULL DEFAULT 'Active' |
| created_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |
| updated_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |

Index: `academy_records_kind_idx (kind, id)`

**Kind values and their JSON `data` fields:**

| kind | Key data fields |
|---|---|
| `courses` | course_status, label, description, thumbnail |
| `course_levels` | course, level (Beginner/Intermediate/Advanced), order, description |
| `modules` | course, level, order (1-15), description |
| `lessons` | course, level, module, video (URL), thumbnail, notes (legacy PDF URL) |
| `classes` | course, level, module, date, time, duration, batch, video_url (legacy) |
| `students` | studentId, email, batch, course, level, attendance (%), notes |
| `batches` | description, course, start_date, end_date |
| `admissions` | (enquiry/application records) |
| `attendance` | student, date, status, class_id |
| `assignments` | title, description, due_date, module, course, level |
| `submissions` | student, assignment_id, file_url, score, feedback, status |
| `quizzes` | course, level, module, questions JSON, pass_score |
| `questions` | quiz_id, text, options, correct |
| `progress` | student, completion (0-100), modules_done |
| `certificates` | student, course, issued_date, cert_url |
| `announcements` | audience (all/batch), batch, title, content |
| `tickets` | student, subject, description, status |
| `settings` | key-value configuration |
| `staff` | (alias; real data in academy_staff) |
| `activity` | log entries |
| `portal_menus` | url, icon, label, order |

### `lesson_materials`
| Column | Type | Constraints |
|---|---|---|
| id | INTEGER | PK AUTOINCREMENT |
| video_lesson_id | INTEGER | NOT NULL FK → academy_records(id) ON DELETE CASCADE |
| pdf_title | TEXT | NOT NULL |
| pdf_description | TEXT | NOT NULL DEFAULT '' |
| pdf_file_url | TEXT | NOT NULL (portal-relative path) |
| display_order | INTEGER | NOT NULL DEFAULT 0 |
| created_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |

Index: `lesson_materials_lesson_idx (video_lesson_id, display_order, id)`

### `class_materials`
Identical structure to `lesson_materials` but FK → `class_id`.  
Index: `class_materials_class_idx (class_id, display_order, id)`

### `class_videos`
| Column | Type | Constraints |
|---|---|---|
| id | INTEGER | PK AUTOINCREMENT |
| class_id | INTEGER | NOT NULL FK → academy_records(id) ON DELETE CASCADE |
| video_title | TEXT | NOT NULL |
| video_url | TEXT | NOT NULL |
| display_order | INTEGER | NOT NULL DEFAULT 0 |
| created_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |

Index: `class_videos_class_idx (class_id, display_order, id)`

### `academy_audit`
| Column | Type | Constraints |
|---|---|---|
| id | INTEGER | PK AUTOINCREMENT |
| admin_id | TEXT | NOT NULL (SHA-256 of username) |
| action | TEXT | NOT NULL (create/update/delete) |
| kind | TEXT | NOT NULL |
| record_id | INTEGER | nullable |
| created_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |

### `academy_audit_detail`
| Column | Type | Constraints |
|---|---|---|
| id | INTEGER | PK AUTOINCREMENT |
| admin_name | TEXT | NOT NULL |
| action | TEXT | NOT NULL |
| details | TEXT | NOT NULL DEFAULT '' (JSON) |
| created_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |

### `academy_staff`
| Column | Type | Constraints |
|---|---|---|
| id | INTEGER | PK AUTOINCREMENT |
| username | TEXT | UNIQUE NOT NULL |
| password_hash | TEXT | NOT NULL (bcrypt) |
| role | TEXT | NOT NULL CHECK IN ('Admin','Trainer','Support Staff') |
| active | INTEGER | NOT NULL DEFAULT 1 |

### `academy_students`
| Column | Type | Constraints |
|---|---|---|
| id | INTEGER | PK AUTOINCREMENT |
| student_id | TEXT | UNIQUE NOT NULL (MWA-XXXXXXXXXXXX) |
| record_id | INTEGER | UNIQUE FK → academy_records(id) |
| name | TEXT | NOT NULL |
| email | TEXT | NOT NULL UNIQUE COLLATE NOCASE |
| password_hash | TEXT | NOT NULL (bcrypt) |
| batch | TEXT | NOT NULL DEFAULT '' |
| active | INTEGER | NOT NULL DEFAULT 1 |
| created_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |
| updated_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |
| course | TEXT | NOT NULL DEFAULT '' |
| level | TEXT | NOT NULL DEFAULT 'Beginner' |

Unique indexes: `student_id`, `record_id`, `email`.

### `student_login_attempts`
| Column | Type | Constraints |
|---|---|---|
| ip_hash | TEXT | NOT NULL |
| created_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |

No PK; purged on successful login.

### `student_video_progress`
| Column | Type | Constraints |
|---|---|---|
| student_id | TEXT | NOT NULL PK (composite) |
| lesson_key | TEXT | NOT NULL PK |
| position_seconds | REAL | NOT NULL DEFAULT 0 |
| duration_seconds | REAL | NOT NULL DEFAULT 0 |
| view_seconds | REAL | NOT NULL DEFAULT 0 (cumulative) |
| completed | INTEGER | NOT NULL DEFAULT 0 |
| updated_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |

### `student_video_events`
| Column | Type | Constraints |
|---|---|---|
| student_id | TEXT | NOT NULL PK (composite) |
| event_id | TEXT | NOT NULL PK (UUID-like, client-generated) |
| lesson_key | TEXT | NOT NULL |
| watched_seconds | REAL | NOT NULL DEFAULT 0 (capped at 30 s per event) |
| created_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |

Prevents duplicate progress accumulation per heartbeat event.

### `student_practice_checks`
| Column | Type | Constraints |
|---|---|---|
| student_id | TEXT | NOT NULL PK (composite) |
| platform | TEXT | NOT NULL PK (instagram/facebook/google) |
| task_key | TEXT | NOT NULL PK |
| completed | INTEGER | NOT NULL DEFAULT 0 |
| updated_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |

Fixed task keys per platform (7 for instagram, 7 for facebook, 7 for google).

### `rewards_rules`
| Column | Type | Constraints |
|---|---|---|
| rule | TEXT | PK |
| points | INTEGER | NOT NULL |

Seeded defaults: `daily_login=5`, `attendance=10`, `lesson=5`, `quiz_pass=15`, `practical=25`, `weekly_streak=25`.

### `rewards_events`
| Column | Type | Constraints |
|---|---|---|
| id | INTEGER | PK AUTOINCREMENT |
| student_id | TEXT | NOT NULL |
| activity_key | TEXT | NOT NULL |
| activity | TEXT | NOT NULL (display label) |
| points | INTEGER | NOT NULL |
| admin_name | TEXT | NOT NULL DEFAULT 'system' |
| created_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |

UNIQUE(student_id, activity_key) — prevents duplicate rewards.

### `rewards_streaks`
| Column | Type | Constraints |
|---|---|---|
| student_id | TEXT | PK |
| current_streak | INTEGER | NOT NULL DEFAULT 0 |
| best_streak | INTEGER | NOT NULL DEFAULT 0 |
| last_active_date | TEXT | NOT NULL DEFAULT '' |

### `academy_notifications`
| Column | Type | Constraints |
|---|---|---|
| id | INTEGER | PK AUTOINCREMENT |
| audience | TEXT | NOT NULL (all / batch / student) |
| target | TEXT | NOT NULL DEFAULT '' (batch name or student_id) |
| category | TEXT | NOT NULL |
| title | TEXT | NOT NULL |
| message | TEXT | NOT NULL |
| created_by | TEXT | NOT NULL (admin username) |
| created_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |

### `academy_notification_reads`
| Column | Type | Constraints |
|---|---|---|
| notification_id | INTEGER | NOT NULL PK (composite) |
| student_id | TEXT | NOT NULL PK |
| read_at | TEXT | NOT NULL DEFAULT CURRENT_TIMESTAMP |

---

## 4. Authentication Flows

### Student Authentication
1. `student-login.html` POSTs `{email, password}` to `api/student-auth.php?action=login`
2. Server checks IP hash against `student_login_attempts` (10 attempts/15 min window)
3. Looks up `academy_students WHERE email=? AND active=1`; verifies bcrypt hash
4. On success: `session_regenerate_id(true)`; sets `$_SESSION = {role:'student', student_id, student_name, csrf}`
5. Session cookie: `MWA_LEARNING`; httpOnly, SameSite=Lax, Secure when HTTPS
6. **Demo sessions:** when `demo_sessions=true` (loopback only), unauthenticated local requests auto-generate a `local-<32hex>` student_id — isolated per browser session

### Admin Authentication
1. Admin POSTs `{username, password}` to `api/admin.php?action=login`
2. Rate limit: 8 attempts/15 min per IP hash in `admin_attempts`
3. Checks config (`admin_username` + `admin_password_hash`) first → assigns role `Super Admin`
4. Falls back to `academy_staff` table → assigns staff role (Admin / Trainer / Support Staff)
5. Session cookie: `MWA_ADMIN`; sets `{role:'admin', admin_id, admin_username, admin_role, csrf}`
6. RBAC enforced per action: `permissions` array in `academy-admin.php`

---

## 5. File Storage

| Path | Content | Protection |
|---|---|---|
| `assets/uploads/lessons/` | MP4 videos, JPG/PNG/WebP thumbnails, PDF notes | `.htaccess`: no exec, no index; PHP stripped |
| `storage/knowledge/` | Uploaded AI knowledge materials (PDF/TXT/MD) | `.htaccess`: Require all denied |
| `storage/upload_chunks/` | Temporary chunk files (`{uploadId}.{index}.part`) | No .htaccess; inside storage/ which is denied |
| `storage/mwa-assistant.sqlite` | Full database | `.htaccess`: Require all denied |
| `knowledge/mwa-course-knowledge.md` | Seed knowledge document for RAG | Filesystem only |

**Naming convention:** all uploaded files renamed to `bin2hex(random_bytes(20)) + .ext` (40-character hex + extension). No predictable URLs.

---

## 6. Video Handling

### Chunked Upload Algorithm
- Chunk size: **8 MB** (`$chunkBytes = 8 * 1024 * 1024`)
- Maximum video: **1 GB**
- Client sends chunks sequentially via POST to `?action=chunk`
- Headers per request: `X-Upload-Id` (32-hex random), `X-Chunk-Index` (0-based), `X-Total-Chunks`, `X-Total-Size`
- Server writes `{uploadId}.{index}.part` to `storage/upload_chunks/`
- On `?action=finish`: assembles all parts in order using `stream_copy_to_stream`, validates total byte count, checks MP4 `ftyp` magic bytes (offset 4–7), moves to `assets/uploads/lessons/`
- Cleanup: `.part` files deleted after successful assembly

### Video Progress Tracking
- `lesson_key` = client-defined string (≤120 chars, `[A-Za-z0-9._:-]+`)
- `event_id` = client UUID (≤100 chars) — deduplicates 30-second heartbeat events
- `watched_seconds` capped at 30 s per event server-side (`min(30, value)`)
- `view_seconds` accumulated via SQL `+excluded.view_seconds`
- `completed` flag: monotonic — once set to 1, never decremented
- Full progress state returned on every POST response

---

## 7. AI Assistant

### Configuration
- **Model:** `gpt-4.1-mini` (configurable via `MWA_OPENAI_MODEL` env)
- **Endpoint:** `https://api.openai.com/v1/responses` (Responses API)
- **Max output tokens:** 700
- **API key:** `MWA_OPENAI_API_KEY` env variable

### RAG Retrieval (`mwa_retrieve`)
1. Load `knowledge/mwa-course-knowledge.md` as seed source
2. Load all `approved_materials` with non-empty `extracted_text` (≤100 items)
3. Load all active `faqs` (≤200 items)
4. Tokenize question into words (length > 2)
5. Score each paragraph-chunk by keyword hits (long words ≥6 chars score 2, shorter score 1)
6. Sort descending by score; return top 6 chunks (≤1800 chars each)
7. Injected into system prompt as "APPROVED MATERIAL EXCERPTS"

### Academic Integrity Guard
- `mwa_is_academic_completion()`: regex-based multi-pattern detection for assignment completion requests, quiz answers, submission-ready content
- `mwa_is_out_of_scope()`: checks message contains digital marketing keywords; portal-category questions bypass scope check
- Refusals logged to `chat_metrics` with `answered=1` (they receive a canned response)

### Rate Limiting
- 10 messages per student per minute (rolling window on `chat_metrics.created_at`)

### Demo Sessions
- When `demo_sessions=true` (loopback only) and no session exists, a temporary `local-{32hex}` student_id is auto-created
- These sessions persist only for browser session lifetime; data stored in real tables

---

## 8. Rewards and Streaks

### Rules (configurable via admin)
| Rule | Default Points |
|---|---|
| `daily_login` | 5 |
| `attendance` | 10 |
| `lesson` | 5 |
| `quiz_pass` | 15 |
| `practical` | 25 |
| `weekly_streak` | 25 |

### Streak Calculation (`engagement.php?action=visit`)
1. Called on each portal visit (GET, student auth required)
2. Awards `daily_login` points; idempotent via `activity_key = 'login:{date}'`
3. Reads `rewards_streaks` for `last_active_date`
4. If date changed: compare to yesterday → increment streak or reset to 1
5. Update `rewards_streaks` with new streak + best_streak
6. At every 7th consecutive day: award `weekly_streak` bonus
7. Returns `{points (total), awarded_today, streak, best_streak}`

### Activity Award (POST engagement.php)
- Student frontend POSTs `{type, activity_key}` after completing lesson/quiz/practical/attendance
- Server looks up rule points → inserts `rewards_events`
- UNIQUE constraint prevents double-awarding same `activity_key` per student

---

## 9. Notifications

### Schema
- `academy_notifications`: admin-created; audience = `all` / `batch` / `student`; `target` = batch name or student_id
- `academy_notification_reads`: marks when a student read a notification (currently 0 rows — unused in live system)

### Delivery
- **No push/email/websocket delivery** exists
- Student fetches via `engagement.php?action=notifications` on page load
- Filter: `audience='all'` OR `(audience='student' AND target=student_id)` OR `(audience='batch' AND target IN student's batches)`
- **Gap:** `academy_notification_reads` table exists but write endpoint is not implemented; unread count not tracked in frontend

---

## 10. Analytics

### `chat_metrics` table tracks:
- `student_id` — which student asked
- `category` — learning or portal
- `module_name` — module context (one of 6 known modules or empty)
- `question` — first 4000 chars of the question
- `answered` — 1 = answered (or canned refusal), 0 = API error/unconfigured
- `created_at` — timestamp (used for per-minute rate limiting)

### Admin analytics endpoint (`?action=analytics`) returns:
- **Engagement:** daily events + unique learners for last 30 days
- **Chat summary:** total queries, unique students, unanswered count
- **Unanswered doubts:** last 100 `answered=0` entries
- **Streaks:** all students ranked by current_streak
- **Batch performance:** average progress % + attendance % per batch (JOIN on academy_records)
- **Monthly leaderboard:** top 20 students by points this month
