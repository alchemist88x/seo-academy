<?php

namespace Tests\Feature\Identity;

use App\Domain\Academies\Models\Academy;
use App\Domain\Identity\Services\InvitationService;
use App\Domain\Tenants\Services\TenantContext;
use App\Livewire\Admin\MembersIndex;
use App\Mail\AcademyInvitationMail;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class InvitationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function createAcademyAdmin(Academy $academy): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');
        $user->academies()->attach($academy->getKey(), [
            'id' => (string) Str::uuid(),
            'role' => 'admin',
            'status' => 'active',
        ]);

        return $user;
    }

    public function test_invitation_stores_sha256_hash_and_sends_mailable(): void
    {
        Mail::fake();

        $academy = Academy::factory()->create(['slug' => 'inv-acad']);
        $admin = $this->createAcademyAdmin($academy);

        $rawToken = null;

        $invitation = app(InvitationService::class)->create($academy, [
            'email' => 'invitee@example.com',
            'name' => 'Invitee Person',
            'role' => 'student',
            'expires_in_days' => 7,
        ], $admin);

        Mail::assertSent(AcademyInvitationMail::class, function (AcademyInvitationMail $mail) use (&$rawToken, $invitation): bool {
            $rawToken = $mail->rawToken;

            return $mail->hasTo('invitee@example.com')
                && $mail->invitation->id === $invitation->id;
        });

        $this->assertNotNull($rawToken);
        $this->assertNotSame($rawToken, $invitation->token_hash);
        $this->assertSame(hash('sha256', $rawToken), $invitation->token_hash);
        $this->assertSame('pending', $invitation->status);
    }

    public function test_new_user_can_accept_invitation_and_single_use_is_enforced(): void
    {
        Mail::fake();

        $academy = Academy::factory()->create(['slug' => 'new-user-inv']);
        $admin = $this->createAcademyAdmin($academy);

        $rawToken = null;
        $invitation = app(InvitationService::class)->create($academy, [
            'email' => 'newbie@example.com',
            'name' => 'Newbie Student',
            'role' => 'student',
        ], $admin);

        Mail::assertSent(AcademyInvitationMail::class, function (AcademyInvitationMail $mail) use (&$rawToken): bool {
            $rawToken = $mail->rawToken;

            return true;
        });

        $this->get(route('invitations.accept', ['token' => $rawToken]))
            ->assertStatus(200)
            ->assertSee($academy->name)
            ->assertSee('newbie@example.com');

        $response = $this->post(route('invitations.accept.store', ['token' => $rawToken]), [
            'name' => 'Newbie Student',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated();

        $user = User::where('email', 'newbie@example.com')->firstOrFail();
        $this->assertTrue($user->belongsToAcademy($academy));
        $this->assertDatabaseHas('student_profiles', [
            'academy_id' => $academy->id,
            'user_id' => $user->id,
        ]);
        $this->assertSame('accepted', $invitation->fresh()->status);
        $this->assertNotNull($invitation->fresh()->accepted_at);

        // Single-use enforced: second attempt fails
        $this->post(route('logout'));
        $this->get(route('invitations.accept', ['token' => $rawToken]))
            ->assertStatus(200)
            ->assertSee('Invalid or Expired Invitation');

        $this->expectException(ValidationException::class);
        app(InvitationService::class)->accept($rawToken, [
            'name' => 'Replay Attempt',
            'password' => 'StrongPass123!',
        ]);
    }

    public function test_existing_user_can_accept_invitation_in_single_academy(): void
    {
        Mail::fake();

        $academy = Academy::factory()->create(['slug' => 'exist-inv']);
        $admin = $this->createAcademyAdmin($academy);

        $existingUser = User::factory()->create([
            'email' => 'existing.member@example.com',
            'is_active' => true,
        ]);

        $rawToken = null;
        app(InvitationService::class)->create($academy, [
            'email' => 'existing.member@example.com',
            'role' => 'trainer',
        ], $admin);

        Mail::assertSent(AcademyInvitationMail::class, function (AcademyInvitationMail $mail) use (&$rawToken): bool {
            $rawToken = $mail->rawToken;

            return true;
        });

        $response = $this->actingAs($existingUser)
            ->post(route('invitations.accept.store', ['token' => $rawToken]));

        $response->assertRedirect(route('trainer.dashboard'));
        $this->assertSame(1, User::where('email', 'existing.member@example.com')->count());
        $this->assertSame('trainer', $existingUser->fresh()->academyRole($academy));
        $this->assertDatabaseHas('trainer_profiles', [
            'academy_id' => $academy->id,
            'user_id' => $existingUser->id,
        ]);
    }

    public function test_expired_and_revoked_invitations_are_rejected(): void
    {
        Mail::fake();

        $academy = Academy::factory()->create(['slug' => 'exp-rev-acad']);
        $admin = $this->createAcademyAdmin($academy);
        $service = app(InvitationService::class);

        $expiredToken = null;
        $expiredInv = $service->create($academy, [
            'email' => 'expired@example.com',
            'role' => 'student',
        ], $admin);
        Mail::assertSent(AcademyInvitationMail::class, function (AcademyInvitationMail $mail) use (&$expiredToken, $expiredInv): bool {
            if ($mail->invitation->id === $expiredInv->id) {
                $expiredToken = $mail->rawToken;

                return true;
            }

            return false;
        });

        $expiredInv->update(['expires_at' => now()->subDay()]);
        $this->assertNull($service->findValidByToken($expiredToken));

        $revokedToken = null;
        $revokedInv = $service->create($academy, [
            'email' => 'revoked@example.com',
            'role' => 'student',
        ], $admin);
        Mail::assertSent(AcademyInvitationMail::class, function (AcademyInvitationMail $mail) use (&$revokedToken, $revokedInv): bool {
            if ($mail->invitation->id === $revokedInv->id) {
                $revokedToken = $mail->rawToken;

                return true;
            }

            return false;
        });

        $service->revoke($revokedInv, $admin);
        $this->assertSame('revoked', $revokedInv->fresh()->status);
        $this->assertNull($service->findValidByToken($revokedToken));

        try {
            $service->accept($expiredToken, ['name' => 'Exp', 'password' => 'Secret1234!']);
            $this->fail('Expected ValidationException for expired invitation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('token', $e->errors());
        }

        try {
            $service->accept($revokedToken, ['name' => 'Rev', 'password' => 'Secret1234!']);
            $this->fail('Expected ValidationException for revoked invitation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('token', $e->errors());
        }
    }

    public function test_non_admin_cannot_revoke_invitation(): void
    {
        Mail::fake();

        $academy = Academy::factory()->create(['slug' => 'inv-single']);
        $admin = $this->createAcademyAdmin($academy);

        $trainer = User::factory()->create(['is_active' => true]);
        $trainer->assignRole('trainer');
        $trainer->academies()->attach($academy->getKey(), [
            'id' => (string) Str::uuid(),
            'role' => 'trainer',
            'status' => 'active',
        ]);

        $invitation = app(InvitationService::class)->create($academy, [
            'email' => 'target@example.com',
            'role' => 'student',
        ], $admin);

        $this->assertFalse($trainer->can('revoke', $invitation));

        app(TenantContext::class)->setAcademy($academy);
        Livewire::actingAs($trainer)
            ->test(MembersIndex::class)
            ->assertForbidden();

        $this->assertSame('pending', $invitation->fresh()->status);
    }
}
