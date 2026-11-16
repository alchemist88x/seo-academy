<?php

namespace Tests\Feature;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\CourseTrainer;
use App\Domain\Courses\Models\Module;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Platform\Services\NavigationService;
use App\Domain\Support\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function createAcademy(): Academy
    {
        return Academy::create([
            'name' => 'SEO Test Academy',
            'slug' => 'seo-test-academy-'.Str::random(6),
            'subdomain' => 'test-'.Str::random(6),
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createUserWithRole(string $role, Academy $academy): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        $user->academies()->attach($academy->id, [
            'id' => (string) Str::uuid(),
            'role' => $role,
            'status' => 'active',
            'created_at' => now(),
        ]);

        return $user;
    }

    public function test_admin_navigation_structure(): void
    {
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin', $academy);

        $navService = app(NavigationService::class);
        $groups = $navService->getNavigationForUser($admin);

        $this->assertNotEmpty($groups);
        $titles = array_column($groups, 'title');
        $this->assertContains('Overview', $titles);
        $this->assertContains('People & Access', $titles);
        $this->assertContains('Curriculum & Records', $titles);
        $this->assertContains('System & Operations', $titles);

        // Flatten all labels
        $allLabels = [];
        foreach ($groups as $g) {
            foreach ($g['items'] as $item) {
                $allLabels[] = $item['label'];
            }
        }

        $this->assertContains('Dashboard', $allLabels);
        $this->assertContains('Students', $allLabels);
        $this->assertContains('Trainers', $allLabels);
        $this->assertContains('Members & Invitations', $allLabels);
        $this->assertContains('Courses', $allLabels);
        $this->assertContains('Enrollments', $allLabels);
        $this->assertContains('Assessments', $allLabels);
        $this->assertContains('Certificates', $allLabels);
        $this->assertContains('Support Desk', $allLabels);
        $this->assertContains('Academy Settings', $allLabels);
        $this->assertContains('File Management', $allLabels);
        $this->assertContains('Audit Logs', $allLabels);
        $this->assertContains('System Health', $allLabels);
        $this->assertContains('System & Email', $allLabels);
        $this->assertContains('Backups & Recovery', $allLabels);

        // Verify dead routes are NOT present
        $this->assertNotContains('Video Lessons', $allLabels);
        $this->assertNotContains('Live Classes', $allLabels);
        $this->assertNotContains('Admissions', $allLabels);
    }

    public function test_trainer_navigation_structure(): void
    {
        $academy = $this->createAcademy();
        $trainer = $this->createUserWithRole('trainer', $academy);

        $navService = app(NavigationService::class);
        $groups = $navService->getNavigationForUser($trainer);

        $titles = array_column($groups, 'title');
        $this->assertContains('Overview', $titles);
        $this->assertContains('Teaching', $titles);
        $this->assertNotContains('System & Operations', $titles);

        $allLabels = [];
        foreach ($groups as $g) {
            foreach ($g['items'] as $item) {
                $allLabels[] = $item['label'];
            }
        }

        $this->assertContains('Dashboard', $allLabels);
        $this->assertContains('My Assigned Courses', $allLabels);
        $this->assertContains('My Students', $allLabels);
        $this->assertContains('Assessments & Grading', $allLabels);

        // Must not expose admin operations
        $this->assertNotContains('Academy Settings', $allLabels);
        $this->assertNotContains('Audit Logs', $allLabels);
        $this->assertNotContains('Backups & Recovery', $allLabels);
        $this->assertNotContains('Submissions', $allLabels);
    }

    public function test_support_staff_navigation_structure(): void
    {
        $academy = $this->createAcademy();
        $support = $this->createUserWithRole('support_staff', $academy);

        $navService = app(NavigationService::class);
        $groups = $navService->getNavigationForUser($support);

        $titles = array_column($groups, 'title');
        $this->assertContains('Overview', $titles);
        $this->assertContains('Support Desk', $titles);
        $this->assertNotContains('Curriculum & Records', $titles);
        $this->assertNotContains('Teaching', $titles);

        $allLabels = [];
        foreach ($groups as $g) {
            foreach ($g['items'] as $item) {
                $allLabels[] = $item['label'];
            }
        }

        $this->assertContains('Dashboard', $allLabels);
        $this->assertContains('Support Tickets', $allLabels);

        // No dead links
        $this->assertNotContains('Admissions', $allLabels);
    }

    public function test_student_navigation_structure(): void
    {
        $academy = $this->createAcademy();
        $student = $this->createUserWithRole('student', $academy);

        $navService = app(NavigationService::class);
        $groups = $navService->getNavigationForUser($student);

        $titles = array_column($groups, 'title');
        $this->assertContains('Overview', $titles);
        $this->assertContains('Learning', $titles);
        $this->assertNotContains('People & Access', $titles);
        $this->assertNotContains('Teaching', $titles);
        $this->assertNotContains('System & Operations', $titles);

        $allLabels = [];
        foreach ($groups as $g) {
            foreach ($g['items'] as $item) {
                $allLabels[] = $item['label'];
            }
        }

        $this->assertContains('Dashboard', $allLabels);
        $this->assertContains('My Courses', $allLabels);
        $this->assertContains('My Certificates', $allLabels);

        // Dead links removed
        $this->assertNotContains('Video Lessons', $allLabels);
        $this->assertNotContains('Assignments', $allLabels);
    }

    public function test_admin_dashboard_renders_sidebar_and_main_content(): void
    {
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin', $academy);

        $response = $this->actingAs($admin)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('id="main-sidebar"', false);
        $response->assertSee('class="sidebar border-r border-slate-800"', false);
        $response->assertSee('class="main-content"', false);
        $response->assertSee(route('admin.students.index'), false);
        $response->assertSee(route('admin.courses.index'), false);
        $response->assertSee(route('admin.certificates.index'), false);
    }

    public function test_trainer_dashboard_renders_sidebar_and_main_content(): void
    {
        $academy = $this->createAcademy();
        $trainer = $this->createUserWithRole('trainer', $academy);

        $response = $this->actingAs($trainer)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('trainer.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('id="main-sidebar"', false);
        $response->assertSee('class="sidebar border-r border-slate-800"', false);
        $response->assertSee('class="main-content"', false);
        $response->assertSee(route('trainer.courses.index'), false);
        $response->assertSee(route('trainer.students.index'), false);
        $response->assertSee(route('trainer.assessments.index'), false);
    }

    public function test_support_dashboard_renders_sidebar_and_main_content(): void
    {
        $academy = $this->createAcademy();
        $support = $this->createUserWithRole('support_staff', $academy);

        $response = $this->actingAs($support)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('support.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('id="main-sidebar"', false);
        $response->assertSee('class="sidebar border-r border-slate-800"', false);
        $response->assertSee('class="main-content"', false);
        $response->assertSee(route('support.tickets.index'), false);
    }

    public function test_student_dashboard_renders_sidebar_and_main_content(): void
    {
        $academy = $this->createAcademy();
        $student = $this->createUserWithRole('student', $academy);

        $response = $this->actingAs($student)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('student.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('id="main-sidebar"', false);
        $response->assertSee('class="sidebar border-r border-slate-800"', false);
        $response->assertSee('class="main-content"', false);
        $response->assertSee(route('student.courses.index'), false);
        $response->assertSee(route('student.certificates.index'), false);
    }

    public function test_subpages_render_sidebar_and_maintain_active_highlighting(): void
    {
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin', $academy);

        // 1. Admin Students Index
        $response = $this->actingAs($admin)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('admin.students.index'));
        $response->assertStatus(200);
        $response->assertSee('id="main-sidebar"', false);
        $response->assertSee('aria-current="page"', false);

        // 2. Notifications Index
        $response = $this->actingAs($admin)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('notifications.index'));
        $response->assertStatus(200);
        $response->assertSee('id="main-sidebar"', false);
        $response->assertSee('aria-current="page"', false);

        // 3. Admin Course Structure (nested route under courses)
        $course = Course::factory()->create(['academy_id' => $academy->id]);
        $response = $this->actingAs($admin)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('admin.courses.structure', $course));
        $response->assertStatus(200);
        $response->assertSee('id="main-sidebar"', false);
        $response->assertSee('aria-current="page"', false);

        // 4. Support Ticket Detail (nested route under tickets)
        $support = $this->createUserWithRole('support_staff', $academy);
        $ticket = SupportTicket::create([
            'academy_id' => $academy->id,
            'user_id' => $admin->id,
            'subject' => 'Billing inquiry',
            'description' => 'Need help with invoice',
            'status' => 'open',
            'priority' => 'medium',
            'category' => 'billing',
        ]);
        $response = $this->actingAs($support)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('support.tickets.show', $ticket));
        $response->assertStatus(200);
        $response->assertSee('id="main-sidebar"', false);
        $response->assertSee('aria-current="page"', false);
    }

    public function test_trainer_subpages_render_sidebar_with_active_state(): void
    {
        $academy = $this->createAcademy();
        $trainer = $this->createUserWithRole('trainer', $academy);
        $course = Course::factory()->create(['academy_id' => $academy->id]);
        CourseTrainer::create([
            'academy_id' => $academy->id,
            'course_id' => $course->id,
            'user_id' => $trainer->id,
            'is_primary' => true,
        ]);

        $response = $this->actingAs($trainer)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('trainer.courses.structure', $course));

        $response->assertStatus(200);
        $response->assertSee('id="main-sidebar"', false);
        $response->assertSee('aria-current="page"', false);
        $response->assertSee('My Assigned Courses', false);
    }

    public function test_lesson_preview_renders_sidebar(): void
    {
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin', $academy);
        $course = Course::factory()->create(['academy_id' => $academy->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        $response = $this->actingAs($admin)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('lessons.preview', ['course' => $course, 'lesson' => $lesson]));

        $response->assertStatus(200);
        $response->assertSee('id="main-sidebar"', false);
        $response->assertSee('class="main-content"', false);
    }

    public function test_role_authorization_and_unauthenticated_boundaries(): void
    {
        $academy = $this->createAcademy();
        $student = $this->createUserWithRole('student', $academy);
        $support = $this->createUserWithRole('support_staff', $academy);

        // Student forbidden from admin dashboard
        $this->actingAs($student)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('admin.dashboard'))
            ->assertStatus(403);

        // Support staff forbidden from trainer dashboard
        $this->actingAs($support)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('trainer.dashboard'))
            ->assertStatus(403);

        // Unauthenticated redirected to login
        auth()->logout();
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
        $this->get(route('student.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_sidebar_accessibility_and_responsive_markup(): void
    {
        $academy = $this->createAcademy();
        $admin = $this->createUserWithRole('admin', $academy);

        $response = $this->actingAs($admin)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('admin.dashboard'));

        $response->assertStatus(200);

        // ARIA and accessibility
        $response->assertSee('aria-label="Sidebar Navigation"', false);
        $response->assertSee('role="navigation"', false);
        $response->assertSee('aria-controls="main-sidebar"', false);
        $response->assertSee('@keydown.escape.window', false);

        // Backdrop for mobile
        $response->assertSee('x-show="$store.sidebar.mobileOpen"', false);
        $response->assertSee('aria-hidden="true"', false);

        // Focus ring classes
        $response->assertSee('focus-visible:ring-primary-400', false);
    }

    public function test_no_unauthorized_menu_destinations_exposed_in_rendered_sidebar(): void
    {
        $academy = $this->createAcademy();
        $student = $this->createUserWithRole('student', $academy);
        $trainer = $this->createUserWithRole('trainer', $academy);
        $support = $this->createUserWithRole('support_staff', $academy);

        // Student view checks
        $studentResponse = $this->actingAs($student)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('student.dashboard'));
        $studentResponse->assertStatus(200);
        $studentResponse->assertDontSee('Academy Settings');
        $studentResponse->assertDontSee('Backups & Recovery');
        $studentResponse->assertDontSee('Audit Logs');
        $studentResponse->assertDontSee('System Health');
        $studentResponse->assertDontSee('Support Desk');
        $studentResponse->assertDontSee('Members & Invitations');
        $studentResponse->assertDontSee('Video Lessons');
        $studentResponse->assertDontSee('Live Classes');

        // Trainer view checks
        $trainerResponse = $this->actingAs($trainer)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('trainer.dashboard'));
        $trainerResponse->assertStatus(200);
        $trainerResponse->assertDontSee('Academy Settings');
        $trainerResponse->assertDontSee('Backups & Recovery');
        $trainerResponse->assertDontSee('Audit Logs');
        $trainerResponse->assertDontSee('System Health');
        $trainerResponse->assertDontSee('Members & Invitations');
        $trainerResponse->assertDontSee('Submissions');

        // Support Staff view checks
        $supportResponse = $this->actingAs($support)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('support.dashboard'));
        $supportResponse->assertStatus(200);
        $supportResponse->assertDontSee('My Assigned Courses');
        $supportResponse->assertDontSee('Curriculum & Records');
        $supportResponse->assertDontSee('Backups & Recovery');
        $supportResponse->assertDontSee('System Health');
        $supportResponse->assertDontSee('Admissions');
    }
}
