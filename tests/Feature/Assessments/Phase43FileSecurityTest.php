<?php

namespace Tests\Feature\Assessments;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Services\AssignmentManagementService;
use App\Domain\Assignments\Services\AssignmentSubmissionService;
use App\Domain\Courses\Models\Course;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase43FileSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Academy $academy;

    protected Course $course;

    protected User $admin;

    protected User $trainer;

    protected User $otherTrainer;

    protected User $studentA;

    protected User $studentB;

    protected User $supportStaff;

    protected Assignment $assignment;

    protected AssignmentSubmissionService $submissionService;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'permission.cache.store' => 'array',
            'session.driver' => 'array',
        ]);

        $this->seed(RoleSeeder::class);

        $this->academy = Academy::create([
            'id' => (string) Str::uuid(),
            'name' => 'SEO Test Academy',
            'slug' => 'seo-test-academy-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        app(TenantContext::class)->setAcademy($this->academy);

        $this->course = Course::factory()->create([
            'academy_id' => $this->academy->id,
            'status' => 'Published',
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');

        $this->trainer = User::factory()->create(['is_active' => true]);
        $this->trainer->assignRole('trainer');
        $this->course->trainers()->attach($this->trainer->id, [
            'id' => (string) Str::uuid(),
            'academy_id' => $this->academy->id,
            'is_primary' => true,
            'created_at' => now(),
        ]);

        $this->otherTrainer = User::factory()->create(['is_active' => true]);
        $this->otherTrainer->assignRole('trainer');

        // Student A (Enrolled)
        $this->studentA = User::factory()->create(['is_active' => true]);
        $this->studentA->assignRole('student');
        $profileA = StudentProfile::create([
            'academy_id' => $this->academy->id,
            'user_id' => $this->studentA->id,
            'student_code' => 'STU-P43-A',
        ]);
        Enrollment::create([
            'academy_id' => $this->academy->id,
            'course_id' => $this->course->id,
            'student_profile_id' => $profileA->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);

        // Student B (Enrolled)
        $this->studentB = User::factory()->create(['is_active' => true]);
        $this->studentB->assignRole('student');
        $profileB = StudentProfile::create([
            'academy_id' => $this->academy->id,
            'user_id' => $this->studentB->id,
            'student_code' => 'STU-P43-B',
        ]);
        Enrollment::create([
            'academy_id' => $this->academy->id,
            'course_id' => $this->course->id,
            'student_profile_id' => $profileB->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);

        $this->supportStaff = User::factory()->create(['is_active' => true]);
        $this->supportStaff->assignRole('support_staff');

        $managementService = app(AssignmentManagementService::class);
        $this->assignment = $managementService->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Security Test Assignment',
            'max_marks' => 100.0,
            'passing_marks' => 50.0,
            'status' => Assignment::STATUS_PUBLISHED,
        ], $this->admin, $this->academy);

        $this->submissionService = app(AssignmentSubmissionService::class);
    }

    public function test_file_is_stored_in_private_vault_outside_webroot(): void
    {
        $submission = $this->submissionService->submit($this->assignment, $this->studentA, [
            'content' => 'Student A secret deliverable.',
            'files' => [
                ['name' => 'confidential_audit.pdf', 'content' => '%PDF-1.4 secret client report', 'mime_type' => 'application/pdf'],
            ],
        ]);

        $attachment = $submission->attachments->first();
        $this->assertNotNull($attachment);

        // Verify storage is on private disk
        $this->assertSame('private', $attachment->storage_disk);
        $this->assertTrue(Storage::disk('private')->exists($attachment->storage_path));

        // Verify file path is within private directory structure
        $this->assertStringStartsWith('assignments/submissions/', $attachment->storage_path);
    }

    public function test_student_can_download_their_own_attachment(): void
    {
        $submission = $this->submissionService->submit($this->assignment, $this->studentA, [
            'content' => 'Student A deliverable.',
            'files' => [
                ['name' => 'my_work.pdf', 'content' => '%PDF-1.4 student A personal work', 'mime_type' => 'application/pdf'],
            ],
        ]);

        $attachment = $submission->attachments->first();

        $response = $this->actingAs($this->studentA)
            ->get(route('assignments.attachments.download', [
                'submission' => $submission->id,
                'attachment' => $attachment->id,
            ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename="my_work.pdf"');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_other_student_cannot_download_attachment(): void
    {
        $submission = $this->submissionService->submit($this->assignment, $this->studentA, [
            'content' => 'Student A deliverable.',
            'files' => [
                ['name' => 'student_a_secret.pdf', 'content' => '%PDF-1.4 private A', 'mime_type' => 'application/pdf'],
            ],
        ]);

        $attachment = $submission->attachments->first();

        // Student B attempts to download Student A's attachment
        $response = $this->actingAs($this->studentB)
            ->get(route('assignments.attachments.download', [
                'submission' => $submission->id,
                'attachment' => $attachment->id,
            ]));

        $response->assertStatus(403);
    }

    public function test_support_staff_cannot_download_attachment(): void
    {
        $submission = $this->submissionService->submit($this->assignment, $this->studentA, [
            'content' => 'Student A deliverable.',
            'files' => [
                ['name' => 'student_a_secret.pdf', 'content' => '%PDF-1.4 private A', 'mime_type' => 'application/pdf'],
            ],
        ]);

        $attachment = $submission->attachments->first();

        $response = $this->actingAs($this->supportStaff)
            ->get(route('assignments.attachments.download', [
                'submission' => $submission->id,
                'attachment' => $attachment->id,
            ]));

        $response->assertStatus(403);
    }

    public function test_assigned_trainer_and_admin_can_download_attachment(): void
    {
        $submission = $this->submissionService->submit($this->assignment, $this->studentA, [
            'content' => 'Student A deliverable.',
            'files' => [
                ['name' => 'student_a_work.pdf', 'content' => '%PDF-1.4 report content', 'mime_type' => 'application/pdf'],
            ],
        ]);

        $attachment = $submission->attachments->first();

        // Assigned Trainer
        $trainerResponse = $this->actingAs($this->trainer)
            ->get(route('assignments.attachments.download', [
                'submission' => $submission->id,
                'attachment' => $attachment->id,
            ]));
        $trainerResponse->assertStatus(200);

        // Admin
        $adminResponse = $this->actingAs($this->admin)
            ->get(route('assignments.attachments.download', [
                'submission' => $submission->id,
                'attachment' => $attachment->id,
            ]));
        $adminResponse->assertStatus(200);

        // Unassigned Trainer
        $otherResponse = $this->actingAs($this->otherTrainer)
            ->get(route('assignments.attachments.download', [
                'submission' => $submission->id,
                'attachment' => $attachment->id,
            ]));
        $otherResponse->assertStatus(403);
    }
}
