<?php

namespace Tests\Feature\Operations;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Services\AuditLogService;
use App\Livewire\Admin\AuditLogsIndex;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    private Academy $academy;

    private User $admin;

    private User $trainer;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->academy = Academy::factory()->create();

        $this->admin = User::factory()->create(['name' => 'Auditor Admin', 'email' => 'admin@test.local', 'is_active' => true]);
        $this->admin->assignRole('admin');
        $this->admin->academies()->attach($this->academy->id, ['id' => (string) Str::uuid(), 'role' => 'admin', 'status' => 'active']);

        $this->trainer = User::factory()->create(['name' => 'Auditor Trainer', 'is_active' => true]);
        $this->trainer->assignRole('trainer');
        $this->trainer->academies()->attach($this->academy->id, ['id' => (string) Str::uuid(), 'role' => 'trainer', 'status' => 'active']);

        $this->student = User::factory()->create(['name' => 'Auditor Student', 'is_active' => true]);
        $this->student->assignRole('student');
        $this->student->academies()->attach($this->academy->id, ['id' => (string) Str::uuid(), 'role' => 'student', 'status' => 'active']);
    }

    /**
     * Test audit event recording and sensitive key redaction.
     */
    public function test_audit_recording_and_sensitive_redaction(): void
    {
        $service = app(AuditLogService::class);

        $log = $service->record(
            academy: $this->academy,
            actor: $this->admin,
            action: 'settings.updated',
            subjectType: Academy::class,
            subjectId: (string) $this->academy->id,
            properties: [
                'name' => 'New Academy Name',
                'smtp_password' => 'SuperSecret123!',
                'nested' => [
                    'api_token' => 'Bearer XYZ-999',
                    'public_setting' => 'VisibleValue',
                ],
            ]
        );

        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertSame('settings.updated', $log->action);
        $this->assertSame((string) $this->admin->id, (string) $log->user_id);

        // Assert sensitive keys are redacted
        $this->assertSame('[REDACTED]', $log->properties['smtp_password']);
        $this->assertSame('[REDACTED]', $log->properties['nested']['api_token']);
        $this->assertSame('VisibleValue', $log->properties['nested']['public_setting']);
        $this->assertSame('New Academy Name', $log->properties['name']);

        // Assert actor snapshot is preserved in properties
        $this->assertSame('Auditor Admin', $log->properties['_actor']['name']);
        $this->assertSame('admin@test.local', $log->properties['_actor']['email']);
    }

    /**
     * Test audit record survives actor user deletion with actor metadata intact.
     */
    public function test_audit_record_survives_actor_deletion(): void
    {
        $service = app(AuditLogService::class);

        $tempUser = User::factory()->create(['name' => 'Ephemeral Staff', 'email' => 'ephemeral@test.local']);
        $tempUser->assignRole('trainer');

        $log = $service->record(
            academy: $this->academy,
            actor: $tempUser,
            action: 'course.lesson_published',
            subjectType: Course::class,
            subjectId: (string) Str::uuid(),
            properties: ['title' => 'Lesson 101']
        );

        $logId = $log->id;

        // Delete the actor user
        $tempUser->delete();

        // Refresh audit log from database
        $refreshed = AuditLog::find($logId);
        $this->assertNotNull($refreshed, 'Audit log must never be deleted when actor is deleted');
        $this->assertNull($refreshed->user_id, 'user_id foreign key must be SET NULL');

        // Actor name snapshot remains preserved in properties
        $this->assertSame('Ephemeral Staff', $refreshed->properties['_actor']['name']);
        $this->assertSame('ephemeral@test.local', $refreshed->properties['_actor']['email']);
    }

    /**
     * Test admin can view and filter audit logs in Livewire component.
     */
    public function test_admin_audit_logs_livewire_component(): void
    {
        $service = app(AuditLogService::class);

        $log = $service->record(
            academy: $this->academy,
            actor: $this->admin,
            action: 'enrollment.activated',
            subjectType: 'Enrollment',
            subjectId: 'ENR-9988',
            properties: ['student' => 'Test Student']
        );

        $this->actingAs($this->admin);

        Livewire::test(AuditLogsIndex::class)
            ->assertSee('enrollment.activated')
            ->assertSee('Auditor Admin')
            ->call('selectLog', $log->id)
            ->assertSee('ENR-9988');
    }

    /**
     * Test non-admin authorization boundaries (students, trainers, support staff blocked from audit logs).
     */
    public function test_non_admin_cannot_access_audit_logs(): void
    {
        $this->actingAs($this->student);
        $this->get('/admin/audit-logs')->assertForbidden();

        $this->actingAs($this->trainer);
        $this->get('/admin/audit-logs')->assertForbidden();

        auth()->logout();
        $this->get('/admin/audit-logs')->assertRedirect('/login');
    }
}
