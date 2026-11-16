<?php

namespace Tests\Feature\Operations;

use App\Domain\Academies\Models\Academy;
use App\Domain\Notifications\Models\AcademyNotification;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\SupportTicketMessage;
use App\Domain\Support\Services\SupportTicketService;
use App\Domain\Tenants\Services\TenantContext;
use App\Livewire\Dashboard\SupportDashboard;
use App\Livewire\Support\SupportTicketDetail;
use App\Livewire\Support\SupportTicketsIndex;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SupportOperationsTest extends TestCase
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

    private function createTicket(Academy $academy, User $student, array $attributes = []): SupportTicket
    {
        return SupportTicket::create(array_merge([
            'academy_id' => $academy->getKey(),
            'user_id' => $student->getKey(),
            'subject' => 'Cannot play lesson video',
            'description' => 'I am getting an error when loading lesson 3.',
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => SupportTicket::PRIORITY_MEDIUM,
        ], $attributes));
    }

    public function test_support_dashboard_displays_real_ticket_metrics(): void
    {
        $academy = Academy::factory()->create(['slug' => 'support-dash-acad']);
        $staff = $this->createAcademyMember($academy, 'support_staff');
        $student = $this->createAcademyMember($academy, 'student');

        // Create tickets with various statuses
        $this->createTicket($academy, $student, [
            'status' => SupportTicket::STATUS_OPEN,
            'assigned_to' => $staff->getKey(),
        ]);
        $this->createTicket($academy, $student, [
            'status' => SupportTicket::STATUS_OPEN,
        ]);
        $this->createTicket($academy, $student, [
            'status' => SupportTicket::STATUS_IN_PROGRESS,
            'assigned_to' => $staff->getKey(),
        ]);
        $this->createTicket($academy, $student, [
            'status' => SupportTicket::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);
        $this->createTicket($academy, $student, [
            'status' => SupportTicket::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);
        $this->createTicket($academy, $student, [
            'status' => SupportTicket::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);
        $this->createTicket($academy, $student, [
            'status' => SupportTicket::STATUS_CLOSED,
        ]);

        app(TenantContext::class)->setAcademy($academy);

        $response = $this->actingAs($staff)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('support.dashboard'));

        $response->assertStatus(200);

        Livewire::actingAs($staff)
            ->test(SupportDashboard::class)
            ->assertSee('Total Tickets')
            ->assertSee('7') // Total tickets
            ->assertSee('Open Tickets')
            ->assertSee('2') // Open tickets
            ->assertSee('In Progress')
            ->assertSee('1') // In progress
            ->assertSee('Resolved Today')
            ->assertSee('3'); // Resolved today
    }

    public function test_support_staff_can_view_support_tickets_and_ticket_details(): void
    {
        $academy = Academy::factory()->create(['slug' => 'support-view-acad']);
        $staff = $this->createAcademyMember($academy, 'support_staff');
        $student = $this->createAcademyMember($academy, 'student');

        $ticket = $this->createTicket($academy, $student, [
            'subject' => 'Payment Issue with Invoice',
            'description' => 'Need help with receipt download.',
        ]);

        app(TenantContext::class)->setAcademy($academy);

        // View ticket list
        $this->actingAs($staff)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('support.tickets.index'))
            ->assertStatus(200)
            ->assertSee('Payment Issue with Invoice');

        Livewire::actingAs($staff)
            ->test(SupportTicketsIndex::class)
            ->assertSee('Payment Issue with Invoice');

        // View ticket detail
        $this->actingAs($staff)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('support.tickets.show', $ticket))
            ->assertStatus(200)
            ->assertSee('Payment Issue with Invoice')
            ->assertSee('Need help with receipt download.');

        Livewire::actingAs($staff)
            ->test(SupportTicketDetail::class, ['ticket' => (string) $ticket->getKey()])
            ->assertSee('Payment Issue with Invoice')
            ->assertSee('Need help with receipt download.');
    }

    public function test_support_staff_can_assign_ticket_creating_audit_log_and_notification(): void
    {
        $academy = Academy::factory()->create(['slug' => 'support-assign-acad']);
        $actorStaff = $this->createAcademyMember($academy, 'support_staff', ['name' => 'Sara Staff']);
        $targetStaff = $this->createAcademyMember($academy, 'support_staff', ['name' => 'Tom Tech']);
        $student = $this->createAcademyMember($academy, 'student');

        $ticket = $this->createTicket($academy, $student, [
            'subject' => 'DNS Configuration question',
        ]);

        app(TenantContext::class)->setAcademy($academy);

        // Assign ticket to target staff
        Livewire::actingAs($actorStaff)
            ->test(SupportTicketDetail::class, ['ticket' => (string) $ticket->getKey()])
            ->set('selectedAssignee', (string) $targetStaff->getKey())
            ->call('updateAssignment')
            ->assertDispatched('toast');

        $ticket->refresh();
        $this->assertSame($targetStaff->getKey(), $ticket->assigned_to);

        // Assert AuditLog was recorded
        $audit = AuditLog::query()
            ->where('action', 'ticket.assigned')
            ->where('subject_id', (string) $ticket->getKey())
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($actorStaff->getKey(), $audit->user_id);
        $this->assertSame($targetStaff->getKey(), $audit->properties['assigned_to']);

        // Assert notification was created for assigned staff
        $notification = AcademyNotification::query()
            ->where('academy_id', $academy->getKey())
            ->where('target', (string) $targetStaff->getKey())
            ->where('category', 'support')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame('Support Ticket Assigned', $notification->title);
        $this->assertSame('staff', $notification->audience);
        $this->assertSame($actorStaff->getKey(), $notification->created_by);
    }

    public function test_support_staff_can_update_ticket_status_with_validated_transitions_and_audit(): void
    {
        $academy = Academy::factory()->create(['slug' => 'support-status-acad']);
        $staff = $this->createAcademyMember($academy, 'support_staff');
        $student = $this->createAcademyMember($academy, 'student');

        $ticket = $this->createTicket($academy, $student, [
            'subject' => 'Quiz Reset Request',
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        app(TenantContext::class)->setAcademy($academy);

        // Transition: open -> in_progress
        Livewire::actingAs($staff)
            ->test(SupportTicketDetail::class, ['ticket' => (string) $ticket->getKey()])
            ->set('selectedStatus', SupportTicket::STATUS_IN_PROGRESS)
            ->call('updateStatus')
            ->assertDispatched('toast');

        $this->assertSame(SupportTicket::STATUS_IN_PROGRESS, $ticket->fresh()->status);

        // Transition: in_progress -> resolved (sets resolved_at and notifies student)
        Livewire::actingAs($staff)
            ->test(SupportTicketDetail::class, ['ticket' => (string) $ticket->getKey()])
            ->set('selectedStatus', SupportTicket::STATUS_RESOLVED)
            ->call('updateStatus')
            ->assertDispatched('toast');

        $ticket->refresh();
        $this->assertSame(SupportTicket::STATUS_RESOLVED, $ticket->status);
        $this->assertNotNull($ticket->resolved_at);

        // Student receives status notification
        $studentNotification = AcademyNotification::query()
            ->where('academy_id', $academy->getKey())
            ->where('target', (string) $student->getKey())
            ->where('category', 'support')
            ->where('title', 'Support Ticket Status Updated')
            ->first();

        $this->assertNotNull($studentNotification);
        $this->assertSame('student', $studentNotification->audience);

        // Transition: resolved -> closed
        Livewire::actingAs($staff)
            ->test(SupportTicketDetail::class, ['ticket' => (string) $ticket->getKey()])
            ->set('selectedStatus', SupportTicket::STATUS_CLOSED)
            ->call('updateStatus')
            ->assertDispatched('toast');

        $this->assertSame(SupportTicket::STATUS_CLOSED, $ticket->fresh()->status);

        // Invalid status validation
        $service = app(SupportTicketService::class);
        $this->expectException(ValidationException::class);
        $service->updateStatus($ticket, 'non_existent_status', $staff);
    }

    public function test_support_staff_can_reply_publicly_and_add_internal_notes(): void
    {
        $academy = Academy::factory()->create(['slug' => 'support-reply-acad']);
        $staff = $this->createAcademyMember($academy, 'support_staff');
        $student = $this->createAcademyMember($academy, 'student');

        $ticket = $this->createTicket($academy, $student, [
            'subject' => 'Help with assignment rubric',
        ]);

        app(TenantContext::class)->setAcademy($academy);

        // Post public reply
        Livewire::actingAs($staff)
            ->test(SupportTicketDetail::class, ['ticket' => (string) $ticket->getKey()])
            ->set('replyMessage', 'Hello! We have reviewed your request and updated the rubric link.')
            ->set('isInternalNote', false)
            ->call('sendReply')
            ->assertDispatched('toast');

        $publicMessage = SupportTicketMessage::where('support_ticket_id', $ticket->getKey())
            ->where('is_internal', false)
            ->first();

        $this->assertNotNull($publicMessage);
        $this->assertSame('Hello! We have reviewed your request and updated the rubric link.', $publicMessage->message);
        $this->assertFalse($publicMessage->is_internal);

        // Student received notification for staff public reply
        $this->assertTrue(AcademyNotification::query()
            ->where('target', (string) $student->getKey())
            ->where('category', 'support')
            ->where('title', 'like', 'New Reply to Your Ticket%')
            ->exists());

        // Post internal note
        Livewire::actingAs($staff)
            ->test(SupportTicketDetail::class, ['ticket' => (string) $ticket->getKey()])
            ->set('replyMessage', 'Internal Note: Student had an old cookie session, verified in backend logs.')
            ->set('isInternalNote', true)
            ->call('sendReply')
            ->assertDispatched('toast');

        $internalMessage = SupportTicketMessage::where('support_ticket_id', $ticket->getKey())
            ->where('is_internal', true)
            ->first();

        $this->assertNotNull($internalMessage);
        $this->assertSame('Internal Note: Student had an old cookie session, verified in backend logs.', $internalMessage->message);
        $this->assertTrue($internalMessage->is_internal);

        // Assert audit logs for public reply and internal note
        $this->assertTrue(AuditLog::query()
            ->where('action', 'ticket.reply_added')
            ->where('subject_id', (string) $ticket->getKey())
            ->exists());

        $this->assertTrue(AuditLog::query()
            ->where('action', 'ticket.internal_note_added')
            ->where('subject_id', (string) $ticket->getKey())
            ->exists());
    }

    public function test_students_viewing_ticket_never_see_internal_notes(): void
    {
        $academy = Academy::factory()->create(['slug' => 'support-iso-acad']);
        $staff = $this->createAcademyMember($academy, 'support_staff');
        $student = $this->createAcademyMember($academy, 'student');

        $ticket = $this->createTicket($academy, $student, [
            'subject' => 'Confidential Inquiry',
        ]);

        $service = app(SupportTicketService::class);
        $service->addReply($ticket, $staff, 'Public staff response to student', isInternal: false);
        $service->addReply($ticket, $staff, 'TOP SECRET INTERNAL AUDIT NOTE', isInternal: true);

        // When queried with scopePublicOnly (the student-facing message scope)
        $publicMessages = SupportTicketMessage::query()
            ->where('support_ticket_id', $ticket->getKey())
            ->publicOnly()
            ->get();

        $this->assertCount(1, $publicMessages);
        $this->assertSame('Public staff response to student', $publicMessages->first()->message);
        $this->assertFalse($publicMessages->pluck('message')->contains('TOP SECRET INTERNAL AUDIT NOTE'));

        // Querying all messages without internal notes filter shows 2, but internal is strictly tagged
        $allMessages = SupportTicketMessage::where('support_ticket_id', $ticket->getKey())->get();
        $this->assertCount(2, $allMessages);
        $this->assertTrue($allMessages->where('is_internal', true)->isNotEmpty());
    }

    public function test_support_staff_blocked_from_admin_routes(): void
    {
        $academy = Academy::factory()->create(['slug' => 'support-guards-acad']);
        $staff = $this->createAcademyMember($academy, 'support_staff');

        app(TenantContext::class)->setAcademy($academy);

        // Blocked from admin courses
        $this->actingAs($staff)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('admin.courses.index'))
            ->assertStatus(403);

        // Blocked from admin enrollments
        $this->actingAs($staff)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('admin.enrollments.index'))
            ->assertStatus(403);

        // Blocked from admin academy settings
        $this->actingAs($staff)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('admin.academy.settings'))
            ->assertStatus(403);

        // Blocked from admin trainers
        $this->actingAs($staff)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('admin.trainers.index'))
            ->assertStatus(403);
    }

    public function test_students_blocked_from_support_staff_routes(): void
    {
        $academy = Academy::factory()->create(['slug' => 'student-block-acad']);
        $student = $this->createAcademyMember($academy, 'student');
        $ticket = $this->createTicket($academy, $student);

        app(TenantContext::class)->setAcademy($academy);

        // Student blocked from support dashboard
        $this->actingAs($student)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('support.dashboard'))
            ->assertStatus(403);

        // Student blocked from support tickets index
        $this->actingAs($student)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('support.tickets.index'))
            ->assertStatus(403);

        // Student blocked from support ticket detail staff view
        $this->actingAs($student)
            ->withSession([TenantContext::SESSION_KEY => $academy->getKey()])
            ->get(route('support.tickets.show', $ticket))
            ->assertStatus(403);
    }

    public function test_notification_and_audit_boundaries_and_no_cross_user_access(): void
    {
        $academy = Academy::factory()->create(['slug' => 'notif-boundary-acad']);
        $staff1 = $this->createAcademyMember($academy, 'support_staff');
        $staff2 = $this->createAcademyMember($academy, 'support_staff');
        $student = $this->createAcademyMember($academy, 'student');

        // Notification targeted to staff1
        $staffNotif = AcademyNotification::create([
            'academy_id' => $academy->getKey(),
            'audience' => 'staff',
            'target' => (string) $staff1->getKey(),
            'category' => 'support',
            'title' => 'Assigned Ticket',
            'message' => 'Staff 1 only message',
            'created_by' => $staff2->getKey(),
        ]);

        // Notification targeted to student
        $studentNotif = AcademyNotification::create([
            'academy_id' => $academy->getKey(),
            'audience' => 'student',
            'target' => (string) $student->getKey(),
            'category' => 'support',
            'title' => 'Ticket Resolved',
            'message' => 'Student only message',
            'created_by' => $staff1->getKey(),
        ]);

        // Scoped target query boundary: student only sees their own notifications
        $studentVisible = AcademyNotification::query()
            ->where('academy_id', $academy->getKey())
            ->where(function ($q) use ($student) {
                $q->where('target', (string) $student->getKey())
                    ->orWhere('audience', 'all');
            })
            ->pluck('id');

        $this->assertTrue($studentVisible->contains($studentNotif->id));
        $this->assertFalse($studentVisible->contains($staffNotif->id));

        // Scoped target query boundary: staff2 does not see staff1's targeted notification
        $staff2Visible = AcademyNotification::query()
            ->where('academy_id', $academy->getKey())
            ->where(function ($q) use ($staff2) {
                $q->where('target', (string) $staff2->getKey())
                    ->orWhere('audience', 'all');
            })
            ->pluck('id');

        $this->assertFalse($staff2Visible->contains($staffNotif->id));
    }
}
