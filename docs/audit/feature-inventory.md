# Feature Inventory
**Project:** MWA Academy LMS Portal  
**Audit date:** 2026-11-12  

## Classification Key
| Symbol | Meaning |
|---|---|
| ✅ | Existing and functional |
| ⚠️ | Partially implemented |
| 🎨 | Frontend only (no working backend) |
| 🔧 | Backend only (no UI exposed to students) |
| ❌ | Missing |
| 🔒 | Requires security review |

---

## 1. Authentication

| Feature | Status | Evidence | Notes / Gaps |
|---|---|---|---|
| Student email/password login | ✅ | `api/student-auth.php?action=login`, `student-login.html` | Bcrypt verify, session regenerate |
| Student login rate limiting | ✅ | `student_login_attempts` table, 10 attempts/15 min | IP hashed with SHA-256 |
| Student session management | ✅ | `MWA_LEARNING` cookie, httpOnly, SameSite=Lax | — |
| Student logout | ✅ | `api/student-auth.php?action=logout` + CSRF | Destroys session, clears cookie |
| Student status check | ✅ | `api/student-auth.php?action=status` | Returns profile + CSRF token |
| Admin username/password login | ✅ | `api/admin.php?action=login` | Config hash + academy_staff fallback |
| Admin login rate limiting | ✅ | `admin_attempts` table, 8 attempts/15 min | — |
| Admin session (MWA_ADMIN) | ✅ | Separate session name from student | — |
| Staff role-based access control | ✅ | `permissions` array in `academy-admin.php` | Roles: Super Admin, Admin, Trainer, Support Staff |
| CSRF protection (admin & student) | ✅ | `mwa_csrf()` in bootstrap.php, `X-CSRF-Token` header | Checked on all POST mutations |
| Same-origin enforcement | ✅ | `mwa_same_origin()` checks HTTP_ORIGIN vs HTTP_HOST | — |
| Student self-registration | ❌ | No endpoint or page | Students created by admin only |
| Student password reset | ❌ | No endpoint or page | Must be reset by admin via save action |
| Email verification | ❌ | — | No email sending at all |
| Two-factor authentication | ❌ | — | — |
| Loopback demo sessions | 🔒 | `bootstrap.php` lines 103-110; `demo_sessions` = true when host is localhost | Auto-login bypass for localhost; must be disabled in production |
| Admin password stored in config file | 🔒 | `api/config.local.php` | Password hash in flat PHP file; should move to env-only |

---

## 2. Content Management

| Feature | Status | Evidence | Notes / Gaps |
|---|---|---|---|
| Course CRUD | ✅ | `academy-admin.php?action=save&kind=courses` | Validates course_status (Active/Coming Soon); auto-seeds 3 levels |
| Course level CRUD | ✅ | `kind=course_levels` | Auto-seeded on course creation |
| Module CRUD | ✅ | `kind=modules` | Order 1–15; validates course + level exists |
| Lesson CRUD (video lessons) | ✅ | `kind=lessons` | Linked to course/level/module |
| Class/Topic CRUD | ✅ | `kind=classes` | Includes batch filter, module linkage |
| PDF notes for lessons (multi-PDF) | ✅ | `lesson_materials` table, `admin-upload.php`, `academy-admin.php` lesson-material-* actions | Supports ordering, replace, delete |
| PDF notes for classes (multi-PDF) | ✅ | `class_materials` table | Same as lesson materials |
| Extra videos for class topics | ✅ | `class_videos` table, class-video-* actions | Add/update/delete/reorder |
| Assignment CRUD | ✅ | `kind=assignments` | Frontend page exists (`assignments.html`) |
| Submission grading | ⚠️ | `kind=submissions` exists in DB; `save` action accepts it | No dedicated UI for student submission upload |
| Quiz CRUD | ✅ | `kind=quizzes`, `kind=questions` | Backend stores questions; frontend `quizzes.html` page exists |
| Quiz taking (student-side) | 🎨 | `quizzes.html` via `lms-pages.js` | No POST endpoint for quiz submission/scoring; `portal-content.php` returns quiz records |
| Announcement CRUD | ✅ | `kind=announcements` | Audience: all / specific batch |
| Certificate issuance | ✅ | `kind=certificates` | Backend stores; frontend `certificates.html` exists |
| Certificate download/rendering | 🎨 | `certificates.html` | No PDF generation; cert_url field suggests external file |
| Batch management | ✅ | `kind=batches` | — |
| Student admission records | ✅ | `kind=admissions` | — |
| Portal menu customization | ✅ | `kind=portal_menus`, `portal-content.php?view=menu` | Admin can add custom nav links |
| Bulk import of students | ❌ | — | Only one-at-a-time via admin UI |
| Rich-text editor for content | ❌ | — | All content is plain text / JSON fields |
| Course content versioning | ❌ | — | No version history on academy_records |

