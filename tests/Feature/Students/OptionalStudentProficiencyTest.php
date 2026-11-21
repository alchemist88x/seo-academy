<?php

namespace Tests\Feature\Students;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Students\Services\StudentManagementService;
use App\Domain\Tenants\Services\TenantContext;
use App\Livewire\Admin\StudentsIndex;
use App\Livewire\Dashboard\StudentDashboard;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OptionalStudentProficiencyTest extends TestCase
{
    use RefreshDatabase;

    private Academy $academy;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        RateLimiter::clear('register:127.0.0.1');

        $this->academy = Academy::create([
            'name' => 'SEO Academy Proficiency Test',
            'slug' => 'seo-academy-prof-test-'.Str::random(6),
            'is_active' => true,
            'settings' => [
                'allow_self_registration' => true,
                'trainers_can_manage_proficiency' => true,
            ],
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin.prof@example.com',
            'is_active' => true,
        ]);
        $this->admin->assignRole('admin');
        $this->admin->academies()->attach($this->academy->id, [
            'id' => (string) Str::uuid(),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->withSession([TenantContext::SESSION_KEY => $this->academy->id]);
        app(TenantContext::class)->setAcademy($this->academy);
    }

    /**
     * Requirement 1: Admin creates a student without selecting proficiency.
     */
    public function test_admin_can_create_student_without_proficiency(): void
    {
        Livewire::actingAs($this->admin)
            ->test(StudentsIndex::class)
            ->set('name', 'Unassessed Alice')
            ->set('email', 'alice.unassessed@example.com')
            ->set('formProficiency', '')
            ->call('saveStudent')
            ->assertHasNoErrors();

        $user = User::where('email', 'alice.unassessed@example.com')->firstOrFail();
        $profile = StudentProfile::where('user_id', $user->id)->firstOrFail();

        $this->assertNull($profile->proficiency);
        $this->assertNull($profile->level);
        $this->assertTrue($profile->isUnassessed());
        $this->assertFalse($profile->isAssessed());
        $this->assertSame('Not Assessed', $profile->proficiency_label);
        $this->assertSame('gray', $profile->proficiency_badge_color);
    }

    /**
     * Requirement 2: Student self-registers without proficiency.
     */
    public function test_student_self_registers_without_proficiency(): void
    {
        $response = $this->post(route('register.attempt'), [
            'first_name' => 'Bob',
            'last_name' => 'SelfReg',
            'email' => 'bob.selfreg@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('student.dashboard'));

        $user = User::where('email', 'bob.selfreg@example.com')->firstOrFail();
        $profile = $user->studentProfile;

        $this->assertNotNull($profile);
        $this->assertNull($profile->proficiency);
        $this->assertNull($profile->level);
        $this->assertSame('Not Assessed', $profile->proficiency_label);
        $this->assertSame('gray', $profile->proficiency_badge_color);
    }

    /**
     * Requirement 3: NULL proficiency persists after database reload and raw query check.
     */
    public function test_null_proficiency_persists_after_database_reload(): void
    {
        $service = app(StudentManagementService::class);
        $profile = $service->createOrAttach($this->academy, [
            'name' => 'Charlie Persist',
            'email' => 'charlie.persist@example.com',
            'proficiency' => null,
        ], $this->admin);

        $this->assertNull($profile->proficiency);

        // Reload Eloquent model fresh from database
        $freshProfile = $profile->fresh();
        $this->assertNull($freshProfile->proficiency);
        $this->assertNull($freshProfile->level);

        // Raw database check ensures no default silent overwrite
        $rawRow = DB::table('student_profiles')->where('id', $profile->id)->first();
        $this->assertNotNull($rawRow);
        $this->assertNull($rawRow->proficiency);
        $this->assertNull($rawRow->level);
    }

    /**
     * Requirement 4: Admin assigns beginner later.
     */
    public function test_admin_assigns_beginner_later(): void
    {
        $service = app(StudentManagementService::class);
        $profile = $service->createOrAttach($this->academy, [
            'name' => 'David Late',
            'email' => 'david.late@example.com',
            'proficiency' => null,
        ], $this->admin);

        $this->assertNull($profile->proficiency);

        Livewire::actingAs($this->admin)
            ->test(StudentsIndex::class)
            ->call('openEditModal', $profile->id)
            ->assertSet('formProficiency', '')
            ->set('formProficiency', StudentProfile::PROFICIENCY_BEGINNER)
            ->call('saveStudent')
            ->assertHasNoErrors();

        $profile->refresh();
        $this->assertSame(StudentProfile::PROFICIENCY_BEGINNER, $profile->proficiency);
        $this->assertSame('Beginner', $profile->level);
        $this->assertSame('Beginner', $profile->proficiency_label);
        $this->assertSame('blue', $profile->proficiency_badge_color);
        $this->assertTrue($profile->isAssessed());
    }

    /**
     * Requirement 5: Admin changes proficiency across levels and back to unassessed.
     */
    public function test_admin_changes_proficiency_across_levels_and_back_to_unassessed(): void
    {
        $service = app(StudentManagementService::class);
        $profile = $service->createOrAttach($this->academy, [
            'name' => 'Elena Progression',
            'email' => 'elena.progression@example.com',
            'proficiency' => StudentProfile::PROFICIENCY_BEGINNER,
        ], $this->admin);

        $this->assertSame('beginner', $profile->proficiency);

        // Promote to Intermediate
        $service->update($profile, ['proficiency' => 'intermediate'], $this->admin);
        $profile->refresh();
        $this->assertSame('intermediate', $profile->proficiency);
        $this->assertSame('Intermediate', $profile->level);
        $this->assertSame('Intermediate', $profile->proficiency_label);
        $this->assertSame('amber', $profile->proficiency_badge_color);

        // Promote to Expert
        $service->update($profile, ['proficiency' => 'expert'], $this->admin);
        $profile->refresh();
        $this->assertSame('expert', $profile->proficiency);
        $this->assertSame('Expert', $profile->level);
        $this->assertSame('Expert', $profile->proficiency_label);
        $this->assertSame('purple', $profile->proficiency_badge_color);

        // Reset back to Not Assessed (null)
        $service->update($profile, ['proficiency' => null], $this->admin);
        $profile->refresh();
        $this->assertNull($profile->proficiency);
        $this->assertNull($profile->level);
        $this->assertSame('Not Assessed', $profile->proficiency_label);
        $this->assertSame('gray', $profile->proficiency_badge_color);
    }

    /**
     * Requirement 6: Not Assessed displays correctly in student profiles and dashboard UI.
     */
    public function test_not_assessed_displays_correctly_in_views(): void
    {
        $service = app(StudentManagementService::class);
        $profile = $service->createOrAttach($this->academy, [
            'name' => 'Fiona Display',
            'email' => 'fiona.display@example.com',
            'proficiency' => null,
        ], $this->admin);

        // Student Dashboard view test
        Livewire::actingAs($profile->user)
            ->test(StudentDashboard::class)
            ->assertSee('Not Assessed')
            ->assertDontSee('Not Assessed Level');

        // Admin Student view modal test
        Livewire::actingAs($this->admin)
            ->test(StudentsIndex::class)
            ->call('openViewModal', $profile->id)
            ->assertSee('Not Assessed');
    }

    /**
     * Requirement 7: Filters and analytics handle NULL safely.
     */
    public function test_filters_and_analytics_handle_null(): void
    {
        $service = app(StudentManagementService::class);

        // Create 2 unassessed, 1 beginner, 1 intermediate, 1 expert
        $service->createOrAttach($this->academy, ['name' => 'Null One', 'email' => 'null1@example.com', 'proficiency' => null], $this->admin);
        $service->createOrAttach($this->academy, ['name' => 'Null Two', 'email' => 'null2@example.com', 'proficiency' => null], $this->admin);
        $service->createOrAttach($this->academy, ['name' => 'Beg One', 'email' => 'beg1@example.com', 'proficiency' => 'beginner'], $this->admin);
        $service->createOrAttach($this->academy, ['name' => 'Inter One', 'email' => 'inter1@example.com', 'proficiency' => 'intermediate'], $this->admin);
        $service->createOrAttach($this->academy, ['name' => 'Exp One', 'email' => 'exp1@example.com', 'proficiency' => 'expert'], $this->admin);

        // Filter by 'not_assessed'
        $unassessedList = $service->listForAcademy($this->academy, ['proficiency' => 'not_assessed']);
        $this->assertSame(2, $unassessedList->total());
        foreach ($unassessedList->items() as $item) {
            $this->assertNull($item->proficiency);
            $this->assertSame('Not Assessed', $item->proficiency_label);
        }

        // Filter by 'beginner'
        $beginnerList = $service->listForAcademy($this->academy, ['proficiency' => 'beginner']);
        $this->assertSame(1, $beginnerList->total());
        $this->assertSame('beginner', $beginnerList->items()[0]->proficiency);

        // Analytics aggregation group by proficiency including NULL
        $counts = StudentProfile::query()
            ->forAcademy($this->academy)
            ->select('proficiency', DB::raw('count(*) as count'))
            ->groupBy('proficiency')
            ->pluck('count', 'proficiency')
            ->toArray();

        $this->assertSame(2, $counts[''] ?? $counts[null] ?? 0);
        $this->assertSame(1, $counts['beginner']);
        $this->assertSame(1, $counts['intermediate']);
        $this->assertSame(1, $counts['expert']);
    }

    /**
     * Requirement 8: Existing students retain their current proficiency without corruption.
     */
    public function test_existing_students_retain_their_current_proficiency(): void
    {
        $service = app(StudentManagementService::class);

        $studentA = $service->createOrAttach($this->academy, [
            'name' => 'Existing A',
            'email' => 'existingA@example.com',
            'proficiency' => 'beginner',
        ], $this->admin);

        $studentB = $service->createOrAttach($this->academy, [
            'name' => 'Existing B',
            'email' => 'existingB@example.com',
            'proficiency' => 'expert',
        ], $this->admin);

        $this->assertSame('beginner', $studentA->fresh()->proficiency);
        $this->assertSame('expert', $studentB->fresh()->proficiency);
    }

    /**
     * Requirement 9: Unauthorized trainers cannot modify proficiency.
     */
    public function test_unauthorized_trainers_cannot_modify_proficiency(): void
    {
        $service = app(StudentManagementService::class);

        // Create a student in academy
        $student = $service->createOrAttach($this->academy, [
            'name' => 'Protected Student',
            'email' => 'protected.student@example.com',
            'proficiency' => null,
        ], $this->admin);

        // Create Course 1 and Course 2
        $course1 = Course::factory()->create(['academy_id' => $this->academy->id, 'title' => 'SEO Course 1']);
        $course2 = Course::factory()->create(['academy_id' => $this->academy->id, 'title' => 'SEO Course 2']);

        // Student is enrolled ONLY in Course 1
        Enrollment::create([
            'user_id' => $student->user_id,
            'course_id' => $course1->id,
            'student_profile_id' => $student->id,
            'academy_id' => $this->academy->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        // Trainer A is assigned to Course 1 (Student enrolled)
        $trainerA = User::factory()->create(['is_active' => true]);
        $trainerA->assignRole('trainer');
        $trainerA->academies()->attach($this->academy->id, ['id' => (string) Str::uuid(), 'role' => 'trainer', 'status' => 'active']);
        $course1->trainers()->attach($trainerA->id, ['academy_id' => $this->academy->id]);

        // Trainer B is assigned to Course 2 (Student NOT enrolled)
        $trainerB = User::factory()->create(['is_active' => true]);
        $trainerB->assignRole('trainer');
        $trainerB->academies()->attach($this->academy->id, ['id' => (string) Str::uuid(), 'role' => 'trainer', 'status' => 'active']);
        $course2->trainers()->attach($trainerB->id, ['academy_id' => $this->academy->id]);

        // Trainer B attempts to update student proficiency -> Forbidden (outside assigned course)
        $this->expectException(AuthorizationException::class);
        $service->updateProficiency($student, 'intermediate', $trainerB);
    }

    /**
     * Requirement 9b: Authorized trainer assigned to course CAN update proficiency if permitted.
     */
    public function test_authorized_trainer_assigned_to_course_can_update_proficiency(): void
    {
        $service = app(StudentManagementService::class);

        $student = $service->createOrAttach($this->academy, [
            'name' => 'Assessable Student',
            'email' => 'assessable.student@example.com',
            'proficiency' => null,
        ], $this->admin);

        $course = Course::factory()->create(['academy_id' => $this->academy->id, 'title' => 'Advanced SEO']);
        Enrollment::create([
            'user_id' => $student->user_id,
            'course_id' => $course->id,
            'student_profile_id' => $student->id,
            'academy_id' => $this->academy->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $trainer = User::factory()->create(['is_active' => true]);
        $trainer->assignRole('trainer');
        $trainer->academies()->attach($this->academy->id, ['id' => (string) Str::uuid(), 'role' => 'trainer', 'status' => 'active']);
        $course->trainers()->attach($trainer->id, ['academy_id' => $this->academy->id]);

        $service->updateProficiency($student, 'intermediate', $trainer);

        $student->refresh();
        $this->assertSame('intermediate', $student->proficiency);
        $this->assertSame('Intermediate', $student->proficiency_label);
    }
}
