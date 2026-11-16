# SEO Academy — LMS Phase Dependency Map & Architecture Graph

**Project:** SEO Academy (`C:\laragon\www\seo-academy`)  
**Scope:** Architectural Dependency Analysis & Phased Implementation Sequencing  
**Guiding Invariant:** Construct capabilities in strict foundational order so no phase requires re-migrating, re-architecting, or refactoring prior work.

---

## 1. Architectural Dependency Diagram

The following Mermaid graph visualizes the structural dependencies across SEO Academy's completed operational foundation (Phases 1–2.7) and the required commercial LMS phases.

```mermaid
flowchart TD
    subgraph Foundation["Phases 1 - 2.7: Operational Foundation (COMPLETE)"]
        AUTH["Security & Roles\n(admin, trainer, support, student)"]
        MEDIA["Private Media Vault\n(Chunked Streaming & Storage)"]
        NOTIF["Notification Center\n(In-App & Queued Email)"]
        AUDIT["Audit Logging\n(Tamper-Evident Records)"]
        COURSES["Course & Lesson Structure\n(Modules, Lessons, Prerequisites)"]
        ENROLL["Enrollment Engine\n(Lifecycle, Validity & Expiry)"]
        BACKUP["Backup & Recovery\n(Consistent InnoDB/SQLite Snapshots)"]
    end

    subgraph Phase3["Phase 3: Student Learning Experience & Progress Engine"]
        CLASSROOM["Student Classroom Player\n(Lesson Stepper & Curriculum Nav)"]
        PROGRESS["Lesson Progress Engine\n(Completion Beacon, Position & Resume)"]
        PREREQ_RUN["Prerequisite Runtime Gate\n(Enforce Sequential Learning)"]
        COMPLETION["Course Completion Engine\n(Progress Evaluation & Triggers)"]
    end

    subgraph Phase4["Phase 4: Assessment & Practical Evaluation Engine"]
        ASSIGN["Assignment System\n(Instructions, Attachments & Deadlines)"]
        SUBMIT["Student Submissions\n(File Uploads & Text Workspace)"]
        GRADE_ASSIGN["Trainer Assignment Grading\n(Rubrics, Scores & Revision Feedback)"]
        QBANK["Question Bank & Pools\n(Multi-Type, Categorized & Tagged)"]
        QUIZ["Quiz Engine\n(Timers, Shuffle, Auto-Grading & Attempts)"]
        EXAM["Examination System\n(Scheduled Windows, Recovery & Audit)"]
    end

    subgraph Phase5["Phase 5: Academic Records, Gradebook & Certification"]
        GRADEBOOK["Centralized Gradebook\n(Weighted Course Components)"]
        TRANSCRIPT["Student Academic Transcripts\n(Itemized Scores & GPA/Standing)"]
        CERT_ENGINE["Certificate Issuance Engine\n(Eligibility Rules & Unique IDs)"]
        CERT_PDF["Certificate PDF Renderer\n(Academy Branding & Layouts)"]
        CERT_VERIFY["Public Verification Portal\n(Zero-PII Credential Authenticity)"]
    end

    subgraph Phase6["Phase 6: Practical SEO Workshop & Student Portfolios"]
        SEO_AUDIT["Technical SEO Checklists\n(Crawl, Core Web Vitals, Schema)"]
        SEO_KW["Keyword Research Projects\n(Spreadsheet Uploads & Clustering)"]
        SEO_CAMPAIGN["SEO Campaign Milestones\n(On-Page, Backlinks, GA4/GSC Proof)"]
        PORTFOLIO["Student Portfolio Showcase\n(Shareable Public Proof of Work)"]
    end

    subgraph Phase7["Phase 7: Live Learning, Community & Communications"]
        LIVE_CLASS["Live Class Scheduling\n(Jitsi Meet & BigBlueButton Links)"]
        ATTEND["Attendance Tracking\n(Trainer Recording & Student Logs)"]
        DISCUSS["Course & Lesson Discussions\n(Q&A, Comments & Moderation)"]
    end

    subgraph Phase8["Phase 8: Commercial Learning Analytics & Optional AI Tutoring"]
        ANALYTICS["Executive & Trainer Analytics\n(Funnels, Velocity, Drop-Offs & Exports)"]
        AI_TUTOR["Optional AI Learning Assistant\n(Assignment Feedback & SEO Guidance)"]
    end

    %% Dependencies into Phase 3
    COURSES --> CLASSROOM
    MEDIA --> CLASSROOM
    ENROLL --> CLASSROOM
    CLASSROOM --> PROGRESS
    PROGRESS --> PREREQ_RUN
    PROGRESS --> COMPLETION

    %% Dependencies into Phase 4
    CLASSROOM --> ASSIGN
    MEDIA --> SUBMIT
    ASSIGN --> SUBMIT
    SUBMIT --> GRADE_ASSIGN
    NOTIF --> GRADE_ASSIGN
    COURSES --> QBANK
    QBANK --> QUIZ
    QUIZ --> EXAM
    AUDIT --> EXAM

    %% Dependencies into Phase 5
    GRADE_ASSIGN --> GRADEBOOK
    QUIZ --> GRADEBOOK
    EXAM --> GRADEBOOK
    GRADEBOOK --> TRANSCRIPT
    COMPLETION --> CERT_ENGINE
    GRADEBOOK --> CERT_ENGINE
    CERT_ENGINE --> CERT_PDF
    CERT_ENGINE --> CERT_VERIFY

    %% Dependencies into Phase 6
    ASSIGN --> SEO_AUDIT
    ASSIGN --> SEO_KW
    SUBMIT --> SEO_CAMPAIGN
    GRADE_ASSIGN --> PORTFOLIO
    SEO_CAMPAIGN --> PORTFOLIO

    %% Dependencies into Phase 7
    COURSES --> LIVE_CLASS
    LIVE_CLASS --> ATTEND
    ATTEND --> COMPLETION
    CLASSROOM --> DISCUSS
    NOTIF --> DISCUSS

    %% Dependencies into Phase 8
    PROGRESS --> ANALYTICS
    GRADEBOOK --> ANALYTICS
    ATTEND --> ANALYTICS
    SUBMIT --> AI_TUTOR
    SEO_AUDIT --> AI_TUTOR
```

