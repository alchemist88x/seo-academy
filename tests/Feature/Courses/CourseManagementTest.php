<?php

namespace Tests\Feature\Courses;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\CourseCategory;
use App\Domain\Courses\Services\CourseCategoryService;
use App\Domain\Courses\Services\CourseManagementService;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Tenants\Services\TenantContext;
use App\Livewire\Admin\CoursesIndex;
use App\Livewire\Trainer\TrainerCoursesIndex;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CourseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    protected function createAcademyUser(Academy $academy, string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        $user->academies()->attach($academy->getKey(), [
            'id' => (string) Str::uuid(),
            'role' => $role,
            'status' => 'active',
            'created_at' => now(),
        ]);

        return $user;
    }

    public function test_academy_admin_can_create_course_with_thumbnail_category_trainers_and_prerequisites(): void
    {
        Storage::fake('public');

        $academy = Academy::factory()->create();
        $admin = $this->createAcademyUser($academy, 'admin');
        $trainer = $this->createAcademyUser($academy, 'trainer');

        $category = app(CourseCategoryService::class)->create($academy, [
            'name' => 'Technical SEO',
            'slug' => 'technical-seo',
            'is_active' => true,
        ], $admin);

        $prereqCourse = Course::factory()->create([
            'academy_id' => $academy->id,
            'title' => 'SEO Basics',
            'status' => 'Published',
        ]);

        $this->actingAs($admin);
        app(TenantContext::class)->setAcademy($academy);

        $thumbnail = UploadedFile::fake()->image('course-thumb.png', 640, 360);

        Livewire::test(CoursesIndex::class)
            ->set('title', 'Enterprise Crawl Optimization')
            ->set('slug', 'enterprise-crawl-optimization')
            ->set('category_id', (string) $category->id)
            ->set('courseDifficulty', 'Advanced')
            ->set('is_free', false)
            ->set('price', '249.00')
            ->set('currency', 'USD')
            ->set('courseVisibility', 'public')
            ->set('courseStatus', 'Draft')
            ->set('description', 'Deep dive into crawl budget and log file analysis.')
            ->set('trainer_ids', [(string) $trainer->id])
            ->set('prerequisite_ids', [(string) $prereqCourse->id])
            ->set('thumbnail', $thumbnail)
            ->call('saveCourse')
            ->assertHasNoErrors();

        $course = Course::query()
            ->where('academy_id', $academy->id)
            ->where('slug', 'enterprise-crawl-optimization')
            ->firstOrFail();

        $this->assertSame((string) $category->id, (string) $course->category_id);
        $this->assertSame('Advanced', $course->difficulty);
        $this->assertFalse($course->is_free);
        $this->assertSame('249.00', number_format((float) $course->price, 2, '.', ''));
        $this->assertNotNull($course->thumbnail_path);
        Storage::disk('public')->assertExists($course->thumbnail_path);

        $this->assertTrue($course->trainers()->where('users.id', $trainer->id)->exists());
        $this->assertTrue($course->prerequisites()->where('prerequisite_course_id', $prereqCourse->id)->exists());

        $this->assertDatabaseHas('audit_log', [
            'user_id' => $admin->id,
            'action' => 'course.created',
            'subject_id' => $course->id,
        ]);
        $this->assertDatabaseHas('audit_log', [
            'action' => 'course.trainers_synced',
            'subject_id' => $course->id,
        ]);
        $this->assertDatabaseHas('audit_log', [
            'action' => 'course.prerequisites_synced',
            'subject_id' => $course->id,
        ]);
    }

    public function test_course_search_filters_sorting_and_pagination(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyUser($academy, 'admin');

        $catTech = CourseCategory::create([
            'academy_id' => $academy->id,
            'name' => 'Technical',
            'slug' => 'technical',
            'is_active' => true,
        ]);
        $catContent = CourseCategory::create([
            'academy_id' => $academy->id,
            'name' => 'Content',
            'slug' => 'content',
            'is_active' => true,
        ]);

        Course::factory()->create([
            'academy_id' => $academy->id,
            'category_id' => $catTech->id,
            'title' => 'Alpha Technical Audit',
            'difficulty' => 'Advanced',
            'status' => 'Published',
            'visibility' => 'public',
            'is_free' => false,
            'price' => 199.00,
        ]);

        Course::factory()->create([
            'academy_id' => $academy->id,
            'category_id' => $catContent->id,
            'title' => 'Beta Editorial SEO',
            'difficulty' => 'Beginner',
            'status' => 'Draft',
            'visibility' => 'private',
            'is_free' => false,
            'price' => 49.00,
        ]);

        for ($i = 1; $i <= 12; $i++) {
            Course::factory()->create([
                'academy_id' => $academy->id,
                'category_id' => $catContent->id,
                'title' => sprintf('Zeta Filler Course %02d', $i),
                'difficulty' => 'Beginner',
                'status' => 'Published',
                'visibility' => 'public',
                'is_free' => true,
                'price' => 0.00,
            ]);
        }

        $this->actingAs($admin);
        app(TenantContext::class)->setAcademy($academy);

        // Search + category + difficulty + status + visibility filter
        Livewire::test(CoursesIndex::class)
            ->set('search', 'Alpha')
            ->set('categoryId', (string) $catTech->id)
            ->set('difficulty', 'Advanced')
            ->set('status', 'Published')
            ->set('visibility', 'public')
            ->assertViewHas('courses', function ($courses): bool {
                $titles = $courses->pluck('title')->all();

                return $titles === ['Alpha Technical Audit'];
            });

        // Sorting by price_desc puts 199.00 first, then 49.00
        Livewire::test(CoursesIndex::class)
            ->set('sort', 'price_desc')
            ->assertViewHas('courses', function ($courses): bool {
                $titles = $courses->pluck('title')->all();

                return ($titles[0] ?? null) === 'Alpha Technical Audit'
                    && ($titles[1] ?? null) === 'Beta Editorial SEO'
                    && $courses->total() === 14
                    && $courses->count() === 12;
            });

        // Pagination: 14 courses total, 12 per page -> page 2 has remaining 2
        $service = app(CourseManagementService::class);
        $page1 = $service->listForAcademy($academy, ['sort' => 'title'], perPage: 12);
        $this->assertSame(14, $page1->total());
        $this->assertCount(12, $page1->items());
    }

    public function test_course_status_lifecycle_transitions_and_sets_published_at(): void
    {
        $academy = Academy::factory()->create();
        $admin = $this->createAcademyUser($academy, 'admin');

        $course = Course::factory()->create([
            'academy_id' => $academy->id,
            'status' => 'Draft',
            'published_at' => null,
        ]);

        $this->actingAs($admin);
        app(TenantContext::class)->setAcademy($academy);

        Livewire::test(CoursesIndex::class)
            ->call('transitionStatus', (string) $course->id, 'Published');

        $course->refresh();
        $this->assertSame('Published', $course->status);
        $this->assertNotNull($course->published_at);

        Livewire::test(CoursesIndex::class)
            ->call('transitionStatus', (string) $course->id, 'Unpublished');

        $course->refresh();
        $this->assertSame('Unpublished', $course->status);

        Livewire::test(CoursesIndex::class)
            ->call('transitionStatus', (string) $course->id, 'Archived');

        $course->refresh();
        $this->assertSame('Archived', $course->status);

        $this->assertSame(3, AuditLog::query()
            ->where('subject_id', $course->id)
            ->where('action', 'course.status_changed')
            ->count());
    }

    public function test_trainer_assignment_and_trainer_course_permissions(): void
    {
        $academyA = Academy::factory()->create();

        $adminA = $this->createAcademyUser($academyA, 'admin');
        $trainerA = $this->createAcademyUser($academyA, 'trainer');
        $studentA = $this->createAcademyUser($academyA, 'student');
        $suspendedTrainer = $this->createAcademyUser($academyA, 'trainer');
        $suspendedTrainer->update(['is_active' => false]);

        $assignedCourse = Course::factory()->create([
            'academy_id' => $academyA->id,
            'title' => 'Assigned Trainer Course',
            'status' => 'Draft',
        ]);
        $unassignedCourse = Course::factory()->create([
            'academy_id' => $academyA->id,
            'title' => 'Unassigned Trainer Course',
            'status' => 'Draft',
        ]);

        $service = app(CourseManagementService::class);

        // Rejects inactive/suspended trainer
        try {
            $service->syncTrainers($assignedCourse, [$suspendedTrainer->id], $adminA);
            $this->fail('Expected ValidationException for suspended trainer.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('trainer_ids', $e->errors());
        }

        // Rejects non-trainer user
        try {
            $service->syncTrainers($assignedCourse, [$studentA->id], $adminA);
            $this->fail('Expected ValidationException for non-trainer user.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('trainer_ids', $e->errors());
        }

        // Valid trainer assignment
        $service->syncTrainers($assignedCourse, [$trainerA->id], $adminA);

        $this->actingAs($trainerA);
        app(TenantContext::class)->setAcademy($academyA);

        // Trainer sees only assigned course on /trainer/courses and can edit it
        $this->withSession([TenantContext::SESSION_KEY => $academyA->id])
            ->get(route('trainer.courses.index'))
            ->assertOk()
            ->assertSee('Assigned Trainer Course')
            ->assertDontSee('Unassigned Trainer Course');

        Livewire::test(TrainerCoursesIndex::class)
            ->call('openEditModal', (string) $assignedCourse->id)
            ->set('title', 'Assigned Course Updated')
            ->call('saveCourse')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('courses', [
            'id' => $assignedCourse->id,
            'title' => 'Assigned Course Updated',
        ]);

        // Assigned trainer can preview assigned course, gets 403 on unassigned course
        $this->withSession([TenantContext::SESSION_KEY => $academyA->id])
            ->get(route('admin.courses.preview', $assignedCourse))
            ->assertOk();

        $this->withSession([TenantContext::SESSION_KEY => $academyA->id])
            ->get(route('admin.courses.preview', $unassignedCourse))
            ->assertForbidden();

        // Trainer cannot edit unassigned course in TrainerCoursesIndex
        Livewire::test(TrainerCoursesIndex::class)
            ->call('openEditModal', (string) $unassignedCourse->id)
            ->assertForbidden();

        // Trainer cannot publish or delete even an assigned course
        $this->assertTrue(Gate::forUser($trainerA)->denies('publish', $assignedCourse));
        $this->assertTrue(Gate::forUser($trainerA)->denies('delete', $assignedCourse));
    }

    public function test_prerequisite_validation_rejects_self_and_circular_prerequisites(): void
    {
        $academyA = Academy::factory()->create();
        $adminA = $this->createAcademyUser($academyA, 'admin');

        $courseA = Course::factory()->create(['academy_id' => $academyA->id]);
        $courseB = Course::factory()->create(['academy_id' => $academyA->id]);

        $service = app(CourseManagementService::class);

        // Self-prerequisite
        try {
            $service->syncPrerequisites($courseA, [$courseA->id], $adminA);
            $this->fail('Expected ValidationException for self-prerequisite.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('prerequisite_ids', $e->errors());
        }

        // Circular prerequisite: A requires B, then B cannot require A
        $service->syncPrerequisites($courseA, [$courseB->id], $adminA);

        try {
            $service->syncPrerequisites($courseB, [$courseA->id], $adminA);
            $this->fail('Expected ValidationException for circular prerequisite.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('prerequisite_ids', $e->errors());
        }
    }

    public function test_non_admin_and_suspended_users_cannot_access_course_management_or_categories(): void
    {
        $academy = Academy::factory()->create();

        $suspendedAdmin = $this->createAcademyUser($academy, 'admin');
        $suspendedAdmin->update(['is_active' => false]);
        $student = $this->createAcademyUser($academy, 'student');

        $course = Course::factory()->create([
            'academy_id' => $academy->id,
            'status' => 'Draft',
        ]);
        $category = CourseCategory::create([
            'academy_id' => $academy->id,
            'name' => 'Academy Category',
            'slug' => 'academy-category',
            'is_active' => true,
        ]);

        // Policy-level 403 for student on course & category
        $this->assertTrue(Gate::forUser($student)->denies('update', $course));
        $this->assertTrue(Gate::forUser($student)->denies('preview', $course));
        $this->assertTrue(Gate::forUser($student)->denies('update', $category));

        // Route-level 403 when suspended admin or student hits course preview
        $this->actingAs($suspendedAdmin)
            ->get(route('admin.courses.preview', $course))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('admin.courses.preview', $course))
            ->assertForbidden();
    }
}
