<?php

namespace Tests\Feature\Courses;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CourseCatalogueTest extends TestCase
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

    public function test_public_catalogue_shows_only_published_public_courses_for_resolved_academy(): void
    {
        $academyA = Academy::factory()->create(['subdomain' => 'alpha']);

        $publicPublished = Course::factory()->create([
            'academy_id' => $academyA->id,
            'title' => 'Public Published SEO',
            'slug' => 'public-published-seo',
            'status' => 'Published',
            'visibility' => 'public',
        ]);

        $draftCourse = Course::factory()->create([
            'academy_id' => $academyA->id,
            'title' => 'Hidden Draft SEO',
            'slug' => 'hidden-draft-seo',
            'status' => 'Draft',
            'visibility' => 'public',
        ]);

        $unpublishedCourse = Course::factory()->create([
            'academy_id' => $academyA->id,
            'title' => 'Hidden Unpublished SEO',
            'slug' => 'hidden-unpublished-seo',
            'status' => 'Unpublished',
            'visibility' => 'public',
        ]);

        $archivedCourse = Course::factory()->create([
            'academy_id' => $academyA->id,
            'title' => 'Hidden Archived SEO',
            'slug' => 'hidden-archived-seo',
            'status' => 'Archived',
            'visibility' => 'public',
        ]);

        $privateCourse = Course::factory()->create([
            'academy_id' => $academyA->id,
            'title' => 'Hidden Private SEO',
            'slug' => 'hidden-private-seo',
            'status' => 'Published',
            'visibility' => 'private',
        ]);

        $academyOnlyCourse = Course::factory()->create([
            'academy_id' => $academyA->id,
            'title' => 'Hidden Academy Only SEO',
            'slug' => 'hidden-academy-only-seo',
            'status' => 'Published',
            'visibility' => 'academy_only',
        ]);

        // Public /courses
        $this->get('http://alpha.localhost/courses')
            ->assertOk()
            ->assertSee($publicPublished->title)
            ->assertDontSee($draftCourse->title)
            ->assertDontSee($unpublishedCourse->title)
            ->assertDontSee($archivedCourse->title)
            ->assertDontSee($privateCourse->title)
            ->assertDontSee($academyOnlyCourse->title);

        // Public /courses/{slug}
        $this->get("http://alpha.localhost/courses/{$publicPublished->slug}")
            ->assertOk()
            ->assertSee($publicPublished->title);

        foreach ([$draftCourse, $unpublishedCourse, $archivedCourse, $privateCourse, $academyOnlyCourse] as $hidden) {
            $this->get("http://alpha.localhost/courses/{$hidden->slug}")
                ->assertNotFound();
        }
    }

    public function test_student_catalogue_shows_published_public_and_academy_only_courses_for_their_academy_only(): void
    {
        $academyA = Academy::factory()->create();

        $studentA = $this->createAcademyUser($academyA, 'student');

        $publicCourse = Course::factory()->create([
            'academy_id' => $academyA->id,
            'title' => 'Student Visible Public',
            'slug' => 'student-visible-public',
            'status' => 'Published',
            'visibility' => 'public',
        ]);

        $academyOnlyCourse = Course::factory()->create([
            'academy_id' => $academyA->id,
            'title' => 'Student Visible Academy Only',
            'slug' => 'student-visible-academy-only',
            'status' => 'Published',
            'visibility' => 'academy_only',
        ]);

        $draftCourse = Course::factory()->create([
            'academy_id' => $academyA->id,
            'title' => 'Student Hidden Draft',
            'status' => 'Draft',
            'visibility' => 'public',
        ]);

        $archivedCourse = Course::factory()->create([
            'academy_id' => $academyA->id,
            'title' => 'Student Hidden Archived',
            'status' => 'Archived',
            'visibility' => 'public',
        ]);

        $privateCourse = Course::factory()->create([
            'academy_id' => $academyA->id,
            'title' => 'Student Hidden Private',
            'status' => 'Published',
            'visibility' => 'private',
        ]);

        $profile = StudentProfile::create([
            'user_id' => $studentA->id,
            'academy_id' => $academyA->id,
            'student_code' => 'STU-CATALOGUE-01',
            'active' => true,
        ]);

        Enrollment::create([
            'academy_id' => $academyA->id,
            'student_profile_id' => $profile->id,
            'course_id' => $publicCourse->id,
            'status' => 'active',
        ]);

        Enrollment::create([
            'academy_id' => $academyA->id,
            'student_profile_id' => $profile->id,
            'course_id' => $academyOnlyCourse->id,
            'status' => 'active',
        ]);

        $this->actingAs($studentA)
            ->withSession([TenantContext::SESSION_KEY => $academyA->id])
            ->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee($publicCourse->title)
            ->assertSee($academyOnlyCourse->title)
            ->assertDontSee($draftCourse->title)
            ->assertDontSee($archivedCourse->title)
            ->assertDontSee($privateCourse->title);

        // Authenticated student in Academy A can also view academy_only detail page
        $this->actingAs($studentA)
            ->withSession([TenantContext::SESSION_KEY => $academyA->id])
            ->get(route('courses.public.show', $academyOnlyCourse->slug))
            ->assertOk()
            ->assertSee($academyOnlyCourse->title);
    }
}
