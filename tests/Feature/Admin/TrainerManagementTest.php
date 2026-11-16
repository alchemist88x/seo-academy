<?php

namespace Tests\Feature\Admin;

use App\Domain\Academies\Models\Academy;
use App\Domain\Tenants\Services\TenantContext;
use App\Domain\Trainers\Models\TrainerProfile;
use App\Livewire\Admin\TrainersIndex;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TrainerManagementTest extends TestCase
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

    public function test_admin_can_create_edit_and_activate_suspend_trainers(): void
    {
        $academy = Academy::factory()->create(['slug' => 'trainer-acad']);
        $admin = $this->createAcademyMember($academy, 'admin');

        $this->actingAs($admin)
            ->withSession([TenantContext::SESSION_KEY => $academy->id])
            ->get(route('admin.trainers.index'))
            ->assertStatus(200)
            ->assertSee('Trainers');

        app(TenantContext::class)->setAcademy($academy);

        // Create trainer
        Livewire::actingAs($admin)
            ->test(TrainersIndex::class)
            ->set('name', 'Tariq Trainer')
            ->set('email', 'tariq@example.com')
            ->set('phone', '+15550202')
            ->set('specialty', 'Technical SEO')
            ->set('bio', '10 years of crawl budget optimization.')
            ->call('saveTrainer')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $trainerUser = User::where('email', 'tariq@example.com')->firstOrFail();
        $trainerProfile = TrainerProfile::where('academy_id', $academy->id)
            ->where('user_id', $trainerUser->id)
            ->firstOrFail();

        $this->assertTrue($trainerProfile->active);
        $this->assertSame('Technical SEO', $trainerProfile->specialty);

        // Edit trainer
        Livewire::actingAs($admin)
            ->test(TrainersIndex::class)
            ->call('openEditModal', $trainerProfile->id)
            ->set('name', 'Tariq Updated')
            ->set('specialty', 'Link Building & PR')
            ->call('saveTrainer')
            ->assertHasNoErrors();

        $this->assertSame('Tariq Updated', $trainerUser->fresh()->name);
        $this->assertSame('Link Building & PR', $trainerProfile->fresh()->specialty);

        // Suspend trainer
        Livewire::actingAs($admin)
            ->test(TrainersIndex::class)
            ->call('toggleActive', $trainerProfile->id)
            ->assertDispatched('toast');

        $this->assertFalse($trainerProfile->fresh()->active);

        // Activate trainer
        Livewire::actingAs($admin)
            ->test(TrainersIndex::class)
            ->call('toggleActive', $trainerProfile->id);

        $this->assertTrue($trainerProfile->fresh()->active);
    }

    public function test_creating_trainer_reuses_existing_user_record(): void
    {
        $academy = Academy::factory()->create(['slug' => 'tr-acad']);
        $admin = $this->createAcademyMember($academy, 'admin');

        $existingUser = User::factory()->create([
            'name' => 'Global Trainer',
            'email' => 'global.trainer@example.com',
            'is_active' => true,
        ]);

        app(TenantContext::class)->setAcademy($academy);
        Livewire::actingAs($admin)
            ->test(TrainersIndex::class)
            ->set('name', 'Global Trainer')
            ->set('email', 'global.trainer@example.com')
            ->set('specialty', 'On-Page SEO')
            ->call('saveTrainer')
            ->assertHasNoErrors();

        $this->assertSame(1, User::where('email', 'global.trainer@example.com')->count());
        $this->assertSame(1, TrainerProfile::where('user_id', $existingUser->id)->count());
    }

    public function test_suspended_user_and_role_restrictions_return_403(): void
    {
        $academy = Academy::factory()->create(['slug' => 'tr-single']);

        $suspendedAdmin = $this->createAcademyMember($academy, 'admin', ['is_active' => false]);
        $trainer = $this->createAcademyMember($academy, 'trainer');
        $trainerProfile = TrainerProfile::create([
            'user_id' => $trainer->id,
            'academy_id' => $academy->id,
            'specialty' => 'SEO',
            'active' => true,
        ]);
        $support = $this->createAcademyMember($academy, 'support_staff');
        $student = $this->createAcademyMember($academy, 'student');

        $this->actingAs($suspendedAdmin)
            ->get(route('admin.trainers.index'))
            ->assertStatus(403);

        $this->assertFalse($support->can('update', $trainerProfile));

        foreach ([$trainer, $support, $student] as $user) {
            $this->actingAs($user)
                ->get(route('admin.trainers.index'))
                ->assertStatus(403);
        }
    }
}
