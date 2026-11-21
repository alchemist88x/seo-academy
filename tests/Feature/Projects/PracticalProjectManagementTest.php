<?php

namespace Tests\Feature\Projects;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Projects\Models\PracticalProject;
use App\Domain\Projects\Models\PracticalProjectStudent;
use App\Domain\Projects\Services\PracticalProjectService;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PracticalProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Academy $academy;

    protected User $adminUser;

    protected User $trainerUser;

    protected User $supportUser;

    protected User $studentUser;

    protected StudentProfile $studentProfile;

    protected Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->academy = Academy::factory()->create([
            'name' => 'SEO Project Academy',
            'subdomain' => 'seo-projects',
            'is_active' => true,
        ]);

        app(TenantContext::class)->setAcademy($this->academy);

        // 1. Admin
        $this->adminUser = User::factory()->create(['is_active' => true]);
        $this->adminUser->assignRole('admin');
        $this->adminUser->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'admin',
            'status' => 'active',
            'created_at' => now(),
        ]);

        // 2. Trainer
        $this->trainerUser = User::factory()->create(['is_active' => true]);
        $this->trainerUser->assignRole('trainer');
        $this->trainerUser->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'trainer',
            'status' => 'active',
            'created_at' => now(),
        ]);

        // 3. Support Staff
        $this->supportUser = User::factory()->create(['is_active' => true]);
        $this->supportUser->assignRole('support_staff');
        $this->supportUser->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'support_staff',
            'status' => 'active',
            'created_at' => now(),
        ]);

        // 4. Student
        $this->studentUser = User::factory()->create(['is_active' => true]);
        $this->studentUser->assignRole('student');
        $this->studentUser->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $this->studentProfile = StudentProfile::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->studentUser->id,
            'academy_id' => $this->academy->id,
            'student_code' => 'STU-PROJ-001',
            'active' => true,
        ]);

        // Course
        $this->course = Course::factory()->create([
            'academy_id' => $this->academy->id,
            'title' => 'Advanced Technical SEO Mastery',
            'status' => 'published',
        ]);
    }

    public function test_admin_can_create_practical_seo_project_with_criteria(): void
    {
        $service = app(PracticalProjectService::class);

        $project = $service->createProject($this->academy, $this->adminUser, [
            'title' => 'Enterprise Site Crawl & Core Web Vitals Audit',
            'category' => PracticalProject::CATEGORY_TECHNICAL_SEO,
            'difficulty' => PracticalProject::DIFFICULTY_EXPERT,
            'course_id' => $this->course->id,
            'description' => 'Perform an in-depth technical crawl on an e-commerce domain.',
            'objectives' => ['Diagnose 4xx/5xx status codes', 'Audit INP and LCP metrics'],
            'expected_deliverables' => ['Audit PDF Report', 'Crawl Spreadsheet', 'Core Web Vitals Screenshots'],
            'instructions' => 'Follow screaming frog standard configurations.',
            'max_marks' => 100.00,
            'passing_marks' => 70.00,
            'max_revisions' => 3,
            'status' => PracticalProject::STATUS_DRAFT,
            'criteria' => [
                ['title' => 'Technical Crawl & Status Codes', 'max_marks' => 40.0, 'weight' => 40.0],
                ['title' => 'Core Web Vitals Diagnosis', 'max_marks' => 30.0, 'weight' => 30.0],
                ['title' => 'Executive Recommendations', 'max_marks' => 30.0, 'weight' => 30.0],
            ],
        ]);

        $this->assertDatabaseHas('practical_projects', [
            'id' => $project->id,
            'title' => 'Enterprise Site Crawl & Core Web Vitals Audit',
            'category' => 'technical_seo',
            'difficulty' => 'expert',
            'course_id' => $this->course->id,
            'status' => 'draft',
        ]);

        $this->assertCount(3, $project->criteria);
        $this->assertEquals(40.0, (float) $project->criteria->first()->max_marks);
    }

    public function test_admin_can_publish_and_archive_project(): void
    {
        $service = app(PracticalProjectService::class);

        $project = $service->createProject($this->academy, $this->adminUser, [
            'title' => 'Local SEO & Google Business Profile Optimization',
            'category' => PracticalProject::CATEGORY_LOCAL_SEO,
            'difficulty' => PracticalProject::DIFFICULTY_BEGINNER,
            'max_marks' => 100.0,
            'passing_marks' => 50.0,
            'status' => PracticalProject::STATUS_DRAFT,
        ]);

        $this->assertFalse($project->isPublished());

        $service->publishProject($project, $this->adminUser);
        $project->refresh();
        $this->assertTrue($project->isPublished());
        $this->assertEquals(PracticalProject::STATUS_PUBLISHED, $project->status);

        $service->archiveProject($project, $this->adminUser);
        $project->refresh();
        $this->assertTrue($project->isArchived());
        $this->assertEquals(PracticalProject::STATUS_ARCHIVED, $project->status);
    }

    public function test_project_can_be_assigned_to_course(): void
    {
        $service = app(PracticalProjectService::class);

        $project = $service->createProject($this->academy, $this->adminUser, [
            'title' => 'Keyword Research & Clustering Masterplan',
            'category' => PracticalProject::CATEGORY_KEYWORD_RESEARCH,
            'difficulty' => PracticalProject::DIFFICULTY_INTERMEDIATE,
            'course_id' => $this->course->id,
        ]);

        $this->assertEquals($this->course->id, $project->course_id);
        $this->assertEquals('Advanced Technical SEO Mastery', $project->course->title);
    }

    public function test_project_can_be_assigned_to_individual_students(): void
    {
        $service = app(PracticalProjectService::class);

        $project = $service->createProject($this->academy, $this->adminUser, [
            'title' => 'Link Profile Detox & Disavow Campaign',
            'category' => PracticalProject::CATEGORY_LINK_BUILDING,
            'difficulty' => PracticalProject::DIFFICULTY_EXPERT,
            'status' => PracticalProject::STATUS_PUBLISHED,
        ]);

        $assignment = $service->assignToStudent($project, $this->studentProfile, now()->addDays(7), $this->adminUser);

        $this->assertDatabaseHas('practical_project_students', [
            'project_id' => $project->id,
            'student_profile_id' => $this->studentProfile->id,
            'status' => PracticalProjectStudent::STATUS_ASSIGNED,
        ]);

        $this->assertTrue($project->assignedStudents()->where('student_profiles.id', $this->studentProfile->id)->exists());
    }

    public function test_admin_and_student_role_permissions_enforced(): void
    {
        // 1. Admin can access admin projects index
        $this->actingAs($this->adminUser)
            ->get(route('admin.projects.index'))
            ->assertOk();

        // 2. Student cannot access admin projects index
        $this->actingAs($this->studentUser)
            ->get(route('admin.projects.index'))
            ->assertForbidden();

        // 3. Trainer cannot access admin projects index
        $this->actingAs($this->trainerUser)
            ->get(route('admin.projects.index'))
            ->assertForbidden();

        // 4. Support staff cannot access admin projects index
        $this->actingAs($this->supportUser)
            ->get(route('admin.projects.index'))
            ->assertForbidden();
    }
}