---

## 3. Student Learning

| Feature | Status | Evidence | Notes / Gaps |
|---|---|---|---|
| Course listing + enrollment check | ✅ | `portal-content.php` → access='available'/'locked' | Student sees only their enrolled course |
| Course level/module hierarchy navigation | ✅ | `lms-pages.js`, `course.html`, `level.html`, `modules.html` | Hierarchy: Course → Level → Module → Class |
| Lesson viewing | ✅ | `video-lessons.html`, `lms-pages.js` | Shows locked state for wrong course/level |
| Class schedule view | ✅ | `class-schedule.html`, `lms-pages.js` | Filtered by student's batch |
| Attendance view | ✅ | `attendance.html` | Filtered by student_id/name/email |
| Assignment view | ✅ | `assignments.html` | Displays assignments from portal-content |
| Assignment submission (student) | 🎨 | `assignments.html` page exists | No POST endpoint for student file submission |
| Quiz view | ✅ | `quizzes.html` | Displays quiz records |
| Quiz submission (student) | 🎨 | `quizzes.html` | No endpoint to submit answers or record pass/fail |
| Progress tracking (admin-recorded) | ✅ | `kind=progress`, `progress.html` | Admin sets completion %; not auto-calculated |
| Auto-progress from video completion | ⚠️ | `video-progress.php` tracks completion flag | No automatic update of `progress` record from video completion |
| Certificate view | ✅ | `certificates.html` | Reads from academy_records |
| Profile page | ✅ | `profile.html` | Shows student name, course, level, batch |
| Profile editing (student) | 🎨 | `profile.html` | No PUT/PATCH endpoint for student to update own profile |
| My courses page | ✅ | `my-courses.html` | — |
| Ad platform pages (Moogle/Minsta/MBOOK) | ⚠️ | `moogle-ads.html`, `minsta-ads.html`, `facebook-ads-lab.html` | Static content via `lms-pages.js` / `course-data.js`; no backend integration |
| Practice lab checklists | ✅ | `practice.html`, `engagement.php` practice-checklist actions | 3 platforms × 7 tasks; persisted in `student_practice_checks` |

---

## 4. Video

| Feature | Status | Evidence | Notes / Gaps |
|---|---|---|---|
| Chunked video upload (admin) | ✅ | `admin-upload.php?action=chunk` + `?action=finish` | 8 MB chunks, 1 GB max, MP4 magic byte validation |
| Single-file thumbnail upload | ✅ | `admin-upload.php?action=single&kind=thumbnail` | JPG/PNG/WebP ≤10 MB |
| PDF notes upload | ✅ | `admin-upload.php?action=single&kind=notes` | ≤35 MB, PDF magic byte check |
| Video playback (student) | ✅ 🔒 | `video-lessons.html`, HTML5 video element served from `assets/uploads/lessons/` | Publicly accessible under `assets/uploads/`; no tenant or enrollment auth check on direct file URL |
| Resume playback position | ✅ | `video-progress.php` GET + POST | `position_seconds` stored |
| Watch-time accumulation | ✅ | `view_seconds` in `student_video_progress`, capped heartbeats | Deduplication via `student_video_events` |
| Video completion flag | ✅ | `completed` column in `student_video_progress` | Monotonic (never decremented) |
| Video streaming (range requests) | ⚠️ | Files served as static from Apache | No explicit byte-range handling; relies on Apache; large files may not resume on some clients |
| Video transcoding | ❌ | — | MP4 stored as-is; no quality variants/HLS |
| External video embeds (class topics) | ✅ | `class_videos.video_url` can be HTTP(S) URL or portal-relative | — |
| Legacy video URL support | ✅ | `portal-content.php` lines 107-108 | Old `data.video` / `data.video_url` fields surfaced as "legacy" entries |

