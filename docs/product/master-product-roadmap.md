# SEO Academy — Master Commercial Product Roadmap

**Product:** SEO Academy  
**Directory:** `C:\laragon\www\seo-academy`  
**Architecture:** Commercial, Self-Hosted, Single-Academy Learning Management System  
**Current Baseline:** Phases 1 – 2.7 Complete (Operational Foundation & Installer Approved)  
**Target Goal:** Complete Commercial LMS Ready for Independent Customer Distribution  
**Document Version:** 1.0 (Production Roadmap)

---

## 1. Product Vision & Architectural Invariants

SEO Academy is a standalone, self-hosted Learning Management System designed specifically for independent SEO and digital marketing training academies. Each customer purchases, installs, and operates their own private academy on conventional PHP web hosting.

### Permanent Architectural Rules
1. **Single Academy per Installation:** Exactly one academy instance per deployment. No multi-tenant cross-academy leakage, no platform-level super-admins, no academy switching.
2. **Four Local Roles Only:** `admin`, `trainer`, `support_staff`, and `student`. Managed via Spatie Permission and stored locally.
3. **Institutional Student Management:** **Zero public student registration.** Only administrators create student accounts and assign courses. No student self-enrollment, no public checkout, and no payment-triggered enrollment.
4. **Dual Database Engine Support:** Seamless support for **SQLite 3.35+** (zero-configuration single file) and **MySQL 8.0+** (InnoDB transactional snapshots). The supported database matrix is strictly limited to SQLite and MySQL until MariaDB compatibility has been separately approved and tested. All schema modifications must use standard migrations.
5. **Shared-Hosting-First Deployment:** Operates efficiently on standard LAMP/LEMP stacks (cPanel, DirectAdmin, shared VPS) without requiring Docker, Redis, root access, permanent daemon workers, or a Node.js runtime.
6. **No Mandatory Paid External Services:** All core functionality (video streaming, grading, quizzes, certificates, backups, and support) operates without paid third-party APIs. AI features and live meeting integrations must remain strictly optional and support self-hosted or BYO credentials.
7. **Strict Enrollment Validity & Completed Course Access:**
   - Authorization gates must evaluate **temporal eligibility** via [`Enrollment::isValid()`](file:///C:/laragon/www/seo-academy/app/Domain/Students/Models/Enrollment.php#L131-L148) (`status === 'active'` AND current time within `starts_at`/`expires_at`), rather than relying on stored lifecycle state (`isActive()`) in isolation.
   - **Completed Course Read-Only Guarantee:** When an enrollment transitions to `completed`, students retain permanent **Read-Only Access** to the course curriculum, lesson text, video streams, and downloadable materials. Only write-actions (new assignment submissions, quiz retakes) are closed. Suspended or cancelled enrollments immediately lose all access.
8. **Student Portfolio Privacy & Quality Gates:** Student portfolios are **private by default** and **opt-in**. No student deliverable or portfolio is publicly visible until explicitly approved by an assigned trainer or administrator.

---

## 2. Full Target Feature Catalogue

The complete commercial LMS feature catalogue spans eight primary educational and administrative pillars:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           TARGET FEATURE CATALOGUE                          │
├─────────────────────────────────────────────────────────────────────────────┤
│ 1. Student Learning Classroom                                               │
│    ├── Dedicated Video / PDF / External / Text Classroom Player             │
│    ├── Persistent Curriculum Drawer & Next/Previous Lesson Stepper          │
│    ├── Automated Heartbeat Progress Beacon & "Mark Complete" Toggle         │
│    ├── 1-Click "Resume Last Lesson" with Video Timestamp Persistence        │
│    └── Prerequisite Runtime Gates & Active Enrollment Security              │
├─────────────────────────────────────────────────────────────────────────────┤
│ 2. Practical Assignment & Submission System                                 │
│    ├── Trainer Assignment Authoring with Instructions & Vaulted Attachments │
│    ├── Individual Student Submissions (File Uploads & Rich Text Notes)      │
│    ├── Versioned Submissions, Revision Requests & Resubmission Workflows    │
│    ├── Flexible Deadline Enforcement & Configurable Late Submission Flags   │
│    └── Structured Rubrics, Trainer Written Feedback & Grade Visibility      │
├─────────────────────────────────────────────────────────────────────────────┤
│ 3. Comprehensive Quiz & Examination Engine                                  │
│    ├── Centralized Question Bank (Categorized, Tagged & Pool-Enabled)       │
│    ├── Multiple Question Types (MCQ, Multiple-Select, True/False, Short,    │
│    │   and Essay Questions)                                                 │
│    ├── Time-Limited Quizzes, Attempt Limits & Randomized Questions/Answers  │
│    ├── Automated Grading Engine for Objective Question Types                │
│    ├── Manual Trainer Grading Queue for Open-Ended Essay Responses          │
│    └── Scheduled Examinations with Atomic Answer Persistence & Audit Log    │
├─────────────────────────────────────────────────────────────────────────────┤
│ 4. Centralized Gradebook & Academic Transcripts                             │
│    ├── Weighted Course Grading (Assignments, Quizzes, Exams & Practical)    │
│    ├── Configurable Passing Thresholds & Overall Letter/Percentage Grades   │
│    ├── Trainer Grade Overrides with Mandatory Reason & Tamper-Evident Audit │
│    ├── Student Academic Transcripts & Progress Histories                    │
│    └── Complete Gradebook CSV / Excel Export for Administrative Records     │
├─────────────────────────────────────────────────────────────────────────────┤
│ 5. Automated Certification & Public Verification Engine                     │
│    ├── Template-Based Certificate Builder with Academy Branding             │
│    ├── Multi-Criteria Automated Issuance (Lessons + Grades + Attendance)    │
│    ├── Server-Side Vector/Raster PDF Certificate Generation                 │
│    ├── Unique Certificate Serial Numbers & Anti-Tamper Verification Hashes  │
│    ├── Public Certificate Verification Portal (Strict Zero-PII Disclosure)  │
│    └── Certificate Revocation, Reissuance & Administrative Audit Trail      │
├─────────────────────────────────────────────────────────────────────────────┤
│ 6. Practical SEO Learning Environment & Portfolios                          │
│    ├── Structured Technical SEO Audit Checklists (Crawl, CWV, Schema)       │
│    ├── Keyword Research Exercises with CSV / Spreadsheet Upload Parsers     │
│    ├── SEO Campaign Milestones (On-Page, Backlinks, Competitor Benchmarks)  │
│    ├── Optional GA4 & Google Search Console Screenshot / Report Milestones  │
│    └── Student SEO Portfolio Showcase for Prospective Client Proof-of-Work  │
├─────────────────────────────────────────────────────────────────────────────┤
│ 7. Live Learning, Attendance & Community Collaboration                      │
│    ├── Live Class Scheduling (Self-Hosted Jitsi Meet & BigBlueButton Links) │
│    ├── Trainer Live Attendance Recording (Present, Absent, Late, Excused)   │
│    ├── Student Attendance Percentage Tracking & Course Completion Thresholds│
│    └── Course & Lesson Discussion Forums with Threading & Staff Moderation  │
├─────────────────────────────────────────────────────────────────────────────┤
│ 8. Executive Learning Analytics & Optional AI Assistance                    │
│    ├── Student Engagement Funnels, Drop-Off Analysis & Completion Rates     │
│    ├── Trainer Grading Velocity & Quiz Question Difficulty Analysis         │
│    └── Optional BYO-Key AI Assignment Feedback Drafting & Keyword Tools     │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Phased Implementation Roadmap

Development is organized into six targeted phases (Phase 3 through Phase 8), building strictly upon completed operational foundation code without repeating or refactoring prior work.

---

### Phase 3: Core Student Classroom & Learning Progress Engine

**Objective:** Deliver the primary student learning player interface, curriculum navigation stepper, real-time progress tracking, media authorization security fixes, and central course completion evaluation.

#### Technical Deliverables:
1. **Security & Media Authorization Patch:**
   - Audit and update [`PrivateMediaService::authorize()`](file:///C:/laragon/www/seo-academy/app/Domain/Video/Services/PrivateMediaService.php#L134-L137):
     ```php
     return $user->studentProfile?->enrollments()
         ->where('course_id', $courseId)
         ->where(function (Builder $q): void {
             $q->valid()->orWhere('status', Enrollment::STATUS_COMPLETED);
         })
         ->exists() ?? false;
     ```
   - Guarantees students with `completed` enrollments retain permanent read-only streaming access to their learning materials, while `expired`, `suspended`, or `cancelled` enrollments are strictly locked out.
2. **Migrations:**
   - `add_last_active_lesson_to_enrollments_table`: Adds `last_active_lesson_id` (UUID foreign key) and `last_active_at` (timestamp) to `enrollments`.
3. **Models & Enums:**
   - Enhance [`App\Domain\Learning\Models\LessonProgress`](file:///C:/laragon/www/seo-academy/app/Domain/Learning/Models/LessonProgress.php) with helper methods (`markCompleted()`, `updatePlaybackPosition()`, `isCompleted()`).
4. **Services:**
   - `App\Domain\Courses\Services\CourseCompletionService`: **Central course completion evaluator**. Evaluates whether a student has fulfilled all module completion rules, minimum watch thresholds, and course requirements. Central source of truth before Phase 4 assessments and Phase 5 certifications depend on it.
   - `App\Domain\Learning\Services\LearningProgressService`:
     - **Telemetry vs Verified Completion:** Heartbeat pings (`last_position_seconds`) are treated strictly as playback position telemetry to support resume functionality.
     - **Anti-Scrubbing Server Checks:** Verifies elapsed real-time delta between pings before crediting `watch_seconds`. Fast-forwarding or scrubbing does not trigger automatic completion unless the verified watch time satisfies the lesson's `completion_threshold_percent`.
     - Updates `lesson_progress`, triggers prerequisite recalculation, and updates enrollment progress percentages.
   - `App\Domain\Learning\Services\CourseNavigationService`: Resolves previous/next lesson navigation respecting display order and prerequisite gates.
5. **Livewire Components & Views:**
   - `App\Livewire\Student\ClassroomPlayer`: The full student learning interface with responsive layout, collapsible curriculum sidebar, video/pdf/text player container, and lesson navigation controls.
   - `resources/views/livewire/student/classroom-player.blade.php`: Modern Alpine.js-enhanced classroom interface.
6. **Routes & Middleware Gates:**
   - `GET /student/courses/{course}/learn/{lesson?}`: Guarded by `EnsureAcademyAccess` and custom policy checking `Enrollment::isValid() || isCompleted()`.
   - `POST /student/courses/{course}/lessons/{lesson}/complete`: Marks lesson complete.
   - `POST /student/courses/{course}/lessons/{lesson}/progress`: Heartbeat telemetry for video position.

#### Acceptance Criteria & Definition of Done:
- [ ] Students with `valid` or `completed` enrollments can open courses and view lessons in the classroom player.
- [ ] Students with `completed` enrollments retain full read-only access to video streams, lesson text, and downloadable materials.
- [ ] Suspended, expired, or non-enrolled students are strictly denied access (`403 Forbidden`).
- [ ] The classroom sidebar accurately highlights completed lessons, the current lesson, and locked prerequisite lessons.
- [ ] Client heartbeat pings are validated server-side; fast-forwarding or skipping cannot spoof watch time.
- [ ] Clicking "Mark Complete" persists progress in `lesson_progress` and updates the student dashboard counter.
- [ ] "Resume Course" on `My Courses` navigates directly to the student's `last_active_lesson_id` and restores video position.
- [ ] `CourseCompletionService` evaluates all module rules centrally and updates enrollment status when criteria are satisfied.

---

### Phase 4: Unified Assessment & Practical Evaluation Engine

**Objective:** Implement a unified assessment core (shared question banks, practice quizzes, graded module quizzes, and scheduled examinations) alongside practical assignment authoring, student file/text submissions, rubrics, and trainer grading.

#### Architectural Anti-Duplication Strategy:
Instead of building question banks, quizzes, and examinations as separate codebases with duplicate models and grading engines, Phase 4 delivers a **unified assessment core**:
- **`question_banks` & `questions`:** Reusable question repository supporting MCQ, Multiple-Select, True/False, Short Answer, and Essay. Tagged by category and difficulty.
- **`assessments` (or polymorphic `quizzes`):** A single assessment container configurable as `practice_quiz`, `graded_quiz`, or `scheduled_examination` (`time_limit_minutes`, `attempt_limit`, `is_scheduled_exam`, `start_time`, `end_time`, `randomize_questions`, `randomize_answers`, `pass_mark`).
- **`assessment_attempts`:** A unified attempt engine tracking student answers (atomic periodic persistence), start/submit timestamps, score, pass/fail status, and exam audit telemetry.
- **Single Auto-Grading Engine:** Objective question scoring and manual essay grading queue shared seamlessly across quizzes and exams.

#### Technical Deliverables:
1. **Migrations:**
   - `update_assignments_table_for_phase4`: Adds `academy_id`, `max_score`, `passing_score`, `grading_type`, `rubric` (JSON), `resubmission_allowed`, `max_attempts` to `assignments`.
   - `update_assignment_submissions_table_for_phase4`: Adds `submitted_at`, `attempt_number`, `feedback`, `graded_by`, `graded_at` to `assignment_submissions`.
   - `create_unified_question_banks_table`: Question categories, difficulty levels, and tags.
   - `create_unified_questions_table`: Question text, type, options (JSON), correct answer, points, explanation.
   - `update_quizzes_table_for_phase4`: Extends quizzes to serve as unified assessment containers (supports practice, graded quiz, or scheduled exam).
   - `create_assessment_attempts_table`: Unified attempt engine with atomic answer recovery and timer enforcement.
2. **Services:**
   - `App\Domain\Assignments\Services\AssignmentManagementService`: Handles assignment authoring, private submission storage via `PrivateMediaService`, and trainer rubric grading.
   - `App\Domain\Assessments\Services\AssessmentService`: Core assessment engine: question pool selection, attempt lifecycle, atomic answer saving, auto-grading, and score compilation.
   - `App\Domain\Assessments\Services\ExaminationScheduleService`: Manages scheduled exam windows and enforces server-side expiration submission.
3. **Livewire Components:**
   - `App\Livewire\Trainer\AssignmentEditor`: Trainer assignment creator/editor.
   - `App\Livewire\Trainer\AssignmentGradingIndex`: Trainer grading queue with submission preview and rubric grading.
   - `App\Livewire\Student\AssignmentWorkspace`: Student submission upload interface and feedback review.
   - `App\Livewire\Trainer\QuestionBankManager`: Unified question pool manager.
   - `App\Livewire\Student\AssessmentRunner`: Unified timed quiz and exam runner with atomic answer saving and option shuffling.
4. **Notifications:**
   - Triggers `AssignmentSubmittedNotification`, `AssignmentGradedNotification`, `AssessmentCompletedNotification`.

#### Acceptance Criteria & Definition of Done:
- [ ] Quizzes, practice tests, and examinations share the same unified question bank and attempt engine without duplicate schemas.
- [ ] Objective questions auto-grade instantly upon submission.
- [ ] Open-ended essay questions queue into the trainer grading inbox for manual mark entry and feedback.
- [ ] Examination timers are enforced server-side; network disconnects recover saved answers on reconnect.
- [ ] Assignment submissions are stored safely in the private media vault outside webroot.

---

### Phase 5: Academic Records, Gradebook & Certification

**Objective:** Centralize all student performance records into a weighted gradebook, provide student transcripts, generate branded PDF certificates, and provide a public zero-PII certificate verification portal.

#### Technical Deliverables:
1. **Migrations:**
   - `update_certificates_table_for_phase5`: Adds `academy_id`, `template_id`, `revoked_at`, `revocation_reason`, `verification_hash`, `metadata` (JSON).
   - `create_course_grade_weights_table`: Stores grading weight configuration per course (e.g. 40% assignments, 30% quizzes, 30% final project).
2. **Services:**
   - `App\Domain\Grading\Services\GradebookService`: Calculates cumulative percentage grades, letter grades, and GPA equivalents based on component weights. Handles manual overrides with `audit_log` records.
   - `App\Domain\Certificates\Services\CertificateIssuanceService`: Evaluates course completion criteria (100% lessons completed, passing overall grade, attendance requirement). Automatically issues certificate with unique serial number and hash.
   - `App\Domain\Certificates\Services\CertificatePdfGenerator`: Generates lightweight, high-resolution PDF certificate using native HTML/CSS rendering (Dompdf or Snappy) with academy branding, colors, and signatures.
3. **Livewire Components:**
   - `App\Livewire\Admin\GradebookIndex`: Full academy/course gradebook matrix.
   - `App\Livewire\Student\AcademicTranscript`: Student transcript and grade report view.
   - `App\Livewire\Admin\CertificateTemplates`: Certificate designer with live preview.
4. **Public Verification Route:**
   - `GET /verify/{serial_number}`: Resolves certificate by unique number. Displays graduate name, course, issue date, and validity status. **Strictly redacts email, user ID, and private data.**

#### Acceptance Criteria & Definition of Done:
- [ ] Cumulative course grades calculate automatically from assignments, quizzes, and projects based on configurable weights.
- [ ] Manual grade overrides require an audit explanation and are logged in `audit_log`.
- [ ] Students meeting all completion criteria receive automated certificate issuance.
- [ ] Generated PDF certificates match academy branding and download cleanly.
- [ ] Public verification portal confirms validity without exposing sensitive student PII.

---

### Phase 6: Practical SEO Learning Environment & Portfolios

**Objective:** Transform theoretical learning into practical hands-on SEO execution with technical audit checklists, keyword research workspaces, campaign milestones, and student portfolio showcases.

#### Technical Deliverables:
1. **Migrations:**
   - `create_seo_projects_table`: Project blueprints for website audits, keyword research, and campaigns.
   - `create_seo_project_milestones_table`: Milestone steps, audit checklists, and deliverable specifications.
   - `create_student_portfolios_table`: Student public portfolio profiles and showcase items.
2. **Services:**
   - `App\Domain\SeoProjects\Services\SeoProjectService`: Manages project blueprints, checklist validations, and milestone reviews.
   - `App\Domain\SeoProjects\Services\KeywordSpreadsheetParser`: Safe validation and parsing of uploaded student keyword research spreadsheets (CSV/XLSX).
3. **Livewire Components:**
   - `App\Livewire\Student\TechnicalAuditChecklist`: Interactive checklist covering HTTP status codes, robots.txt, XML sitemaps, canonical tags, schema markup, and Core Web Vitals.
   - `App\Livewire\Student\KeywordResearchWorkspace`: Spreadsheet submission and clustering tool.
   - `App\Livewire\Student\SeoCampaignTracker`: Before-and-after ranking evidence, GA4/GSC screenshot milestone submissions.
   - `App\Livewire\Student\PortfolioManager`: Student interface to opt-in, curate completed case studies, redact client details, and submit for trainer review.
   - `App\Livewire\Trainer\PortfolioReviewQueue`: Trainer review interface to verify deliverable quality and approve/reject publication.
   - `App\Livewire\Public\PublicPortfolioView`: Unlisted/tokenized public portfolio view (**strictly private by default; requires prior trainer approval**).

#### Acceptance Criteria & Definition of Done:
- [ ] Students complete practical website audits with structured checklists and evidentiary notes.
- [ ] Keyword research spreadsheets are safely parsed, validated, and reviewed by trainers.
- [ ] Student portfolios are **private by default** and **opt-in**; no deliverable is published automatically.
- [ ] Mandatory trainer approval workflow is enforced before any public portfolio showcase URL is activated.
- [ ] Students can toggle public visibility off or revoke showcase items at any time.

---

### Phase 7: Live Classes, Attendance & Course Discussions

**Objective:** Integrate live online learning via self-hosted open-source video tools (Jitsi Meet, BigBlueButton), automate attendance recording, and provide course discussion boards.

#### Technical Deliverables:
1. **Migrations:**
   - `update_live_classes_table_for_phase7`: Adds `academy_id`, `duration_minutes`, `platform` (`jitsi`, `bigbluebutton`, `external`), `recording_url`, `is_attendance_mandatory`.
   - `update_attendance_records_table_for_phase7`: Adds `academy_id`, `status` (`present`, `absent`, `late`, `excused`), `joined_at`, `duration_minutes`.
   - `create_course_discussions_table`: Threaded discussions attached to courses and lessons.
2. **Services:**
   - `App\Domain\LiveClasses\Services\LiveClassService`: Generates room tokens for Jitsi Meet / BigBlueButton without requiring external paid subscriptions.
   - `App\Domain\Attendance\Services\AttendanceService`: Compiles attendance percentages per student and cohort.
3. **Livewire Components:**
   - `App\Livewire\Trainer\LiveClassManager`: Schedule and launch live classes.
   - `App\Livewire\Trainer\AttendanceRoster`: 1-click attendance marking interface.
   - `App\Livewire\Community\CourseDiscussionForum`: Threaded discussion component with question marking, trainer endorsement badges, and staff moderation.

#### Acceptance Criteria & Definition of Done:
- [ ] Trainers can schedule live sessions and students can join with 1 click.
- [ ] Trainers can record attendance quickly with batch "Mark All Present" tools.
- [ ] Threaded discussions allow Q&A per lesson with trainer endorsement badges.

---

### Phase 8: Commercial Learning Analytics & Optional AI Assistance

**Objective:** Provide executive learning analytics, drop-off funnel reporting, and optional BYO-key AI assignment feedback.

#### Technical Deliverables:
1. **Services:**
   - `App\Domain\Analytics\Services\LearningAnalyticsService`: Aggregates enrollment completion rates, average time-to-complete, quiz question difficulty ratings, and trainer grading turnaround times.
2. **Livewire Components:**
   - `App\Livewire\Admin\LearningAnalyticsDashboard`: Visual charts and CSV data exports.
   - `App\Livewire\Trainer\CoursePerformanceReport`: Question-level failure analysis.
3. **Optional AI Assistance:**
   - `App\Domain\AI\Services\AiGradingAssistant`: Drafts constructive feedback on assignment submissions based on trainer rubrics (requires admin-enabled OpenAI/Anthropic BYO API key).
   - Cleanly disabled by default with zero frontend key leakage.

#### Acceptance Criteria & Definition of Done:
- [ ] Administrators can view true completion funnels and export gradebook data to CSV.
- [ ] Trainers can identify difficult quiz questions with high failure rates.
- [ ] Optional AI tools provide draft feedback without any mandatory cloud dependencies.

---

## 4. Implementation Complexity & Effort Matrix

| Phase | Core Domain Focus | Migrations | New Models | Livewire UI | Est. Complexity | Risk Level |
|---|---|---|---|---|---|---|
| **Phase 3** | Student Classroom & Progress Engine | 1 | 0 | 1 component | **Medium** | Low |
| **Phase 4** | Assignments, Quizzes & Exams | 6 | 4 | 5 components | **High** | Medium |
| **Phase 5** | Gradebook & Certificate Generation | 2 | 2 | 3 components | **Medium** | Low |
| **Phase 6** | Practical SEO Projects & Portfolios | 3 | 3 | 4 components | **Medium** | Low |
| **Phase 7** | Live Classes, Attendance & Discussions | 3 | 1 | 3 components | **Medium** | Medium |
| **Phase 8** | Learning Analytics & Optional AI | 0 | 0 | 2 components | **Low** | Low |

---

## 5. Recommended Next Implementation Step

### **Immediate Recommendation: Implement Phase 3 (Core Student Classroom & Learning Progress Engine)**

#### Immediate Action Plan for Phase 3:
1. **Security & Media Authorization Patch:** Update [`PrivateMediaService::authorize()`](file:///C:/laragon/www/seo-academy/app/Domain/Video/Services/PrivateMediaService.php#L134-L137) to permit access if enrollment `isValid()` OR `status === 'completed'`. Resolves the bug where course completion inadvertently revokes private video and file access.
2. **Central Course Completion Service:** Implement `CourseCompletionService` upfront to establish unambiguous, centralized completion rules across lessons, quizzes, assignments, and attendance before Phase 4 assessments and Phase 5 certifications depend on them.
3. **Single Database Migration:** Add `last_active_lesson_id` (UUID foreign key) and `last_active_at` (timestamp) to `enrollments`.
4. **Learning Progress Service with Anti-Scrubbing Telemetry:** Implement `LearningProgressService` treating playback heartbeats as position telemetry with server-side real-time delta checks, requiring verified cumulative watch time to hit `completion_threshold_percent` before completion is credited.
5. **Student Classroom Livewire Component:** Create `App\Livewire\Student\ClassroomPlayer` with:
   - Responsive video/PDF/text player container.
   - Collapsible curriculum sidebar with module progress percentages and locked/unlocked prerequisite indicators.
   - Previous and Next lesson navigation buttons.
   - "Mark as Completed" button triggering verified progress updates.
   - Periodic video playback position beacon for seamless resumption.
6. **Update Student Navigation:** Wire the "Resume Course" button on `My Courses` (`StudentCoursesIndex`) and `StudentDashboard` to redirect directly to the student's active lesson in the classroom player.
7. **Security Gates:** Enforce `EnsureAcademyAccess` and custom policy checking `Enrollment::isValid() || isCompleted()` before serving classroom content or streaming private media. Completed enrollments retain lifetime read-only access.

