<?php

namespace Tests\Feature\Operations;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Livewire\Dashboard\TrainerDashboard;
use App\Livewire\Trainer\TrainerCoursesIndex;
use App\Livewire\Trainer\TrainerStudentsIndex;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TrainerOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function createAcademyMember(Academy $academy, string $role, array $userOverrides = []): User
    {
        $user = User::factory()->create(array_merge(['is_active' => true], $userOverrides));
        $user->assignRole($role);
        $user->academies()->attach($academy->getKey(), [
            'id' => (string) Str::uuid(),
            'role' => $role,
            'status' => 'active',
        ]);

        return $user;
    }

    private function createCourse(Academy $academy, array $attributes = []): Course
    {
        return Course::create(array_merge([
            'academy_id' => $academy->getKey(),
            'title' => 'Course '.Str::random(5),
            'slug' => 'course-'.Str::lower(Str::random(8)),
            'description' => 'Course description',
            'difficulty' => 'Beginner',
            'status' => 'Published',
            'visibility' => 'public',
            'duration_months' => 3,
        ], $attributes));
    }

    private function assignTrainerToCourse(Academy $academy, Course $course, User $trainer, bool $isPrimary = true): void
    {
        DB::table('course_trainers')->insert([
            'id' => (string) Str::uuid(),
            'academy_id' => $academy->getKey(),
            'course_id' => $course->getKey(),
            'user_id' => $trainer->getKey(),
            'is_primary' => $isPrimary,
            'created_at' => now(),
        ]);
    }

    private function enrollStudent(Academy $academy, User $studentUser, Course $course, string $status = 'active'): Enrollment
    {
        $studentProfile = StudentProfile::firstOrCreate(
            ['academy_id' => $academy->getKey(), 'user_id' => $studentUser->getKey()],
            [
                'student_code' => 'STU-'.strtoupper(Str::random(6)),
                'enrollment_status' => 'active',
                'batch' => 'Cohort-2026-A',
            ]
        );

        return Enrollment::create([
            'academy_id' => $academy->getKey(),
            'student_profile_id' => $studentProfile->getKey(),
            'course_id' => $course->getKey(),
            'status' => $status,
            'enrolled_at' => now(),
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonths(6),
        ]);
    }

    public function test_trainer_dashboard_displays_real_metrics(): void
    {
        $academy = Academy::factory()->create(['slug' => 'trainer-dash-acad']);
        $trainer = $this->createAcademyMember($academy, 'trainer');
        $otherTrainer = $this->createAcademyMember($academy, 'trainer');

        // Course 1: Assigned to trainer, published
        $course1 = $this->createCourse($academy, ['title' => 'Trainer Assigned Published', 'status' => 'Published']);
        $this->assignTrainerToCourse($academy, $course1, $trainer, true);

        // Course 2: Assigned to trainer, draft
        $course2 = $this->createCourse($academy, ['title' => 'Trainer Assigned Draft', 'status' => 'Draft']);
        $this->assignTrainerToCourse($academy, $course2, $trainer, false);

        // Course 3: Unassigned to this trainer (assigned to other trainer)
        $course3 = $this->createCourse($academy, ['title' => 'Other Trainer Course', 'status' => 'Published']);
        $this->assignTrainerToCourse($academy, $course3, $otherTrainer, true);

        // Students in trainer's courses
        $student1 = $this->createAcademyMember($academy, 'student');
        $student2 = $this->createAcademyMember($academy, 'student');
        $student3 = $this->createAcademyMember($academy, 'student');
        $this->enrollStudent($academy, $student1, $course1);
        $this->enrollStudent($academy, $student2, $course1);
        $this->enrollStudent($academy, $student3, $course2);

        // Student only in other trainer's course
        $student4 = $this->createAcademyMember($academy, 'student');
        $this->enrollStudent($academy, $student4, $course3);

        app(TenantContext::class)->setAcademy($academy);

        $response = $this->actingAs($trainer)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('trainer.dashboard'));

        $response->assertStatus(200);

        Livewire::actingAs($trainer)
            ->test(TrainerDashboard::class)
            ->assertSee('Total Assigned Courses')
            ->assertSee('2') // total assigned courses (course1, course2)
            ->assertSee('Active Enrolled Students')
            ->assertSee('3'); // student 1, 2, 3 (not student 4)
    }

    public function test_trainer_sees_only_assigned_courses_on_trainer_courses_index(): void
    {
        $academy = Academy::factory()->create(['slug' => 'trainer-courses-acad']);
        $trainer = $this->createAcademyMember($academy, 'trainer');
        $otherTrainer = $this->createAcademyMember($academy, 'trainer');

        $assignedCourse = $this->createCourse($academy, ['title' => 'SEO Fundamentals Assigned']);
        $this->assignTrainerToCourse($academy, $assignedCourse, $trainer);

        $unassignedCourse = $this->createCourse($academy, ['title' => 'PPC Mastery Unassigned']);
        $this->assignTrainerToCourse($academy, $unassignedCourse, $otherTrainer);

        app(TenantContext::class)->setAcademy($academy);

        $response = $this->actingAs($trainer)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('trainer.courses.index'));

        $response->assertStatus(200)
            ->assertSee('SEO Fundamentals Assigned')
            ->assertDontSee('PPC Mastery Unassigned');

        Livewire::actingAs($trainer)
            ->test(TrainerCoursesIndex::class)
            ->assertSee('SEO Fundamentals Assigned')
            ->assertDontSee('PPC Mastery Unassigned');
    }

    public function test_trainer_cannot_access_or_view_unassigned_courses(): void
    {
        $academy = Academy::factory()->create(['slug' => 'trainer-access-acad']);
        $trainer = $this->createAcademyMember($academy, 'trainer');
        $otherTrainer = $this->createAcademyMember($academy, 'trainer');

        $unassignedCourse = $this->createCourse($academy, ['title' => 'Forbidden Course', 'visibility' => 'private']);
        $this->assignTrainerToCourse($academy, $unassignedCourse, $otherTrainer);

        app(TenantContext::class)->setAcademy($academy);

        // Accessing structure of unassigned course as trainer returns 403
        $this->actingAs($trainer)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('trainer.courses.structure', $unassignedCourse))
            ->assertStatus(403);
    }

    public function test_trainer_can_view_trainer_students_and_sees_only_enrolled_in_assigned_courses(): void
    {
        $academy = Academy::factory()->create(['slug' => 'trainer-stu-acad']);
        $trainer = $this->createAcademyMember($academy, 'trainer');
        $otherTrainer = $this->createAcademyMember($academy, 'trainer');

        $courseTrainer = $this->createCourse($academy, ['title' => 'Technical SEO Cohort']);
        $this->assignTrainerToCourse($academy, $courseTrainer, $trainer);

        $courseOther = $this->createCourse($academy, ['title' => 'Social Media Cohort']);
        $this->assignTrainerToCourse($academy, $courseOther, $otherTrainer);

        $myStudent = $this->createAcademyMember($academy, 'student', ['name' => 'Alice Assigned', 'email' => 'alice@example.com']);
        $this->enrollStudent($academy, $myStudent, $courseTrainer);

        $otherStudent = $this->createAcademyMember($academy, 'student', ['name' => 'Bob Other', 'email' => 'bob@example.com']);
        $this->enrollStudent($academy, $otherStudent, $courseOther);

        app(TenantContext::class)->setAcademy($academy);

        $response = $this->actingAs($trainer)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('trainer.students.index'));

        $response->assertStatus(200)
            ->assertSee('Alice Assigned')
            ->assertDontSee('Bob Other');

        Livewire::actingAs($trainer)
            ->test(TrainerStudentsIndex::class)
            ->assertSee('Alice Assigned')
            ->assertDontSee('Bob Other');
    }

    public function test_trainer_cannot_see_students_enrolled_in_courses_taught_by_other_trainers(): void
    {
        $academy = Academy::factory()->create(['slug' => 'trainer-stu-iso-acad']);
        $trainer = $this->createAcademyMember($academy, 'trainer');
        $otherTrainer = $this->createAcademyMember($academy, 'trainer');

        $courseOther = $this->createCourse($academy, ['title' => 'Other Trainer Course']);
        $this->assignTrainerToCourse($academy, $courseOther, $otherTrainer);

        $otherStudent = $this->createAcademyMember($academy, 'student', ['name' => 'Secret Student', 'email' => 'secret@example.com']);
        $this->enrollStudent($academy, $otherStudent, $courseOther);

        app(TenantContext::class)->setAcademy($academy);

        // Searching for the other student returns no results
        Livewire::actingAs($trainer)
            ->test(TrainerStudentsIndex::class)
            ->set('search', 'Secret Student')
            ->assertDontSee('Secret Student')
            ->assertSee('No students found');
    }

    public function test_trainer_cannot_create_student_accounts_modify_enrollments_or_alter_course_structure_unless_authorized(): void
    {
        $academy = Academy::factory()->create(['slug' => 'trainer-guards-acad']);
        $trainer = $this->createAcademyMember($academy, 'trainer');

        app(TenantContext::class)->setAcademy($academy);

        // Blocked from admin student management
        $this->actingAs($trainer)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('admin.students.index'))
            ->assertStatus(403);

        // Blocked from admin enrollments
        $this->actingAs($trainer)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('admin.enrollments.index'))
            ->assertStatus(403);

        // Blocked from admin courses
        $this->actingAs($trainer)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('admin.courses.index'))
            ->assertStatus(403);

        // Blocked from admin academy settings
        $this->actingAs($trainer)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('admin.academy.settings'))
            ->assertStatus(403);
    }

    public function test_unauthorized_users_blocked_from_trainer_routes(): void
    {
        $academy = Academy::factory()->create(['slug' => 'trainer-block-acad']);
        $student = $this->createAcademyMember($academy, 'student');
        $supportStaff = $this->createAcademyMember($academy, 'support_staff');

        app(TenantContext::class)->setAcademy($academy);

        // Student blocked from trainer dashboard, courses, students
        $this->actingAs($student)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('trainer.dashboard'))
            ->assertStatus(403);

        $this->actingAs($student)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('trainer.courses.index'))
            ->assertStatus(403);

        $this->actingAs($student)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('trainer.students.index'))
            ->assertStatus(403);

        // Support staff blocked from trainer dashboard, courses, students
        $this->actingAs($supportStaff)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('trainer.dashboard'))
            ->assertStatus(403);

        $this->actingAs($supportStaff)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('trainer.courses.index'))
            ->assertStatus(403);

        $this->actingAs($supportStaff)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('trainer.students.index'))
            ->assertStatus(403);
    }
}