---

## 5. AI Assistant

| Feature | Status | Evidence | Notes / Gaps |
|---|---|---|---|
| Chat widget (student) | ✅ | `mwa-assistant.js` on every student page | Floating panel; module + category context |
| Chat history (last 40 messages) | ✅ | `api/chat.php?action=bootstrap`, `messages` table | — |
| AI response (OpenAI Responses API) | ✅ | `mwa_call_ai()` in bootstrap.php | gpt-4.1-mini, 700 tokens max |
| RAG retrieval from knowledge base | ✅ | `mwa_retrieve()` — keyword scoring, top 6 chunks | — |
| Academic integrity refusal | ✅ | `mwa_is_academic_completion()` regex guard | Triggered before API call |
| Out-of-scope refusal | ✅ | `mwa_is_out_of_scope()` | Only for `category=learning`; portal questions bypass |
| Rate limiting | ✅ | 10 messages/min via `chat_metrics` | — |
| Support ticket submission from chat | ✅ | `api/chat.php?action=support` | Creates `support_tickets` row |
| Clear chat history | ✅ | `api/chat.php?action=clear` | Deletes messages + metrics |
| FAQ management (admin) | ✅ | `api/admin.php?action=faq / delete-faq` | Two categories: learning, portal |
| Knowledge material upload (admin) | ✅ | `api/admin.php?action=upload` | PDF/TXT/MD; pdftotext extraction if available |
| Knowledge material delete (admin) | ✅ | `api/admin.php?action=delete-material` | Removes file + DB row |
| AI assistant overview dashboard | ✅ | `api/admin.php?action=overview`, `mwa-ai-admin.html` | Messages, unanswered, FAQs, tickets, materials, popular |
| Ticket status management (admin) | ✅ | `api/admin.php?action=ticket-status` | Pending / In Progress / Resolved |
| Semantic/vector search | ❌ | — | RAG uses keyword scoring only; no embeddings |
| PDF text extraction | ⚠️ | Requires `pdftotext` installed on server | Fallback: PDF stored but not indexed |
| Streaming AI responses | ❌ | — | Full response buffered before returning |

---

## 6. Admin Portal

| Feature | Status | Evidence | Notes / Gaps |
|---|---|---|---|
| Admin dashboard (counts + recent) | ✅ | `academy-admin.php?action=dashboard`, `admin.html` | Student/course/class/attendance/assignment/ticket counts |
| Staff account management | ✅ | `kind=staff`, `academy_staff` table | CRUD; Super Admin only |
| Student CRUD | ✅ | `kind=students` | Syncs `academy_records` ↔ `academy_students`; email uniqueness enforced |
| Attendance recording | ✅ | `kind=attendance` | Per-student per-class; status field |
| Submission review | ⚠️ | `kind=submissions` stored | No file serving endpoint for admin to view submission files |
| Analytics dashboard | ✅ | `?action=analytics` | Engagement trend, chat stats, batch performance, leaderboard |
| Audit log | ✅ | `academy_audit` + `academy_audit_detail` | All record mutations + login events |
| Rewards management | ✅ | `?action=rewards`, reward-rules, reward-adjust/award | Leaderboard, manual point award, rule configuration |
| Notification sender | ✅ | `?action=notification-send` | Audience: all / batch / student; stored in DB |
| AI assistant administration | ✅ | `mwa-ai-admin.html` + `api/admin.php` | Separate from academy admin |
| Email notifications to students | ❌ | — | No SMTP/mail integration anywhere |
| Bulk operations | ❌ | — | All CRUD is single-record |
| Data export (CSV/Excel) | ❌ | — | No export endpoints |
| Settings management | ⚠️ | `kind=settings` in valid kinds | No specific UI or defined schema for settings records |

---

## 7. Rewards

