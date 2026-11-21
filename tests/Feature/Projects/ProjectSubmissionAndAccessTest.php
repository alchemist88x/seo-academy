<?php

namespace Tests\Feature\Projects;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Notifications\Models\AcademyNotification;
use App\Domain\Projects\Models\PracticalProject;
use App\Domain\Projects\Models\ProjectEvaluation;
use App\Domain\Projects\Models\ProjectSubmission;
use App\Domain\Projects\Models\ProjectSubmissionAttachment;
use App\Domain\Projects\Services\PracticalProjectService;
use App\Domain\Projects\Services\ProjectEvaluationService;
use App\Domain\Projects\Services\ProjectSubmissionService;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class ProjectSubmissionAndAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Academy $academy;

    protected User $adminUser;

    protected User $trainerUser;

    protected User $studentA;

    protected StudentProfile $profileA;

    protected User $studentB;

    protected StudentProfile $profileB;

    protected Course $course;

    protected PracticalProject $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('private');

        $this->academy = Academy::factory()->create([
            'name' => 'SEO Submissions Academy',
            'subdomain' => 'seo-submissions',
            'is_active' => true,
        ]);

        app(TenantContext::class)->setAcademy($this->academy);

        // Admin
        $this->adminUser = User::factory()->create(['is_active' => true]);
        $this->adminUser->assignRole('admin');
        $this->adminUser->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'admin',
            'status' => 'active',
            'created_at' => now(),
        ]);

        // Trainer
        $this->trainerUser = User::factory()->create(['is_active' => true]);
        $this->trainerUser->assignRole('trainer');
        $this->trainerUser->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'trainer',
            'status' => 'active',
            'created_at' => now(),
        ]);

        // Course
        $this->course = Course::factory()->create([
            'academy_id' => $this->academy->id,
            'title' => 'Technical SEO Mastery',
            'status' => 'published',
        ]);
        $this->course->trainers()->attach($this->trainerUser->id, [
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
        ]);

        // Student A (Enrolled)
        $this->studentA = User::factory()->create(['is_active' => true]);
        $this->studentA->assignRole('student');
        $this->studentA->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);
        $this->profileA = StudentProfile::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->studentA->id,
            'academy_id' => $this->academy->id,
            'student_code' => 'STU-A',
            'active' => true,
        ]);
        Enrollment::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'course_id' => $this->course->id,
            'student_profile_id' => $this->profileA->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        // Student B (Not enrolled)
        $this->studentB = User::factory()->create(['is_active' => true]);
        $this->studentB->assignRole('student');
        $this->studentB->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);
        $this->profileB = StudentProfile::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->studentB->id,
            'academy_id' => $this->academy->id,
            'student_code' => 'STU-B',
            'active' => true,
        ]);

        // Published Project
        $this->project = PracticalProject::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'course_id' => $this->course->id,
            'title' => 'E-Commerce Crawl Audit & Information Architecture',
            'slug' => 'ecommerce-crawl-audit',
            'category' => PracticalProject::CATEGORY_TECHNICAL_SEO,
            'difficulty' => PracticalProject::DIFFICULTY_EXPERT,
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'max_revisions' => 3,
            'status' => PracticalProject::STATUS_PUBLISHED,
        ]);
    }

    public function test_student_project_access_isolation(): void
    {
        $projectService = app(PracticalProjectService::class);

        // Student A is enrolled in course -> has access
        $projectsForA = $projectService->getAvailableProjectsForStudent($this->profileA)->pluck('id')->all();
        $this->assertContains($this->project->id, $projectsForA);

        // Student B is NOT enrolled in course -> no access
        $projectsForB = $projectService->getAvailableProjectsForStudent($this->profileB)->pluck('id')->all();
        $this->assertNotContains($this->project->id, $projectsForB);

        // Draft project is hidden from both
        $draftProject = PracticalProject::create([
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'course_id' => $this->course->id,
            'title' => 'Unpublished Draft Project',
            'category' => PracticalProject::CATEGORY_ON_PAGE_SEO,
            'status' => PracticalProject::STATUS_DRAFT,
        ]);

        $this->assertNotContains($draftProject->id, $projectService->getAvailableProjectsForStudent($this->profileA)->pluck('id')->all());
    }

    public function test_submission_creation_with_valid_deliverables(): void
    {
        $submissionService = app(ProjectSubmissionService::class);

        $file = UploadedFile::fake()->create('technical-audit.pdf', 1024, 'application/pdf');

        $submission = $submissionService->submit($this->project, $this->studentA, [
            'website_url' => 'https://audit-client-store.com',
            'content' => 'Comprehensive technical crawl identifying 142 broken links and 38 missing canonical tags.',
            'student_notes' => 'Tested on staging environment.',
            'attachments' => [
                ['file' => $file, 'type' => ProjectSubmissionAttachment::TYPE_SEO_AUDIT_REPORT],
            ],
        ]);

        $this->assertDatabaseHas('project_submissions', [
            'id' => $submission->id,
            'project_id' => $this->project->id,
            'user_id' => $this->studentA->id,
            'version_number' => 1,
            'website_url' => 'https://audit-client-store.com',
            'status' => ProjectSubmission::STATUS_SUBMITTED,
        ]);

        $this->assertCount(1, $submission->attachments);
        $this->assertEquals('technical-audit.pdf', $submission->attachments->first()->file_name);
        Storage::disk('private')->assertExists($submission->attachments->first()->storage_path);
    }

    public function test_submission_version_history_preserved_upon_revision(): void
    {
        $submissionService = app(ProjectSubmissionService::class);
        $evaluationService = app(ProjectEvaluationService::class);

        // 1. Initial submission v1
        $sub1 = $submissionService->submit($this->project, $this->studentA, [
            'content' => 'Initial crawl results version 1.',
        ]);
        $this->assertEquals(1, $sub1->version_number);

        // 2. Trainer requests revision
        $evaluationService->evaluate($sub1, $this->trainerUser, [
            'decision' => ProjectEvaluation::STATUS_REVISION_REQUESTED,
            'revision_reason' => 'Please provide the raw Screaming Frog CSV export with status codes.',
        ]);
        $sub1->refresh();
        $this->assertEquals(ProjectSubmission::STATUS_REVISION_REQUESTED, $sub1->status);

        // 3. Student submits revision -> creates v2
        $sub2 = $submissionService->submit($this->project, $this->studentA, [
            'content' => 'Revised submission version 2 with CSV attachment included.',
        ]);

        $this->assertEquals(2, $sub2->version_number);
        $this->assertEquals($sub1->id, $sub2->previous_submission_id);
        $this->assertEquals(ProjectSubmission::STATUS_RESUBMITTED, $sub2->status);

        // Verify v1 record is NOT overwritten
        $this->assertDatabaseHas('project_submissions', [
            'id' => $sub1->id,
            'version_number' => 1,
            'status' => ProjectSubmission::STATUS_REVISION_REQUESTED,
        ]);
    }

    public function test_file_upload_security_disallows_dangerous_extensions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $submissionService = app(ProjectSubmissionService::class);

        $dangerousFile = UploadedFile::fake()->create('malicious.php', 500, 'application/x-php');

        $submissionService->submit($this->project, $this->studentA, [
            'content' => 'Attempting to upload executable script.',
            'attachments' => [
                ['file' => $dangerousFile, 'type' => ProjectSubmissionAttachment::TYPE_SUPPORTING_ATTACHMENT],
            ],
        ]);
    }

    public function test_attachment_download_authorization_and_idor_protection(): void
    {
        $submissionService = app(ProjectSubmissionService::class);

        $file = UploadedFile::fake()->create('confidential-report.pdf', 500, 'application/pdf');
        $submission = $submissionService->submit($this->project, $this->studentA, [
            'content' => 'Private submission.',
            'attachments' => [
                ['file' => $file, 'type' => ProjectSubmissionAttachment::TYPE_PDF_DOCUMENT],
            ],
        ]);
        $attachment = $submission->attachments->first();

        // 1. Owner Student A can download
        $this->actingAs($this->studentA)
            ->get(route('projects.attachments.download', ['submission' => $submission->id, 'attachment' => $attachment->id]))
            ->assertOk();

        // 2. Assigned Trainer can download
        $this->actingAs($this->trainerUser)
            ->get(route('projects.attachments.download', ['submission' => $submission->id, 'attachment' => $attachment->id]))
            ->assertOk();

        // 3. Admin can download
        $this->actingAs($this->adminUser)
            ->get(route('projects.attachments.download', ['submission' => $submission->id, 'attachment' => $attachment->id]))
            ->assertOk();

        // 4. Other Student B CANNOT download (IDOR protection)
        $this->actingAs($this->studentB)
            ->get(route('projects.attachments.download', ['submission' => $submission->id, 'attachment' => $attachment->id]))
            ->assertForbidden();
    }

    public function test_notification_deduplication(): void
    {
        $submissionService = app(ProjectSubmissionService::class);

        $submission = $submissionService->submit($this->project, $this->studentA, [
            'content' => 'First submission test.',
        ]);

        $notificationCount = AcademyNotification::query()
            ->where('target', (string) $this->studentA->id)
            ->where('title', 'Project Submission Received')
            ->count();

        $this->assertEquals(1, $notificationCount);
    }
}
