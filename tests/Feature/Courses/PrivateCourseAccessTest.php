<?php

namespace Tests\Feature\Courses;

use App\Domain\Academies\Models\Academy;
use App\Domain\Academies\Models\AcademyUser;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateCourseAccessTest extends TestCase
{
    use RefreshDatabase;

    private Academy $academy;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->academy = Academy::create([
            'name' => 'SEO Academy',
            'slug' => 'seo-academy',
            'is_active' => true,
        ]);

        $this->course = Course::create([
            'academy_id' => $this->academy->getKey(),
            'title' => 'Technical SEO Mastery',
            'slug' => 'technical-seo-mastery',
            'description' => 'In-depth technical SEO curriculum.',
            'difficulty' => 'intermediate',
            'status' => 'Published',
            'visibility' => 'academy_only',
        ]);
    }

    public function test_guest_cannot_browse_course_catalogue(): void
    {
        $response = $this->get('/courses');

        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_course_details(): void
    {
        $response = $this->get('/courses/'.$this->course->slug);

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_student_enrolled_in_course_can_access_classroom(): void
    {
        $module = Module::create([
            'academy_id' => $this->academy->getKey(),
            'course_id' => $this->course->getKey(),
            'title' => 'Module 1: Crawling',
            'display_order' => 1,
            'status' => 'Active',
        ]);

        $lesson = Lesson::create([
            'academy_id' => $this->academy->getKey(),
            'course_id' => $this->course->getKey(),
            'module_id' => $module->getKey(),
            'title' => 'Lesson 1.1: Robots.txt',
            'type' => 'text',
            'status' => 'Active',
            'visibility' => 'published',
            'display_order' => 1,
        ]);

        $studentUser = User::factory()->create([
            'is_active' => true,
        ]);
        $studentUser->assignRole('student');
        AcademyUser::create([
            'academy_id' => $this->academy->getKey(),
            'user_id' => $studentUser->getKey(),
            'role' => 'student',
            'status' => 'active',
        ]);

        $profile = StudentProfile::create([
            'user_id' => $studentUser->getKey(),
            'academy_id' => $this->academy->getKey(),
            'student_code' => 'STU-001',
            'proficiency' => 'beginner',
            'active' => true,
        ]);

        Enrollment::create([
            'academy_id' => $this->academy->getKey(),
            'student_profile_id' => $profile->getKey(),
            'course_id' => $this->course->getKey(),
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $response = $this->actingAs($studentUser)->get(route('student.courses.learn', [
            'course' => $this->course->getKey(),
            'lesson' => $lesson->getKey(),
        ]));

        $response->assertStatus(200);
    }

    public function test_authenticated_student_not_enrolled_in_course_is_denied_classroom_access(): void
    {
        $studentUser = User::factory()->create([
            'is_active' => true,
        ]);
        $studentUser->assignRole('student');
        AcademyUser::create([
            'academy_id' => $this->academy->getKey(),
            'user_id' => $studentUser->getKey(),
            'role' => 'student',
            'status' => 'active',
        ]);

        StudentProfile::create([
            'user_id' => $studentUser->getKey(),
            'academy_id' => $this->academy->getKey(),
            'student_code' => 'STU-002',
            'proficiency' => 'beginner',
            'active' => true,
        ]);

        // Accessing classroom without enrollment must return 403 Forbidden
        $response = $this->actingAs($studentUser)->get(route('student.courses.learn', [
            'course' => $this->course->getKey(),
        ]));

        $response->assertStatus(403);
    }

    public function test_guest_cannot_stream_private_media(): void
    {
        $response = $this->get('/media/fake-uuid');

        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_access_course_administration(): void
    {
        $adminUser = User::factory()->create([
            'is_active' => true,
        ]);
        $adminUser->assignRole('admin');
        AcademyUser::create([
            'academy_id' => $this->academy->getKey(),
            'user_id' => $adminUser->getKey(),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($adminUser)->get(route('admin.courses.index'));

        $response->assertStatus(200);
    }
}
