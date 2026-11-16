<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_model_can_be_created_with_factory(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue(Str::isUuid((string) $user->id));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }

    public function test_user_can_be_assigned_a_role(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('student');

        $this->assertTrue($user->hasRole('student'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_user_has_correct_fillable_fields(): void
    {
        $user = new User;

        $this->assertSame(
            ['name', 'email', 'password', 'is_active', 'phone', 'suspended_at'],
            $user->getFillable(),
        );
    }
}
