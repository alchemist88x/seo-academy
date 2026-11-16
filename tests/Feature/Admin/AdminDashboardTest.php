<?php

namespace Tests\Feature\Admin;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    protected function createAcademyUser(Academy $academy, string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->academies()->attach($academy->getKey(), [
            'id' => (string) Str::uuid(),
            'role' => $role,
        ]);

        return $user;
    }

    public function test_admin_dashboard_renders_real_db_metrics_without_placeholders(): void
    {
        $academy = Academy::factory()->create([
            'name' => 'RankMaster Academy',
            'subdomain' => 'rankmaster',
            'timezone' => 'Europe/London',
            'support_email' => 'team@rankmaster.test',
        ]);
        $admin = $this->createAcademyUser($academy, 'admin');

        // 2 active students
        $student1 = $this->createAcademyUser($academy, 'student');
        $student2 = $this->createAcademyUser($academy, 'student');
        StudentProfile::factory()->create([
            'academy_id' => $academy->id,
            'user_id' => $student1->id,
            'active' => true,
        ]);
        StudentProfile::factory()->create([
            'academy_id' => $academy->id,
            'user_id' => $student2->id,
            'active' => true,
        ]);

        // 1 published course + 1 draft course
        Course::factory()->create([
            'academy_id' => $academy->id,
            'status' => 'Active',
        ]);
        Course::factory()->create([
            'academy_id' => $academy->id,
            'status' => 'Draft',
        ]);

        // 1 open support ticket + 1 resolved ticket
        SupportTicket::create([
            'academy_id' => $academy->id,
            'user_id' => $student1->id,
            'subject' => 'Cannot access lesson 1',
            'description' => 'Help needed.',
            'status' => 'Pending',
        ]);
        SupportTicket::create([
            'academy_id' => $academy->id,
            'user_id' => $student2->id,
            'subject' => 'Resolved issue',
            'description' => 'All good now.',
            'status' => 'Resolved',
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'academy.profile_updated',
            'subject_type' => Academy::class,
            'subject_id' => $academy->id,
            'properties' => ['academy_id' => $academy->id, 'updated_fields' => ['name']],
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->withSession([TenantContext::SESSION_KEY => $academy->id])
            ->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('RankMaster Academy');
        $response->assertSee('Europe/London');
        $response->assertSee('team@rankmaster.test');
        $response->assertSee('Academy Settings');
        $response->assertSee('1 draft / 2 total');
        $response->assertSee('academy.profile_updated');

        // Ensure hardcoded Phase 1 placeholders are gone
        $response->assertDontSeeText('248');
        $response->assertDontSeeText('76%');
    }
}
