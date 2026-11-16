<?php

namespace Tests\Feature\UI;

use App\Domain\Academies\Models\Academy;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AllModulesPageRenderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
        $this->seed(RoleSeeder::class);

        Academy::create([
            'name' => 'SEO Mastery Academy',
            'slug' => 'seo-mastery',
            'is_active' => true,
        ]);
    }

    public function test_admin_pages_render_cleanly_with_form_controls(): void
    {
        $admin = User::factory()->create(['email' => 'admin-test@seo-academy.local', 'is_active' => true]);
        $admin->assignRole('admin');

        $adminRoutes = [
            'admin.dashboard',
            'admin.courses.index',
            'admin.students.index',
            'admin.trainers.index',
            'admin.assessments.index',
            'admin.question-banks.index',
            'admin.assignments.index',
            'admin.enrollments.index',
            'admin.certificates.index',
            'admin.academy.settings',
            'admin.system.settings',
        ];

        foreach ($adminRoutes as $routeName) {
            $response = $this->actingAs($admin)->get(route($routeName));
            $response->assertStatus(200);
            $response->assertDontSee('Server Error');
            $response->assertDontSee('Exception');
        }
    }

    public function test_trainer_pages_render_cleanly(): void
    {
        $trainer = User::factory()->create(['email' => 'trainer-test@seo-academy.local', 'is_active' => true]);
        $trainer->assignRole('trainer');

        $trainerRoutes = [
            'trainer.dashboard',
            'trainer.courses.index',
            'trainer.students.index',
            'trainer.assessments.index',
            'trainer.assignments.index',
            'trainer.question-banks.index',
        ];

        foreach ($trainerRoutes as $routeName) {
            $response = $this->actingAs($trainer)->get(route($routeName));
            $response->assertStatus(200);
            $response->assertDontSee('Server Error');
        }
    }

    public function test_student_and_support_pages_render_cleanly(): void
    {
        $student = User::factory()->create(['email' => 'student-test@seo-academy.local', 'is_active' => true]);
        $student->assignRole('student');

        $studentRoutes = [
            'student.dashboard',
            'student.courses.index',
            'student.assignments.index',
            'student.certificates.index',
        ];

        foreach ($studentRoutes as $routeName) {
            $response = $this->actingAs($student)->get(route($routeName));
            $response->assertStatus(200);
            $response->assertDontSee('Server Error');
        }

        $support = User::factory()->create(['email' => 'support-test@seo-academy.local', 'is_active' => true]);
        $support->assignRole('support_staff');

        $supportRoutes = [
            'support.dashboard',
            'support.tickets.index',
        ];

        foreach ($supportRoutes as $routeName) {
            $response = $this->actingAs($support)->get(route($routeName));
            $response->assertStatus(200);
            $response->assertDontSee('Server Error');
        }
    }
}
