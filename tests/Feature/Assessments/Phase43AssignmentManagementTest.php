<?php

namespace Tests\Feature\Assessments;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Services\AssignmentManagementService;
use App\Domain\Courses\Models\Course;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class Phase43AssignmentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Academy $academy;

    protected Course $course;

    protected User $admin;

    protected User $trainer;

    protected User $otherTrainer;

    protected User $student;

    protected User $supportStaff;

    protected AssignmentManagementService $service;

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

        // Users
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

        $this->student = User::factory()->create(['is_active' => true]);
        $this->student->assignRole('student');
        $profile = StudentProfile::create([
            'academy_id' => $this->academy->id,
            'user_id' => $this->student->id,
            'student_code' => 'STU-P43-'.Str::upper(Str::random(6)),
        ]);
        Enrollment::create([
            'academy_id' => $this->academy->id,
            'course_id' => $this->course->id,
            'student_profile_id' => $profile->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);

        $this->supportStaff = User::factory()->create(['is_active' => true]);
        $this->supportStaff->assignRole('support_staff');

        $this->service = app(AssignmentManagementService::class);
    }

    public function test_admin_can_create_publish_and_archive_assignment(): void
    {
        $assignment = $this->service->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Technical Audit Practical',
            'description' => 'Complete a crawl audit of the demo site.',
            'max_marks' => 100.0,
            'passing_marks' => 70.0,
            'due_date' => now()->addDays(7)->toISOString(),
            'assignment_type' => Assignment::TYPE_FILE_AND_TEXT,
        ], $this->admin, $this->academy);

        $this->assertInstanceOf(Assignment::class, $assignment);
        $this->assertSame('Technical Audit Practical', $assignment->title);
        $this->assertSame(Assignment::STATUS_DRAFT, $assignment->status);
        $this->assertSame('100.00', (string) $assignment->max_marks);
        $this->assertSame('70.00', (string) $assignment->passing_marks);

        // Publish
        $this->service->publishAssignment($assignment, $this->admin);
        $this->assertTrue($assignment->fresh()->isPublished());

        // Archive
        $this->service->archiveAssignment($assignment, $this->admin);
        $this->assertTrue($assignment->fresh()->isArchived());
    }

    public function test_assigned_trainer_can_create_and_update_assignment(): void
    {
        $assignment = $this->service->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Keyword Clustering Exercise',
            'max_marks' => 50.0,
            'passing_marks' => 30.0,
        ], $this->trainer, $this->academy);

        $this->assertSame('Keyword Clustering Exercise', $assignment->title);

        $updated = $this->service->updateAssignment($assignment, [
            'title' => 'Advanced Keyword Clustering',
            'max_marks' => 60.0,
            'passing_marks' => 35.0,
        ], $this->trainer);

        $this->assertSame('Advanced Keyword Clustering', $updated->title);
        $this->assertSame('60.00', (string) $updated->max_marks);
    }

    public function test_unassigned_trainer_cannot_create_or_modify_assignment(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('You are not assigned to teach this course.');

        $this->service->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Unauthorized Assignment',
            'max_marks' => 50.0,
            'passing_marks' => 25.0,
        ], $this->otherTrainer, $this->academy);
    }

    public function test_rubric_criteria_creation_and_total_marks_validation(): void
    {
        // Total criteria = 60 + 40 = 100 == max_marks
        $assignment = $this->service->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Rubric Guided Assignment',
            'max_marks' => 100.0,
            'passing_marks' => 60.0,
            'rubric_criteria' => [
                ['title' => 'Crawl Accuracy', 'max_marks' => 60.0, 'description' => 'Verify 404s and redirects'],
                ['title' => 'Reporting Clarity', 'max_marks' => 40.0, 'description' => 'Client-ready format'],
            ],
        ], $this->admin, $this->academy);

        $this->assertCount(2, $assignment->rubricCriteria);
        $this->assertTrue($this->service->validateRubricMarks($assignment));

        // When criteria sum does not match max_marks, an exception must be thrown
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Total rubric criteria marks (80.00) must match assignment maximum marks (100.00).');

        $this->service->syncRubricCriteria($assignment, [
            ['title' => 'Criterion A', 'max_marks' => 50.0],
            ['title' => 'Criterion B', 'max_marks' => 30.0],
        ]);
    }

    public function test_passing_marks_cannot_exceed_maximum_marks(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Passing marks cannot exceed maximum marks.');

        $this->service->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Invalid Mark Range',
            'max_marks' => 50.0,
            'passing_marks' => 60.0,
        ], $this->admin, $this->academy);
    }

    public function test_policy_authorization_rules(): void
    {
        $assignment = $this->service->createAssignment([
            'course_id' => $this->course->id,
            'title' => 'Policy Check Assignment',
            'max_marks' => 100.0,
            'passing_marks' => 50.0,
            'status' => Assignment::STATUS_PUBLISHED,
        ], $this->admin, $this->academy);

        // Admin
        $this->assertTrue($this->admin->can('view', $assignment));
        $this->assertTrue($this->admin->can('update', $assignment));
        $this->assertTrue($this->admin->can('grade', $assignment));

        // Assigned Trainer
        $this->assertTrue($this->trainer->can('view', $assignment));
        $this->assertTrue($this->trainer->can('update', $assignment));
        $this->assertTrue($this->trainer->can('grade', $assignment));

        // Other Trainer
        $this->assertFalse($this->otherTrainer->can('view', $assignment));
        $this->assertFalse($this->otherTrainer->can('update', $assignment));
        $this->assertFalse($this->otherTrainer->can('grade', $assignment));

        // Student
        $this->assertTrue($this->student->can('view', $assignment));
        $this->assertTrue($this->student->can('submit', $assignment));
        $this->assertFalse($this->student->can('update', $assignment));
        $this->assertFalse($this->student->can('grade', $assignment));

        // Support Staff
        $this->assertFalse($this->supportStaff->can('view', $assignment));
        $this->assertFalse($this->supportStaff->can('update', $assignment));
        $this->assertFalse($this->supportStaff->can('grade', $assignment));
    }
}