| Feature | Status | Evidence | Notes / Gaps |
|---|---|---|---|
| Point awards for daily login | ✅ | `engagement.php?action=visit` | 5 pts default |
| Point awards for attendance | ✅ | POST engagement.php `type=attendance` | 10 pts default |
| Point awards for lesson completion | ✅ | POST `type=lesson` | 5 pts default |
| Point awards for quiz pass | ✅ | POST `type=quiz_pass` | 15 pts default |
| Point awards for practical completion | ✅ | POST `type=practical` | 25 pts default |
| Weekly streak bonus | ✅ | `engagement.php?action=visit` every 7th day | 25 pts default |
| Streak tracking (current + best) | ✅ | `rewards_streaks` table | — |
| Monthly leaderboard | ✅ | `?action=rewards` + `?action=analytics` | — |
| Admin manual point award/deduct | ✅ | `reward-adjust`, `reward-award` | — |
| Admin configurable point values | ✅ | `reward-rules` action | 0–10 000 per rule |
| Student points display | ⚠️ | `engagement.php` returns total points | No dedicated points/leaderboard page for students in LMS |
| Badges / achievements | ❌ | — | Only points and streaks |
| Level-up system | ❌ | — | — |

---

## 8. Notifications

| Feature | Status | Evidence | Notes / Gaps |
|---|---|---|---|
| Admin creates notifications | ✅ | `notification-send` action, `academy_notifications` table | Audience: all / batch / student |
| Student fetches notifications | ✅ | `engagement.php?action=notifications` | Filtered by audience |
| Read tracking | ⚠️ | `academy_notification_reads` table exists | No write endpoint; 0 rows in production; no unread count API |
| Push notifications | ❌ | — | No WebSocket, SSE, or FCM |
| Email notifications | ❌ | — | — |
| In-app notification badge/counter | ❌ | — | No unread tracking in frontend |

---

## 9. Payments

| Feature | Status | Evidence | Notes / Gaps |
|---|---|---|---|
| Payment processing | ❌ | — | No payment gateway, Stripe, Razorpay, or similar |
| Fee/invoice management | ❌ | — | — |
| Course purchase flow | ❌ | — | Students manually enrolled by admin |
| Refund handling | ❌ | — | — |

---

## 10. Analytics

| Feature | Status | Evidence | Notes / Gaps |
|---|---|---|---|
| AI chat metrics (total/unique/unanswered) | ✅ | `chat_metrics` table, `?action=analytics` | — |
| Daily engagement trend (30 days) | ✅ | `rewards_events` grouped by day | — |
| Batch performance averages | ✅ | Computed JOIN on academy_records | avg_progress + avg_attendance |
| Monthly leaderboard | ✅ | `rewards_events` SUM by student | — |
| Streak analytics | ✅ | `rewards_streaks` all students | — |
| Unanswered doubts tracking | ✅ | `answered=0` rows in chat_metrics | — |
| Video watch-time analytics | 🔧 | `student_video_progress.view_seconds` stored | No admin endpoint to query or visualize |
| Video completion rate | 🔧 | `completed` flag in `student_video_progress` | No admin report |
| Practice completion rate | 🔧 | `student_practice_checks` stored | No admin report |
| Course completion rate | ❌ | — | `progress` records are admin-entered, not computed |
| Page/session analytics | ❌ | — | No server-side tracking; no GA/Plausible integration |

---

## 11. Practice Labs

| Feature | Status | Evidence | Notes / Gaps |
|---|---|---|---|
| Practice lab page | ✅ | `practice.html` with `practice.css` | — |
| Ad platform simulator pages | ⚠️ | `facebook-ads-lab.html` (MBOOK), `minsta-ads.html`, `moogle-ads.html` | Static/simulated UI only; no actual ad API |
| Practice checklist (3 platforms × 7 tasks) | ✅ | `student_practice_checks`, `engagement.php` | Fixed task keys: instagram/facebook/google |
| Checklist persistence | ✅ | Stored per student_id + platform + task_key | — |
| Checklist points reward | ⚠️ | `practical` reward type exists | Frontend must POST separately; checklist completion does not auto-award |
| Actual ad platform integration | ❌ | — | MBOOK/Minsta/Moogle are fictional simulators only |
| Practice submission review (admin) | ❌ | — | No admin view for practice completions |
