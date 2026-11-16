<?php

namespace Tests\Feature\Operations;

use App\Domain\Academies\Models\Academy;
use App\Domain\Notifications\Models\AcademyNotification;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Services\NotificationDispatcherService;
use App\Livewire\Notifications\NotificationCenter;
use App\Livewire\Notifications\NotificationPreferences;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private Academy $academy;

    private User $admin;

    private User $student1;

    private User $student2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->academy = Academy::factory()->create();

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');
        $this->admin->academies()->attach($this->academy->id, ['id' => (string) Str::uuid(), 'role' => 'admin', 'status' => 'active']);

        $this->student1 = User::factory()->create(['name' => 'Student Alice', 'email' => 'alice@test.local', 'is_active' => true]);
        $this->student1->assignRole('student');
        $this->student1->academies()->attach($this->academy->id, ['id' => (string) Str::uuid(), 'role' => 'student', 'status' => 'active']);

        $this->student2 = User::factory()->create(['name' => 'Student Bob', 'email' => 'bob@test.local', 'is_active' => true]);
        $this->student2->assignRole('student');
        $this->student2->academies()->attach($this->academy->id, ['id' => (string) Str::uuid(), 'role' => 'student', 'status' => 'active']);
    }

    /**
     * Test notification isolation: users only see notifications targeted to them or their audience.
     */
    public function test_user_notification_isolation(): void
    {
        // Notification for Alice
        AcademyNotification::create([
            'academy_id' => $this->academy->id,
            'audience' => 'target',
            'target' => (string) $this->student1->id,
            'category' => 'enrollment',
            'title' => 'Alice Private Notice',
            'message' => 'Notice for Alice only.',
            'created_at' => now(),
        ]);

        // Notification for Bob
        AcademyNotification::create([
            'academy_id' => $this->academy->id,
            'audience' => 'target',
            'target' => (string) $this->student2->id,
            'category' => 'enrollment',
            'title' => 'Bob Private Notice',
            'message' => 'Notice for Bob only.',
            'created_at' => now(),
        ]);

        // Alice views notification center
        $this->actingAs($this->student1);
        Livewire::test(NotificationCenter::class)
            ->assertSee('Alice Private Notice')
            ->assertDontSee('Bob Private Notice');

        // Bob views notification center
        $this->actingAs($this->student2);
        Livewire::test(NotificationCenter::class)
            ->assertSee('Bob Private Notice')
            ->assertDontSee('Alice Private Notice');
    }

    /**
     * Test mark single notification as read and mark all as read.
     */
    public function test_read_and_mark_all_read_functionality(): void
    {
        $notif1 = AcademyNotification::create([
            'academy_id' => $this->academy->id,
            'audience' => 'target',
            'target' => (string) $this->student1->id,
            'category' => 'course',
            'title' => 'Course Update 1',
            'message' => 'Content of update 1',
            'created_at' => now()->subMinutes(10),
        ]);

        $notif2 = AcademyNotification::create([
            'academy_id' => $this->academy->id,
            'audience' => 'target',
            'target' => (string) $this->student1->id,
            'category' => 'course',
            'title' => 'Course Update 2',
            'message' => 'Content of update 2',
            'created_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($this->student1);

        $component = Livewire::test(NotificationCenter::class)
            ->assertSee('Course Update 1')
            ->assertSee('Course Update 2')
            ->assertViewHas('unreadCount', 2);

        // Mark notif1 as read
        $component->call('markAsRead', $notif1->id);

        $this->assertTrue($notif1->isReadBy($this->student1));
        $this->assertFalse($notif2->isReadBy($this->student1));

        // Mark all as read
        $component->call('markAllAsRead');

        $this->assertTrue($notif1->isReadBy($this->student1));
        $this->assertTrue($notif2->isReadBy($this->student1));
    }

    /**
     * Test preference enforcement: disabling email suppresses email dispatch.
     */
    public function test_preference_enforcement_email_suppression(): void
    {
        Mail::fake();

        $dispatcher = app(NotificationDispatcherService::class);

        // Turn off email for 'support'
        $dispatcher->setPreference($this->student1, 'email', 'support', false);

        $this->assertFalse($dispatcher->isChannelEnabled($this->student1, 'email', 'support'));
        $this->assertTrue($dispatcher->isChannelEnabled($this->student1, 'in_app', 'support'));

        $dispatcher->send(
            recipient: $this->student1,
            category: 'support',
            title: 'Support Ticket Update',
            message: 'Your ticket has been updated.',
        );

        // In-app notification created
        $this->assertDatabaseHas('academy_notifications', [
            'target' => (string) $this->student1->id,
            'category' => 'support',
            'title' => 'Support Ticket Update',
        ]);

        // Email NOT sent because preference disabled it
        Mail::assertNothingSent();
    }

    /**
     * Test critical system notifications cannot be disabled by users.
     */
    public function test_critical_notifications_cannot_be_disabled(): void
    {
        $dispatcher = app(NotificationDispatcherService::class);

        // Attempt to disable system notifications
        $dispatcher->setPreference($this->student1, 'in_app', 'system', false);
        $dispatcher->setPreference($this->student1, 'email', 'system', false);

        // Must still be reported as enabled
        $this->assertTrue($dispatcher->isChannelEnabled($this->student1, 'in_app', 'system'));
        $this->assertTrue($dispatcher->isChannelEnabled($this->student1, 'email', 'system'));
    }

    /**
     * Test deduplication: repeated notifications within window are suppressed.
     */
    public function test_duplicate_notification_prevention(): void
    {
        $dispatcher = app(NotificationDispatcherService::class);

        $first = $dispatcher->send(
            recipient: $this->student1,
            category: 'enrollment',
            title: 'Enrollment Activated',
            message: 'You have been enrolled in SEO Masterclass.',
            dedupWindowSeconds: 300,
        );
        $this->assertNotNull($first);

        // Second immediate call with identical title & category
        $second = $dispatcher->send(
            recipient: $this->student1,
            category: 'enrollment',
            title: 'Enrollment Activated',
            message: 'You have been enrolled in SEO Masterclass.',
            dedupWindowSeconds: 300,
        );

        $this->assertNull($second, 'Duplicate notification within 300s window must be suppressed');

        $this->assertEquals(1, AcademyNotification::query()
            ->where('target', (string) $this->student1->id)
            ->where('title', 'Enrollment Activated')
            ->count());
    }

    /**
     * Test notification preferences Livewire toggle.
     */
    public function test_notification_preferences_livewire_component(): void
    {
        $this->actingAs($this->student1);

        Livewire::test(NotificationPreferences::class)
            ->assertSee('Notification Preferences')
            ->assertSee('Enrollments & Access')
            ->assertSee('Support & Help Desk')
            ->call('toggle', 'enrollment', 'email');

        $preference = NotificationPreference::where('user_id', $this->student1->id)
            ->where('category', 'enrollment')
            ->where('channel', 'email')
            ->first();

        $this->assertNotNull($preference);
        $this->assertFalse($preference->enabled);
    }
}
