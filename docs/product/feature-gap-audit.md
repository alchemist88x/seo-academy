# SEO Academy — Comprehensive Feature Gap Audit

**Project:** SEO Academy (`C:\laragon\www\seo-academy`)  
**Audit Date:** 2026-11-13  
**Status:** Post-Phase 2.7 / Commercial Release Preparation Audit  
**Auditor:** Antigravity Engineering (Google DeepMind)  
**Methodology:** Codebase-wide source inspection including routing tables, Eloquent models, domain services, Livewire components, Blade views, database migrations, and 36 test suites.

---

## 1. Executive Summary

SEO Academy has successfully completed its operational and governance foundation (Phases 1 through 2.7), establishing robust single-academy tenant isolation, role-based authorization (admin, trainer, support_staff, student), course catalogue and structure management, private media file vaulting, operational backups with consistent InnoDB/SQLite snapshots, customer support ticketing, email notifications, and an automated 8-stage customer installation wizard verified by 247 automated regression tests (1,544 assertions).

However, an exhaustive audit reveals a substantial gap between the **operational administrative foundation** and the **core learning management system (LMS)** required for a commercial educational product. Key learning engines—including student course consumption (player), progress tracking, assignments, quizzes, examinations, grading/gradebooks, certificates, attendance, live classes, and practical SEO project workshops—exist either as **unconnected Phase 1 database skeletons** or are **completely missing**.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                       CURRENT CODEBASE MATURITY MATRIX                      │
├───────────────────────────────────────────────────┬─────────────────────────┤
│ Domain Area                                       │ Maturity Level          │
├───────────────────────────────────────────────────┼─────────────────────────┤
│ Installation, Deployment & Self-Healing Guards    │ 100% COMPLETE           │
│ System Health, Backup & Snapshot Restoration      │ 100% COMPLETE           │
│ Single-Academy Security, Tenancy & Roles          │ 100% COMPLETE           │
│ Private Media Storage & Stream Vaulting           │ 100% COMPLETE           │
│ Notifications & Multi-Channel Preferences         │ 100% COMPLETE           │
│ Customer Support Helpdesk & Threading             │ 100% COMPLETE           │
│ Admin Course, Module & Lesson Structure Builder   │ 100% COMPLETE           │
│ Student Course List & Dashboard Overview          │ 40% PARTIAL             │
│ Student Learning Classroom / Lesson Player        │ 10% PARTIAL (Preview)   │
│ Learning Progress Tracking & Resumption           │ 5% PARTIAL (Schema)     │
│ Assignment & Submission Workflow                  │ 5% PARTIAL (Schema)     │
│ Quiz, Question Bank & Automated Assessment        │ 5% PARTIAL (Schema)     │
│ Examination System & Proctored Attempts           │ 0% MISSING              │
│ Central Gradebook & Academic Transcripts          │ 0% MISSING              │
│ Certificate Generation & Verification Engine      │ 5% PARTIAL (Schema)     │
│ Live Class Integrations (Jitsi / BigBlueButton)   │ 5% PARTIAL (Schema)     │
│ Attendance Recording & Requirements               │ 5% PARTIAL (Schema)     │
│ Practical SEO Projects & Student Portfolios       │ 0% MISSING              │
│ Course Discussions & Moderation                   │ 0% MISSING              │
│ Analytics, Reporting & Gradebook Exports          │ 10% PARTIAL (Dashboards)│
│ Optional AI-Assisted Tutoring & Grading           │ 20% PARTIAL (Contract)  │
└───────────────────────────────────────────────────┴─────────────────────────┘
```

---

## 2. Evidence-Based Feature Classification

Features are classified into four rigorous categories based on concrete code and test evidence:
1. **COMPLETE**: Fully implemented in models, services, UI components, and backed by automated tests.
2. **PARTIAL**: Implementation or schema exists, but essential user-facing workflows, services, or UI components are missing.
3. **MISSING**: Not implemented in the codebase (no models, no services, no UI).
4. **NEEDS VERIFICATION**: Code exists but lacks automated tests or operational proof.

---

### 2.1 Student Experience

| Capability | Status | Evidence in Codebase | Deficiencies & Gaps |
|---|---|---|---|
| **Student Dashboard** | **PARTIAL** | Class: [`App\Livewire\Dashboard\StudentDashboard`](file:///C:/laragon/www/seo-academy/app/Livewire/Dashboard/StudentDashboard.php)<br>View: [`livewire/dashboard/student-dashboard.blade.php`](file:///C:/laragon/www/seo-academy/resources/views/livewire/dashboard/student-dashboard.blade.php)<br>Route: `GET /student/dashboard` | Shows cards for Active Courses, Lessons Completed, Courses Completed, and Total Enrollments. However, "Lessons Completed" relies on `LessonProgress` records which are never created by any UI action. Recent enrollments link only to public course catalogue view. |
| **My Courses View** | **PARTIAL** | Class: [`App\Livewire\Student\StudentCoursesIndex`](file:///C:/laragon/www/seo-academy/app/Livewire/Student/StudentCoursesIndex.php)<br>View: [`livewire/student/student-courses-index.blade.php`](file:///C:/laragon/www/seo-academy/resources/views/livewire/student/student-courses-index.blade.php)<br>Route: `GET /student/courses` | Lists enrolled courses with search, category filtering, and status badges. However, action buttons ("Resume Course", "Review Course") link to the public course overview (`CourseCatalogueController@show`), not a student classroom/player. |
| **Course Overview & Curriculum** | **COMPLETE** | Controller: [`App\Http\Controllers\CourseCatalogueController`](file:///C:/laragon/www/seo-academy/app/Http/Controllers/CourseCatalogueController.php)<br>View: [`catalogue/show.blade.php`](file:///C:/laragon/www/seo-academy/resources/views/catalogue/show.blade.php)<br>Test: [`tests/Feature/Courses/CourseCatalogueTest.php`](file:///C:/laragon/www/seo-academy/tests/Feature/Courses/CourseCatalogueTest.php) | Accurately presents course syllabus, duration, prerequisites, instructors, module hierarchy, and lesson outlines with free-preview badges. |
| **Classroom / Lesson Player** | **MISSING** | Preview Only: [`App\Http\Controllers\LessonPreviewController`](file:///C:/laragon/www/seo-academy/app/Http/Controllers/LessonPreviewController.php)<br>View: [`lessons/preview.blade.php`](file:///C:/laragon/www/seo-academy/resources/views/lessons/preview.blade.php) | There is **no dedicated student learning interface**. Students can only view lessons if they have administrative/trainer preview permissions. No persistent sidebar showing curriculum progress, no "Next Lesson" / "Previous Lesson" stepper, no responsive learning layout. |
| **Lesson Completion Tracking** | **MISSING** | Model: [`App\Domain\Learning\Models\LessonProgress`](file:///C:/laragon/www/seo-academy/app/Domain/Learning/Models/LessonProgress.php)<br>Migration: `2026_11_12_160003_create_lesson_progress_table.php` | The table and model exist, but **zero application code writes to or updates `lesson_progress`**. There is no "Mark as Complete" button, no video watch-time beacon, no completion threshold trigger, and no automated module completion logic. |
| **Resume Last Lesson** | **MISSING** | None. | No mechanism tracks the student's last active lesson or video playback position to provide a 1-click "Resume where you left off" workflow. |
| **Course Prerequisites Enforcement** | **COMPLETE** | Model: [`App\Domain\Courses\Models\CoursePrerequisite`](file:///C:/laragon/www/seo-academy/app/Domain/Courses/Models/CoursePrerequisite.php)<br>Service: [`App\Domain\Courses\Services\CourseManagementService`](file:///C:/laragon/www/seo-academy/app/Domain/Courses/Services/CourseManagementService.php)<br>Test: [`tests/Feature/Courses/CourseManagementTest.php`](file:///C:/laragon/www/seo-academy/tests/Feature/Courses/CourseManagementTest.php) | Admin/trainer can configure prerequisite courses. Prerequisite checks prevent circular dependencies and are displayed to students. |
| **Course Completion Eligibility** | **MISSING** | None. | Enrollment status can only be set to `completed` manually by an administrator via `EnrollmentsIndex.php`. No automated engine verifies that all required lessons, quizzes, or assignments are finished. |
| **Downloadable Authorized Materials** | **PARTIAL** | Model: [`App\Domain\Lessons\Models\LessonMaterial`](file:///C:/laragon/www/seo-academy/app/Domain/Lessons/Models/LessonMaterial.php)<br>Controller: [`App\Http\Controllers\PrivateMediaController`](file:///C:/laragon/www/seo-academy/app/Http/Controllers/PrivateMediaController.php) | Private media authorization is secure and functional, but materials can only be downloaded via the admin/trainer lesson preview, not inside an active student learning session. |
| **Announcements & Notifications** | **COMPLETE** | Component: [`App\Livewire\Notifications\NotificationCenter`](file:///C:/laragon/www/seo-academy/app/Livewire/Notifications/NotificationCenter.php)<br>Service: [`App\Domain\Notifications\Services\AcademyNotificationService`](file:///C:/laragon/www/seo-academy/app/Domain/Notifications/Services/AcademyNotificationService.php)<br>Test: [`tests/Feature/Operations/NotificationCenterTest.php`](file:///C:/laragon/www/seo-academy/tests/Feature/Operations/NotificationCenterTest.php) | Full in-app notification center with read/unread tracking, multi-channel preferences, and queued transactional emails. |
| **Student Profile & Preferences** | **PARTIAL** | Model: [`App\Domain\Students\Models\StudentProfile`](file:///C:/laragon/www/seo-academy/app/Domain/Students/Models/StudentProfile.php)<br>Component: [`App\Livewire\Notifications\NotificationPreferences`](file:///C:/laragon/www/seo-academy/app/Livewire/Notifications/NotificationPreferences.php) | Students can manage notification preferences, but there is no dedicated profile screen for updating name, bio, avatar, or viewing academic achievements. |

---

### 2.2 Trainer Experience

| Capability | Status | Evidence in Codebase | Deficiencies & Gaps |
|---|---|---|---|
| **Trainer Dashboard** | **COMPLETE** | Class: [`App\Livewire\Dashboard\TrainerDashboard`](file:///C:/laragon/www/seo-academy/app/Livewire/Dashboard/TrainerDashboard.php)<br>Service: [`App\Domain\Trainers\Services\TrainerOperationService`](file:///C:/laragon/www/seo-academy/app/Domain/Trainers/Services/TrainerOperationService.php)<br>Test: [`tests/Feature/Operations/TrainerOperationsTest.php`](file:///C:/laragon/www/seo-academy/tests/Feature/Operations/TrainerOperationsTest.php) | Displays assigned courses, active students, cohorts, and recent enrollment activity. |
| **Trainer Assigned Courses** | **COMPLETE** | Class: [`App\Livewire\Trainer\TrainerCoursesIndex`](file:///C:/laragon/www/seo-academy/app/Livewire/Trainer/TrainerCoursesIndex.php)<br>Route: `GET /trainer/courses` | Filterable course view restricted to courses assigned to the logged-in trainer. |
| **Course Structure Editing** | **COMPLETE** | Class: [`App\Livewire\Admin\CourseStructureEditor`](file:///C:/laragon/www/seo-academy/app/Livewire/Admin/CourseStructureEditor.php)<br>Policy: [`App\Domain\Courses\Policies\CoursePolicy`](file:///C:/laragon/www/seo-academy/app/Domain/Courses/Policies/CoursePolicy.php)<br>Test: [`tests/Feature/Courses/CourseStructureTest.php`](file:///C:/laragon/www/seo-academy/tests/Feature/Courses/CourseStructureTest.php) | Trainers assigned to a course can manage modules, lessons, materials, reorder items, and manage lesson prerequisites. |
| **Trainer Students Roster** | **COMPLETE** | Class: [`App\Livewire\Trainer\TrainerStudentsIndex`](file:///C:/laragon/www/seo-academy/app/Livewire/Trainer/TrainerStudentsIndex.php)<br>Route: `GET /trainer/students` | Filterable roster of students enrolled in the trainer's assigned courses with attendance and enrollment status metrics. |
| **Grading & Feedback Queue** | **MISSING** | None. | No trainer interface exists to view pending assignment submissions, review student work, input grades, or provide feedback. |
| **Live Class Management** | **MISSING** | None. | Trainers have no interface to schedule live classes, launch video meetings, or record live attendance. |

---

### 2.3 Course, Module and Lesson Management

| Capability | Status | Evidence in Codebase | Deficiencies & Gaps |
|---|---|---|---|
| **Course Catalogue** | **COMPLETE** | Class: [`App\Livewire\Admin\CoursesIndex`](file:///C:/laragon/www/seo-academy/app/Livewire/Admin/CoursesIndex.php)<br>Service: [`App\Domain\Courses\Services\CourseManagementService`](file:///C:/laragon/www/seo-academy/app/Domain/Courses/Services/CourseManagementService.php)<br>Test: [`tests/Feature/Courses/CourseManagementTest.php`](file:///C:/laragon/www/seo-academy/tests/Feature/Courses/CourseManagementTest.php) | Full CRUD, thumbnail upload, slug generation, visibility toggling (`public`, `academy_only`, `unlisted`), pricing, and category tagging. |
| **Module Organization** | **COMPLETE** | Service: [`App\Domain\Courses\Services\CourseStructureService`](file:///C:/laragon/www/seo-academy/app/Domain/Courses/Services/CourseStructureService.php)<br>Model: [`App\Domain\Courses\Models\Module`](file:///C:/laragon/www/seo-academy/app/Domain/Courses/Models/Module.php) | Supports display ordering, completion rules (`all_lessons`, `required_count`), and safe deletion protection. |
| **Multi-Type Lessons** | **COMPLETE** | Model: [`App\Domain\Lessons\Models\Lesson`](file:///C:/laragon/www/seo-academy/app/Domain/Lessons/Models/Lesson.php)<br>Component: [`App\Livewire\Admin\CourseStructureEditor`](file:///C:/laragon/www/seo-academy/app/Livewire/Admin/CourseStructureEditor.php) | Supports `video`, `pdf`, `external`, and `text` lessons with duration, free-preview flags, completion thresholds, and private media linkage. |
| **Lesson Prerequisites** | **COMPLETE** | Model: [`App\Domain\Lessons\Models\LessonPrerequisite`](file:///C:/laragon/www/seo-academy/app/Domain/Lessons/Models/LessonPrerequisite.php)<br>Service: [`App\Domain\Courses\Services\CourseStructureService`](file:///C:/laragon/www/seo-academy/app/Domain/Courses/Services/CourseStructureService.php) | Enforces prerequisite lesson completion rules within and across modules. |

---

### 2.4 Assignments

| Capability | Status | Evidence in Codebase | Deficiencies & Gaps |
|---|---|---|---|
| **Assignment Management** | **PARTIAL** | Model: [`App\Domain\Assignments\Models\Assignment`](file:///C:/laragon/www/seo-academy/app/Domain/Assignments/Models/Assignment.php)<br>Migration: `2026_01_01_000060_create_assignments_table.php` | The table has columns: `id`, `module_id`, `title`, `description`, `due_date`, `status`. Missing: `academy_id`, `max_score`, `passing_score`, `grading_type`, `rubric`, `resubmission_allowed`, `max_attempts`. **No service, controller, Livewire component, or UI exists to create assignments.** |
| **Student Submissions** | **PARTIAL** | Model: [`App\Domain\Assignments\Models\AssignmentSubmission`](file:///C:/laragon/www/seo-academy/app/Domain/Assignments/Models/AssignmentSubmission.php)<br>Migration: `2026_01_01_000061_create_assignment_submissions_table.php` | The table has columns: `id`, `assignment_id`, `user_id`, `file_path`, `notes`, `grade`, `status`. Missing: `submitted_at`, `attempt_number`, `feedback`, `graded_by`, `graded_at`. **No student submission interface, file upload handler, or resubmission logic exists.** |
| **Rubrics & Criteria** | **MISSING** | None. | No rubric definitions, scoring criteria, or structured evaluation guides exist. |
| **Grading & Feedback Workflow** | **MISSING** | None. | No interface for trainers to view submissions, evaluate documents, enter numerical marks, or provide revision feedback. |
| **Late Submission Policies** | **MISSING** | None. | No logic handles submission cutoffs, grace periods, or late penalty deductions. |

---

### 2.5 Quiz System & Question Banks

| Capability | Status | Evidence in Codebase | Deficiencies & Gaps |
|---|---|---|---|
| **Quiz Configuration** | **PARTIAL** | Model: [`App\Domain\Quizzes\Models\Quiz`](file:///C:/laragon/www/seo-academy/app/Domain/Quizzes/Models/Quiz.php)<br>Migration: `2026_01_01_000070_create_quizzes_table.php` | Table contains: `id`, `module_id`, `title`, `description`, `pass_mark`, `status`. Missing: `academy_id`, `time_limit_minutes`, `attempt_limit`, `randomize_questions`, `randomize_answers`, `show_correct_answers`. **No UI or service exists to create or configure quizzes.** |
| **Question Bank** | **MISSING** | None. | No question bank system exists. Questions are directly bound to a specific `quiz_id`. There are no shared categories, tags, or reusable pools. |
| **Question Types** | **PARTIAL** | Model: [`App\Domain\Quizzes\Models\QuizQuestion`](file:///C:/laragon/www/seo-academy/app/Domain/Quizzes/Models/QuizQuestion.php)<br>Migration: `2026_11_12_120007_create_quiz_questions_table.php` | The table supports `question_text`, `question_type`, `options` (JSON), `correct_answer`, `points`, `display_order`. However, **zero rendering or grading logic exists** for multiple-choice, multiple-select, true/false, short answer, or essay questions. |
| **Quiz Player / Runner** | **MISSING** | None. | No student interface exists to start a quiz attempt, display questions, handle timers, record radio/checkbox answers, or auto-submit on timeout. |
| **Automated Grading** | **MISSING** | None. | No service evaluates student answers against `correct_answer` or calculates percentage scores. |
| **Manual Essay Grading** | **MISSING** | None. | No workflow allows trainers to evaluate open-ended or subjective text answers. |

---

### 2.6 Examination System

| Capability | Status | Evidence in Codebase | Deficiencies & Gaps |
|---|---|---|---|
| **Scheduled Exams** | **MISSING** | None. | No exam scheduling model, start/end windows, or cohort access rules exist. |
| **Time Limits & Auto-Submit** | **MISSING** | None. | No countdown timer, server-enforced timeout submission, or attempt auto-finalization exists. |
| **Attempt Interruption Recovery** | **MISSING** | None. | No mechanism preserves intermediate question answers during network drops or accidental tab closures. |
| **Practical Security & Audit** | **MISSING** | None. | No tracking of attempt start times, IP addresses, user agent signatures, answer change logs, or tab-blur events. |

---

### 2.7 Grading, Gradebook and Academic Records

| Capability | Status | Evidence in Codebase | Deficiencies & Gaps |
|---|---|---|---|
| **Centralized Gradebook** | **MISSING** | None. | No centralized gradebook view exists for administrators or trainers. Scores from quizzes and assignments are isolated in disconnected tables. |
| **Weighted Grading** | **MISSING** | None. | No mechanism calculates final course grades based on component weights (e.g. 30% assignments, 30% quizzes, 40% final project). |
| **Manual Adjustments & Audit** | **MISSING** | None. | No interface permits grade overrides with audit reasoning. |
| **Student Grade Visibility** | **MISSING** | None. | Students have no transcript or report card view to see their itemized scores and overall standing. |
| **Grade Exports** | **MISSING** | None. | No CSV/Excel export of class grades or course results. |

---

### 2.8 Certificates

| Capability | Status | Evidence in Codebase | Deficiencies & Gaps |
|---|---|---|---|
| **Certificate Model** | **PARTIAL** | Model: [`App\Domain\Certificates\Models\Certificate`](file:///C:/laragon/www/seo-academy/app/Domain/Certificates/Models/Certificate.php)<br>Migration: `2026_01_01_000090_create_certificates_table.php` | Table contains `id`, `user_id`, `course_id`, `issued_at`, `certificate_number`, `status`, `created_at`. Missing: `academy_id`, `revoked_at`, `revocation_reason`, `metadata`, `template_id`. |
| **Certificate Templates** | **MISSING** | None. | No template management or customizable layouts with academy branding. |
| **PDF Generation** | **MISSING** | None. | No PDF rendering engine (Dompdf, Snappy, or raw canvas) is integrated for certificate generation. |
| **Public Verification Page** | **MISSING** | None. | No public route (e.g. `/verify/{certificate_number}`) exists to validate credential authenticity. |
| **Automated Issuance Rules** | **MISSING** | None. | No service evaluates course completion, attendance thresholds, and passing grades to automatically award certificates. |

---

### 2.9 Attendance & Live Learning

| Capability | Status | Evidence in Codebase | Deficiencies & Gaps |
|---|---|---|---|
| **Live Class Model** | **PARTIAL** | Model: [`App\Domain\LiveClasses\Models\LiveClass`](file:///C:/laragon/www/seo-academy/app/Domain/LiveClasses/Models/LiveClass.php)<br>Migration: `2026_01_01_000032_create_live_classes_table.php` | Schema has `id`, `module_id`, `title`, `scheduled_at`, `meeting_link`, `status`. Missing: `academy_id`, `duration_minutes`, `platform` (Jitsi, BBB, External), `recording_url`. **No UI or scheduling tools exist.** |
| **Attendance Recording** | **PARTIAL** | Model: [`App\Domain\Attendance\Models\AttendanceRecord`](file:///C:/laragon/www/seo-academy/app/Domain/Attendance/Models/AttendanceRecord.php)<br>Migration: `2026_01_01_000080_create_attendance_records_table.php` | Schema has `id`, `user_id`, `live_class_id`, `attended_at`, `notes`. **No trainer attendance marking interface or student attendance log exists.** |
| **Jitsi / BigBlueButton Integration** | **MISSING** | None. | No integration exists to generate self-hosted Jitsi Meet room tokens or BigBlueButton meeting URLs. |
| **Attendance Requirements** | **MISSING** | None. | No rules enforce minimum attendance percentages (e.g. 80% live attendance) for certificate eligibility. |

---

### 2.10 SEO Practical Learning Environment

| Capability | Status | Evidence in Codebase | Deficiencies & Gaps |
|---|---|---|---|
| **Keyword Research Projects** | **MISSING** | None. | No structured templates, spreadsheet upload parsers, or keyword clustering exercises exist. |
| **Website Audit Projects** | **MISSING** | None. | No interactive technical SEO checklists (crawlability, indexability, Core Web Vitals, schema markup) or audit submission workflows exist. |
| **On-Page SEO Assessments** | **MISSING** | None. | No content optimization workflows (title tags, meta descriptions, headings, search intent matching) exist. |
| **GA4 & GSC Report Submissions** | **MISSING** | None. | No project milestones for submitting Google Search Console performance extracts or GA4 analytics screenshots. |
| **Student Portfolio Showcase** | **MISSING** | None. | No student portfolio view exists to display completed SEO campaigns, before/after ranking proofs, or audit reports to prospective employers. |

---

### 2.11 Discussions & Course Communication

| Capability | Status | Evidence in Codebase | Deficiencies & Gaps |
|---|---|---|---|
| **Lesson & Course Discussions** | **MISSING** | None. | No discussion threads, Q&A sections, comment feeds, or reply notifications exist within courses or lessons. |
| **Moderation Tools** | **MISSING** | None. | No content reporting, comment hiding, or trainer/admin moderation controls exist. |

---

### 2.12 Reporting, Analytics and Exports

| Capability | Status | Evidence in Codebase | Deficiencies & Gaps |
|---|---|---|---|
| **Admin Reporting** | **PARTIAL** | Class: [`App\Livewire\Dashboard\AdminDashboard`](file:///C:/laragon/www/seo-academy/app/Livewire/Dashboard/AdminDashboard.php)<br>Service: [`App\Domain\Academies\Services\AcademySettingsService`](file:///C:/laragon/www/seo-academy/app/Domain/Academies/Services/AcademySettingsService.php) | Provides 4 summary stat counters. Missing: course completion rates, student drop-off funnels, assignment performance analytics, grade distributions, and CSV exports. |
| **Trainer Reporting** | **PARTIAL** | Class: [`App\Livewire\Dashboard\TrainerDashboard`](file:///C:/laragon/www/seo-academy/app/Livewire/Dashboard/TrainerDashboard.php)<br>Service: [`App\Domain\Trainers\Services\TrainerOperationService`](file:///C:/laragon/www/seo-academy/app/Domain/Trainers/Services/TrainerOperationService.php) | Provides active student counts and recent enrollments. Missing: grading velocity, quiz attempt failure rates, and student engagement telemetry. |
| **Student Academic Report** | **MISSING** | None. | Students have no consolidated academic history or performance analytics page. |

---

### 2.13 Optional AI-Assisted Features

| Capability | Status | Evidence in Codebase | Deficiencies & Gaps |
|---|---|---|---|
| **AI Provider Abstraction** | **COMPLETE** | Contract: [`App\Domain\AI\Contracts\AiProviderInterface`](file:///C:/laragon/www/seo-academy/app/Domain/AI/Contracts/AiProviderInterface.php)<br>Providers: [`NullAiProvider`](file:///C:/laragon/www/seo-academy/app/Domain/AI/Services/Providers/NullAiProvider.php), [`OpenAiCompatibleProvider`](file:///C:/laragon/www/seo-academy/app/Domain/AI/Services/Providers/OpenAiCompatibleProvider.php)<br>Test: [`tests/Feature/AI/AiProviderTest.php`](file:///C:/laragon/www/seo-academy/tests/Feature/AI/AiProviderTest.php) | Cleanly abstracts AI providers with fallback to `NullAiProvider` when disabled. Securely handles BYO API keys via `AcademyAiCredential` without frontend exposure. |
| **AI Assistant Service** | **PARTIAL** | Service: [`App\Domain\AI\Services\AiAssistantService`](file:///C:/laragon/www/seo-academy/app/Domain/AI/Services/AiAssistantService.php)<br>Models: `AiMessage`, `AiFaq`, `AiMaterial` | Service exists for FAQ search and message completion, but **is not connected to any UI or student workflow**. |
| **AI Grading & Feedback** | **MISSING** | None. | No AI assistance for drafting assignment feedback or suggesting essay quiz grades. |
| **SEO Practical AI Tools** | **MISSING** | None. | No AI keyword clustering, title tag generation, or content optimization recommendations. |

---

### 2.14 Operational Foundation & Governance (Phases 1 – 2.7)

| Capability | Status | Evidence in Codebase | Deficiencies & Gaps |
|---|---|---|---|
| **Multi-Driver Installation Wizard** | **COMPLETE** | Controller: [`InstallController`](file:///C:/laragon/www/seo-academy/app/Http/Controllers/InstallController.php)<br>Service: [`DatabaseInstallerService`](file:///C:/laragon/www/seo-academy/app/Domain/Platform/Services/DatabaseInstallerService.php)<br>Tests: [`InstallWizardTest`](file:///C:/laragon/www/seo-academy/tests/Feature/InstallWizardTest.php), [`InstallSafetyTest`](file:///C:/laragon/www/seo-academy/tests/Feature/InstallSafetyTest.php) | 8-stage interactive wizard, InnoDB validation, fail-closed ownership detection, identity marker protection, atomic lock file self-healing. |
| **Backup & Full-Set Restoration** | **COMPLETE** | Service: [`BackupService`](file:///C:/laragon/www/seo-academy/app/Domain/Platform/Services/BackupService.php)<br>Component: [`BackupsIndex`](file:///C:/laragon/www/seo-academy/app/Livewire/Admin/BackupsIndex.php)<br>Test: [`BackupAndRecoveryTest`](file:///C:/laragon/www/seo-academy/tests/Feature/Operations/BackupAndRecoveryTest.php) | Consistent MySQL snapshot transactions (`REPEATABLE READ`), SQLite database copy, zip media backup, atomic dual-component rollback on failure. |
| **Single-Academy Multi-Tenancy** | **COMPLETE** | Concern: [`BelongsToAcademy`](file:///C:/laragon/www/seo-academy/app/Domain/Tenants/Concerns/BelongsToAcademy.php)<br>Service: [`TenantContext`](file:///C:/laragon/www/seo-academy/app/Domain/Tenants/Services/TenantContext.php)<br>Test: [`TenantIsolationTest`](file:///C:/laragon/www/seo-academy/tests/Feature/Tenancy/TenantIsolationTest.php) | Enforces single-academy isolation across all domain queries and models. |
| **Private Media Vault** | **COMPLETE** | Service: [`PrivateMediaService`](file:///C:/laragon/www/seo-academy/app/Domain/Video/Services/PrivateMediaService.php)<br>Controller: [`PrivateMediaController`](file:///C:/laragon/www/seo-academy/app/Http/Controllers/PrivateMediaController.php)<br>Test: [`PrivateMediaTest`](file:///C:/laragon/www/seo-academy/tests/Feature/Media/PrivateMediaTest.php) | Stores media outside webroot (`storage/app/private`), chunked streaming, MIME validation, strict role authorization. |
| **Audit Logging & System Health** | **COMPLETE** | Concern: [`RecordsAuditLog`](file:///C:/laragon/www/seo-academy/app/Domain/Platform/Concerns/RecordsAuditLog.php)<br>Components: [`AuditLogsIndex`](file:///C:/laragon/www/seo-academy/app/Livewire/Admin/AuditLogsIndex.php), [`SystemHealthDashboard`](file:///C:/laragon/www/seo-academy/app/Livewire/Admin/SystemHealthDashboard.php)<br>Tests: [`AuditLoggingTest`](file:///C:/laragon/www/seo-academy/tests/Feature/Operations/AuditLoggingTest.php), [`SystemHealthTest`](file:///C:/laragon/www/seo-academy/tests/Feature/Operations/SystemHealthTest.php) | Complete audit trails for sensitive changes and operational health dashboard. |
| **Support Helpdesk** | **COMPLETE** | Service: [`SupportTicketService`](file:///C:/laragon/www/seo-academy/app/Domain/Support/Services/SupportTicketService.php)<br>Components: [`SupportTicketsIndex`](file:///C:/laragon/www/seo-academy/app/Livewire/Support/SupportTicketsIndex.php), [`SupportTicketDetail`](file:///C:/laragon/www/seo-academy/app/Livewire/Support/SupportTicketDetail.php)<br>Tests: [`SupportOperationsTest`](file:///C:/laragon/www/seo-academy/tests/Feature/Operations/SupportOperationsTest.php), [`SupportMessageRetentionTest`](file:///C:/laragon/www/seo-academy/tests/Feature/Operations/SupportMessageRetentionTest.php) | Multi-participant ticketing, attachment handling, author name retention on user deletion, priority filtering. |

---

## 3. Security Concerns & Vulnerability Analysis

### 3.1 Examination Integrity Without Invasive Surveillance
Commercial online exams must balance academic integrity with privacy and shared-hosting limitations:
- **Flawed Assumption:** Attempting to build an unbreakable browser proctoring tool using invasive client webcam streams or full-screen locks is impractical and fragile on shared hosting.
- **Required Practical Protections:**
  1. **Strict Server-Side Windows:** The backend must reject question submissions after `expires_at + grace_period` regardless of client clocks.
  2. **Atomic In-Progress Persistence:** Each question answer must be persisted immediately via background Livewire/fetch calls, preventing total data loss on disconnect.
  3. **Event Audit Logging:** Record attempt metadata: IP address, user agent, attempt duration, question review duration, and unexpected focus-loss count (tab switches).
  4. **Question & Option Randomization:** Pull questions randomly from category pools and shuffle answer choice order so no two exams are identical.

### 3.2 Media Authorization & Enrollment Validity Semantics
A deep audit of [`PrivateMediaController`](file:///C:/laragon/www/seo-academy/app/Http/Controllers/PrivateMediaController.php) and [`PrivateMediaService::authorize()`](file:///C:/laragon/www/seo-academy/app/Domain/Video/Services/PrivateMediaService.php#L107-L145) reveals critical discrepancies between stored lifecycle states and temporal eligibility:

1. **`isActive()` vs `isValid()` Discrepancy:**
   - [`Enrollment::isActive()`](file:///C:/laragon/www/seo-academy/app/Domain/Students/Models/Enrollment.php#L119-L122) checks only whether the database column `status` is `'active'`.
   - [`Enrollment::isValid()`](file:///C:/laragon/www/seo-academy/app/Domain/Students/Models/Enrollment.php#L131-L148) checks that `status === 'active'` **AND** `starts_at <= now()` **AND** `expires_at >= now()`.
   - **Vulnerability:** Checking `status === 'active'` alone creates an authorization leak: an enrollment that has passed its `expires_at` date remains technically `active` in the database until the scheduled `seo-academy:process-expired-enrollments` cron job executes. Access checks must strictly evaluate `isValid()`.

2. **Completed Course Lockout Bug (Critical):**
   - In [`PrivateMediaService.php` (lines 134-137)](file:///C:/laragon/www/seo-academy/app/Domain/Video/Services/PrivateMediaService.php#L134-L137):
     ```php
     return $user->studentProfile?->enrollments()
         ->valid()
         ->where('course_id', $courseId)
         ->exists() ?? false;
     ```
   - Because `scopeValid()` strictly requires `where('status', 'active')`, **the exact moment a student completes a course and their enrollment transitions to `completed`, they are instantly locked out of all video streams, PDF materials, and private files for that course**.
   - **Required Architecture (Completed Course Access Policy):**
     - Students must retain **permanent read-only access** to the learning materials, videos, and curriculum of completed courses.
     - Media authorization and classroom access must authorize users who have an enrollment that is `isValid()` **OR** has `status === 'completed'` (unless subsequently `suspended` or `cancelled`).
     - Form submissions (retaking quizzes for grade, resubmitting assignments) remain closed for completed enrollments, but learning content consumption remains unlocked.

### 3.3 Video Playback Telemetry vs. Verified Completion
- **Client Heartbeat Vulnerability:**
  - Client-side video playback heartbeats (`last_position_seconds`, `watch_seconds`) sent via JavaScript/Livewire are **progress telemetry**, not unquestionable proof that a student genuinely watched the video.
  - A malicious or impatient student could inspect network requests, spoof heartbeat beacons, or scrub the video to 99% in 5 seconds to fraudulently trigger completion.
- **Required Safeguards:**
  1. **Server-Side Watch-Time Delta Validation:** The server must calculate the real-time elapsed seconds between consecutive heartbeat pings. If a client claims 300 seconds of watch time across a 15-second interval, the telemetry is clamped and rejected.
  2. **Completion Threshold vs Telemetry:** Fast-forwarding or scrubbing to the end of a video must not mark a lesson complete unless the cumulative `watch_seconds` reaches the lesson's configured `completion_threshold_percent` (e.g. >= 80% of total video duration).
  3. **Explicit Completion Intent:** Where appropriate, require an explicit student interaction ("Mark Lesson Complete") backed by server-side verification of progress telemetry.

### 3.4 Public Certificate Verification Privacy
- Public certificate verification is essential for graduates to prove credentials to employers.
- **Privacy Risk:** If the public endpoint (`/verify/{certificate_number}`) exposes the student's email address, student ID, full address, or phone number, it violates basic data privacy principles and data minimization regulations.
- **Fix Required:** The verification response must expose ONLY: Graduate Full Name, Course Title, Issue Date, Academy Name, Certificate Status (`Valid`, `Revoked`), and Certificate UUID. Never expose student email, individual grades, or internal user IDs.

### 3.5 Gradebook Tampering Prevention
- Grades must be immutable once finalized, except through explicit manual overrides by authorized trainers/admins.
- Any manual grade adjustment must require an explicit reason and create an irreversible audit log entry in `audit_log`.

### 3.6 Student Portfolio Privacy & Quality Gates
- Exposing student SEO deliverables (website audits, client names, keyword sheets) publicly without explicit controls presents severe privacy, client confidentiality, and institutional reputation risks.
- **Required Architecture:**
  1. **Private by Default:** All student projects, audits, and case studies are strictly private between the student and assigned trainers.
  2. **Explicit Student Opt-In:** A student must explicitly choose which projects to include in their portfolio.
  3. **Mandatory Trainer Approval:** A student portfolio cannot become publicly accessible until an assigned trainer or academy administrator reviews the deliverables, verifies that client confidential data is redacted, and explicitly approves the portfolio for publication.
  4. **Student Revocation:** Students can toggle their public portfolio offline at any time.

---

## 4. Database & Architectural Concerns

### 4.1 Schema Limitations in Legacy Phase 1 Tables
The initial Phase 1 migrations (`2026_01_01_000010` through `2026_01_01_000160`) created bare skeleton tables for future features that lack modern architectural columns:
1. **`assignments` table:** Lacks `academy_id` (violating `BelongsToAcademy`), `max_score`, `passing_score`, `grading_type`, `rubric`, `resubmission_allowed`, `max_attempts`.
2. **`assignment_submissions` table:** Lacks `submitted_at`, `attempt_number`, `feedback`, `graded_by`, `graded_at`.
3. **`quizzes` table:** Lacks `academy_id`, `time_limit_minutes`, `attempt_limit`, `randomize_questions`, `randomize_answers`, `show_correct_answers`.
4. **`quiz_submissions` table:** Lacks `attempt_number`, `started_at`, `answers` (JSON capture of choices), `feedback`.
5. **`certificates` table:** Lacks `academy_id`, `template_id`, `revoked_at`, `revocation_reason`, `metadata`.
6. **`live_classes` table:** Lacks `academy_id`, `duration_minutes`, `platform`, `recording_url`.
7. **`attendance_records` table:** Lacks `academy_id`, `status` (`present`, `absent`, `late`, `excused`).

**Critical Invariant:** All schema upgrades MUST be implemented through new, numbered migrations following the established pattern. Never alter or drop existing historical migrations.

### 4.2 Centralized Course Completion Architecture
Currently, course completion is checked nowhere; an administrator must manually toggle an enrollment status in `EnrollmentsIndex.php`.
- **Architectural Requirement:** Course completion rules must be encapsulated into a single, centralized domain service (`App\Domain\Courses\Services\CourseCompletionService`) rather than scattered ad-hoc checks across controllers or Livewire components.
- **Evaluation Criteria:**
  1. **Curriculum Requirements:** All mandatory modules and lessons completed (`LessonProgress::where('status', 'completed')`).
  2. **Assessment Requirements:** All mandatory assignments graded with score >= passing grade; all mandatory quizzes passed.
  3. **Attendance Requirements:** If live classes have mandatory attendance, student attendance percentage meets the configured threshold (e.g. >= 80%).
  4. **Exam Requirements:** Scheduled final examination passed (if configured).
- Centralizing this service upfront ensures that Phase 3 (Classroom), Phase 4 (Assessments), and Phase 5 (Certificates) all query the exact same source of truth.

### 4.3 Unified Assessment Engine (Anti-Duplication Architecture)
Building question banks, practice quizzes, graded module quizzes, and scheduled examinations as separate silos would duplicate 80% of data structures and grading logic.
- **Architectural Requirement:** Design a unified assessment core:
  1. **Shared Question Bank:** Questions (`question_banks`, `questions`, `question_options`) exist in reusable pools categorized by topic, difficulty, and question type (MCQ, Multiple-Select, True/False, Short Answer, Essay).
  2. **Polymorphic Assessment Container:** Quizzes and Examinations share the same evaluation engine, differing only in configuration (`is_scheduled_exam`, `time_limit_minutes`, `attempt_limit`, `require_proctor_audit`).
  3. **Unified Attempt Engine:** `assessment_attempts` captures started timestamp, expiration timestamp, periodic answer saves (atomic recovery), final score, pass/fail status, and audit metadata across both quizzes and examinations.

### 4.4 SQLite & MySQL InnoDB Dual Compatibility
Every new migration must:
- Use standard data types compatible across SQLite and MySQL InnoDB (`varchar(36)` for UUIDs, `json` with appropriate fallbacks, `decimal(5,2)` for scores).
- Support transactional DDL and rollback.
- Avoid MySQL-specific raw queries (`ALTER TABLE ... ADD CONSTRAINT`) that fail on SQLite.

---

## 5. Test Coverage Gaps

While the current regression test suite has 100% pass rate (247 passed, 1,544 assertions), it covers **only the administrative and operational features** built in Phases 1–2.7:

| Domain | Existing Test Count | Test Gaps / Missing Coverage |
|---|---|---|
| **Installation & Safety** | 21 tests (132 assertions) | Fully covered across SQLite and MySQL. |
| **System Operations & Backups** | 8 tests | Backup creation, restore, rollback, retention covered. |
| **Support Helpdesk** | 3 tests | Ticket lifecycle and message author retention covered. |
| **Notifications** | 1 test (11 assertions) | Center and preferences covered. |
| **Course & Structure Admin** | 3 tests | CRUD, hierarchy, media attachment covered. |
| **Enrollments & Invites** | 3 tests | Lifecycle, transitions, security covered. |
| **Student Learning Classroom** | **0 tests** | **Zero tests** for lesson playback, completion, or resume. |
| **Assignments & Submissions** | **0 tests** | **Zero tests** for assignment creation, submission, or grading. |
| **Quizzes & Questions** | **0 tests** | **Zero tests** for quiz taking, question validation, or scoring. |
| **Examinations & Timeouts** | **0 tests** | **Zero tests** for timed attempts or audit logs. |
| **Gradebook & Calculations** | **0 tests** | **Zero tests** for weighted grades or transcripts. |
| **Certificates & Verification** | **0 tests** | **Zero tests** for issuance, PDF generation, or verification. |
| **Live Classes & Attendance** | **0 tests** | **Zero tests** for attendance marking or reporting. |
| **SEO Practical Projects** | **0 tests** | **Zero tests** for audit checklists or portfolio projects. |