---

## 2. Shared Infrastructure Reuse Map

To maintain a lean, high-performance architecture on conventional PHP shared hosting, new LMS modules must strictly leverage existing shared infrastructure rather than introducing redundant tables, duplicate services, or foreign daemons.

| Existing Infrastructure Component | Reused By Target LMS Modules | Implementation Mechanism |
|---|---|---|
| **`PrivateMediaService` & `Media`** | Classroom, Assignments, Projects, Certificates | **Media Authorization Policy:** Must authorize users with an enrollment that is `isValid()` (active and within `starts_at`/`expires_at`) **OR** `completed` (retaining lifetime read-only access). Strictly excludes `expired`, `suspended`, or `cancelled` enrollments. Vaults all course videos, materials, homework submissions, and certificate PDFs outside webroot (`storage/app/private/`). |
| **`AcademyNotificationService`** | Assignments, Quizzes, Grading, Discussions, Live Classes | Reuses notification templates, in-app notification center, user notification preferences, and queued SMTP delivery for assignment deadlines, graded feedback alerts, quiz results, and live class reminders. |
| **`RecordsAuditLog` & `audit_log`** | Grading, Examinations, Certificates, Moderation | Grade manual overrides, exam attempt exceptions/resets, certificate revocations, and discussion moderation actions automatically write tamper-evident audit log entries with actor UUID, IP, and payload diffs. |
| **`TenantContext` & `BelongsToAcademy`** | All new models (`assignments`, `quizzes`, `certificates`, etc.) | Every new table includes `academy_id` foreign key and Eloquent models apply the `BelongsToAcademy` trait, guaranteeing that all queries strictly scope to the single installed academy without leak risks. |
| **`EnrollmentManagementService`** | Classroom Player, Assignments, Quizzes, Certificates | Access gates in the student player, quiz taker, and assignment submitter evaluate `Enrollment::isValid()` semantics (temporal window enforcement) rather than stored status alone. Coordinates with `CourseCompletionService` to transition status to `completed` while preserving read-only learning access. |
| **`CourseCompletionService` (Core)** | Classroom, Assessments, Gradebook, Certificates | Central domain evaluator enforcing multi-component completion rules (100% required lessons completed + passing assignment scores + passing quiz scores + required live attendance + final exam passed). Single source of truth across all learning workflows. |
| **`BackupService`** | Complete Application | Because `BackupService` dynamically reads the PDO table registry and streams all InnoDB/SQLite tables into consistent snapshot dumps, all new tables added via migrations are automatically protected without modifying backup code. |
| **`DatabaseInstallerService`** | Clean Release & Updates | New migrations are appended to `KNOWN_MIGRATIONS`, ensuring fresh installations and installer verification seamlessly incorporate all LMS schema objects. |
| **`AiProviderInterface` & `NullAiProvider`** | AI Tutoring & Grading Assistance | Optional AI features utilize the existing `OpenAiCompatibleProvider` and fallback cleanly to `NullAiProvider` when unconfigured, requiring zero frontend key exposure or mandatory subscriptions. |

---

## 3. Strict Phased Implementation Sequence

Building the LMS in the correct logical sequence prevents rework, duplicate migrations, and schema churn:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                       STRICT IMPLEMENTATION SEQUENCE                        │
├─────────────────────────────────────────────────────────────────────────────┤
│  Phase 3: Core Student Classroom & Learning Progress Engine                 │
│  ├── 3.1: Student Classroom Player & Curriculum Sidebar Navigation          │
│  ├── 3.2: Media Authorization Re-Audit (`isValid()` || `isCompleted()`)     │
│  ├── 3.3: Lesson Progress Service (Telemetry vs Server-Validated Watch Time)│
│  ├── 3.4: Lesson Prerequisite Runtime Enforcement                          │
│  ├── 3.5: "Resume Last Lesson" Engine & Completed Course Read-Only Access   │
│  └── 3.6: Centralized `CourseCompletionService` Architecture                │
├─────────────────────────────────────────────────────────────────────────────┤
│  Phase 4: Assessment & Practical Evaluation Engine                          │
│  ├── 4.1: Unified Assessment Core (Shared Schema for Quizzes & Exams)       │
│  ├── 4.2: Centralized Question Bank (Categories, Tags, Pools & Question Types)│
│  ├── 4.3: Quiz Engine (Timer, Randomization, Take/Submit, Auto-Grading)     │
│  ├── 4.4: Examination System (Scheduled Windows, Recovery & Timeout Submit) │
│  ├── 4.5: Assignment Management & Trainer Assignment Builder                │
│  ├── 4.6: Student Assignment Workspace, Submissions & Revisions             │
│  └── 4.7: Trainer Assignment Grading, Rubrics & Written Feedback            │
├─────────────────────────────────────────────────────────────────────────────┤
│  Phase 5: Academic Records, Gradebook & Certification                       │
│  ├── 5.1: Centralized Academy Gradebook (Weighted Components & Overrides)   │
│  ├── 5.2: Student Academic Transcripts & Performance Histories              │
│  ├── 5.3: Certificate Issuance Rules (Completion + Grade + Attendance)      │
│  ├── 5.4: Lightweight Certificate PDF Generation (Academy Branding)         │
│  └── 5.5: Public Zero-PII Certificate Verification Portal                   │
├─────────────────────────────────────────────────────────────────────────────┤
│  Phase 6: SEO Practical Learning Environment & Portfolios                   │
│  ├── 6.1: Technical SEO Checklists & Structured Audit Workflows             │
│  ├── 6.2: Keyword Research & Spreadsheet Submission Workspaces              │
│  ├── 6.3: SEO Campaign Milestones (GA4/GSC Proofs & Competitor Reports)     │
│  └── 6.4: Student Portfolios (Private by Default, Opt-In & Trainer Approved)│
├─────────────────────────────────────────────────────────────────────────────┤
│  Phase 7: Live Classes, Attendance & Course Community                       │
│  ├── 7.1: Live Class Scheduling (Self-Hosted Jitsi Meet & BigBlueButton)    │
│  ├── 7.2: Trainer Attendance Recording & Student Attendance Histories       │
│  └── 7.3: Course & Lesson Discussions (Threading, Comments & Moderation)    │
├─────────────────────────────────────────────────────────────────────────────┤
│  Phase 8: Commercial Learning Analytics & Optional AI Assistance            │
│  ├── 8.1: Executive & Trainer Learning Analytics (Drop-Offs & CSV Exports)  │
│  └── 8.2: Optional BYO-Key AI Assignment Feedback & SEO Audit Suggestions   │
└─────────────────────────────────────────────────────────────────────────────┘
```

