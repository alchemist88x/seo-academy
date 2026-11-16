<?php

namespace App\Domain\Platform\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Trainers\Models\TrainerProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class LocalDemoAccountService
{
    /**
     * The five approved local demonstration accounts.
     * Note: platform@ and academy-admin@ both receive the standard 'admin' role.
     */
    public const DEMO_ACCOUNTS = [
        [
            'email' => 'platform@seo-academy.local',
            'name' => 'Platform Admin',
            'role' => 'admin',
        ],
        [
            'email' => 'academy-admin@seo-academy.local',
            'name' => 'Academy Admin',
            'role' => 'admin',
        ],
        [
            'email' => 'trainer@seo-academy.local',
            'name' => 'Lead Trainer',
            'role' => 'trainer',
        ],
        [
            'email' => 'student@seo-academy.local',
            'name' => 'Demo Student',
            'role' => 'student',
        ],
        [
            'email' => 'support@seo-academy.local',
            'name' => 'Support Staff',
            'role' => 'support_staff',
        ],
    ];

    /**
     * Restore or verify the five approved local demo accounts.
     *
     * @return array<int, array{name: string, email: string, role: string, status: string, password: ?string}>
     */
    public function restoreDemoAccounts(): array
    {
        if (app()->environment('production')) {
            throw new DomainException('Local demo accounts cannot be created or restored in a production environment.');
        }

        if (! app()->environment('local', 'development', 'testing')) {
            throw new DomainException(
                'Demo accounts can only be restored in local, development, or testing environments. Current: '.app()->environment()
            );
        }

        // 1. Ensure system roles exist
        $this->ensureRolesExist();

        // 2. Ensure single academy exists
        $academy = $this->ensureAcademyExists();

        // 3. Ensure installation markers exist
        $this->ensureInstallationMarkers($academy);

        // 4. Idempotently create or verify each demo account
        $results = [];

        foreach (self::DEMO_ACCOUNTS as $def) {
            $results[] = $this->restoreSingleAccount($def, $academy);
        }

        return $results;
    }

    /**
     * Generate cryptographically secure password with safe characters
     * that avoid shell escaping, terminal corruption, and copy-paste errors.
     */
    public static function generateCleanSecurePassword(int $length = 16): string
    {
        $lower = 'abcdefghjkmnpqrstuvwxyz';
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits = '23456789';
        $symbols = '!@#%+=_-';
        $chars = $lower.$upper.$digits.$symbols;
        $maxIndex = strlen($chars) - 1;

        $password = '';
        $password .= $lower[random_int(0, strlen($lower) - 1)];
        $password .= $upper[random_int(0, strlen($upper) - 1)];
        $password .= $digits[random_int(0, strlen($digits) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];

        for ($i = 4; $i < $length; $i++) {
            $password .= $chars[random_int(0, $maxIndex)];
        }

        return str_shuffle($password);
    }

    /**
     * Reset passwords for all five approved demo accounts.
     *
     * @return array<int, array{name: string, email: string, role: string, status: string, password: string}>
     */
    public function resetDemoAccountPasswords(): array
    {
        if (app()->environment('production')) {
            throw new DomainException('Demo account passwords cannot be reset in a production environment.');
        }

        if (! app()->environment('local', 'development', 'testing')) {
            throw new DomainException(
                'Demo accounts can only be reset in local, development, or testing environments. Current: '.app()->environment()
            );
        }

        // Ensure roles & academy exist first
        $this->ensureRolesExist();
        $academy = $this->ensureAcademyExists();
        $this->ensureInstallationMarkers($academy);

        return DB::transaction(function () use ($academy) {
            $results = [];

            foreach (self::DEMO_ACCOUNTS as $def) {
                $email = strtolower(trim($def['email']));
                $user = User::where('email', $email)->first();

                $plainPassword = self::generateCleanSecurePassword(16);
                $hash = Hash::make($plainPassword);

                if (! Hash::check($plainPassword, $hash)) {
                    throw new RuntimeException("Generated password verification failed for account {$email}.");
                }

                if (! $user) {
                    // Create account if absent
                    $user = User::create([
                        'name' => $def['name'],
                        'email' => $email,
                        'password' => $hash,
                        'is_active' => true,
                        'email_verified_at' => now(),
                    ]);
                    $user->assignRole($def['role']);
                    $user->academies()->attach($academy->id, [
                        'id' => (string) Str::uuid(),
                        'role' => $def['role'],
                        'status' => 'active',
                        'created_at' => now(),
                    ]);
                    $this->ensureProfile($user, $def['role'], $academy);
                    $status = 'Created with New Password';
                } else {
                    // Update password, ensure active and email verified
                    $user->password = $hash;
                    $user->is_active = true;
                    if (! $user->email_verified_at) {
                        $user->email_verified_at = now();
                    }
                    $user->save();

                    // Ensure role and academy membership
                    $user->syncRoles([$def['role']]);
                    $user->academies()->syncWithoutDetaching([
                        $academy->id => [
                            'id' => (string) Str::uuid(),
                            'role' => $def['role'],
                            'status' => 'active',
                        ],
                    ]);
                    $this->ensureProfile($user, $def['role'], $academy);
                    $status = 'Password Reset';
                }

                $results[] = [
                    'name' => $user->name,
                    'email' => $email,
                    'role' => $def['role'],
                    'status' => $status,
                    'password' => $plainPassword,
                ];
            }

            return $results;
        });
    }

    /**
     * Ensure the 4 approved Spatie system roles exist.
     */
    protected function ensureRolesExist(): void
    {
        $roleSeeder = new RoleSeeder;
        $roleSeeder->run();
    }

    /**
     * Ensure the single local academy exists.
     */
    protected function ensureAcademyExists(): Academy
    {
        $academy = Academy::current() ?? Academy::first();

        if (! $academy) {
            $academy = Academy::create([
                'name' => 'SEO Academy',
                'slug' => 'seo-academy',
                'description' => 'Flagship SEO & Digital Marketing Academy',
                'support_email' => 'support@seo-academy.local',
                'timezone' => config('app.timezone', 'UTC'),
                'locale' => 'en',
                'is_active' => true,
                'settings' => [
                    'theme' => 'light',
                    'installed_at' => now()->toIso8601String(),
                ],
            ]);
        } elseif (! $academy->is_active) {
            $academy->update(['is_active' => true]);
        }

        return $academy;
    }

    /**
     * Ensure persistent installation markers are recorded.
     */
    protected function ensureInstallationMarkers(Academy $academy): void
    {
        if (Schema::hasTable('settings')) {
            DB::table('settings')->updateOrInsert(
                [
                    'group' => 'system',
                    'key' => 'installed_at',
                ],
                [
                    'id' => (string) Str::uuid(),
                    'academy_id' => $academy->id,
                    'value' => now()->toIso8601String(),
                    'type' => 'string',
                    'is_public' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $lockPath = storage_path('app/private/installed.lock');
        if (! file_exists($lockPath)) {
            @mkdir(dirname($lockPath), 0755, true);
            @file_put_contents($lockPath, json_encode([
                'driver' => config('database.default', 'sqlite'),
                'installed_at' => now()->toIso8601String(),
                'environment' => app()->environment(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * Idempotently restore a single demo account.
     *
     * @param  array{name: string, email: string, role: string}  $def
     * @return array{name: string, email: string, role: string, status: string, password: ?string}
     */
    protected function restoreSingleAccount(array $def, Academy $academy): array
    {
        $email = strtolower(trim($def['email']));
        $existing = User::where('email', $email)->first();

        if ($existing) {
            // Account already exists: strictly retain existing password
            $existing->is_active = true;
            $existing->save();

            // Ensure role is assigned
            $existing->syncRoles([$def['role']]);

            // Ensure academy membership
            $existing->academies()->syncWithoutDetaching([
                $academy->id => [
                    'id' => (string) Str::uuid(),
                    'role' => $def['role'],
                    'status' => 'active',
                ],
            ]);

            // Ensure required profile
            $this->ensureProfile($existing, $def['role'], $academy);

            return [
                'name' => $existing->name,
                'email' => $email,
                'role' => $def['role'],
                'status' => 'Existing (Password Retained)',
                'password' => null,
            ];
        }

        // Generate strong random initial password (16 characters)
        $plainPassword = Str::password(16);

        $user = User::create([
            'name' => $def['name'],
            'email' => $email,
            'password' => Hash::make($plainPassword),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->assignRole($def['role']);

        $user->academies()->attach($academy->id, [
            'id' => (string) Str::uuid(),
            'role' => $def['role'],
            'status' => 'active',
            'created_at' => now(),
        ]);

        $this->ensureProfile($user, $def['role'], $academy);

        return [
            'name' => $def['name'],
            'email' => $email,
            'role' => $def['role'],
            'status' => 'Created',
            'password' => $plainPassword,
        ];
    }

    /**
     * Ensure student, trainer, or support profiles exist.
     */
    protected function ensureProfile(User $user, string $role, Academy $academy): void
    {
        if ($role === 'student') {
            StudentProfile::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'academy_id' => $academy->id,
                ],
                [
                    'student_code' => 'STU-DEMO-001',
                    'batch' => 'Cohort 2026-A',
                    'enrolled_at' => now()->toDateString(),
                    'level' => 'Beginner',
                    'active' => true,
                    'notes' => 'Demonstration student profile.',
                ]
            );
        } elseif ($role === 'trainer') {
            TrainerProfile::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'academy_id' => $academy->id,
                ],
                [
                    'specialty' => 'Technical SEO & Assessment Grading',
                    'bio' => 'Lead Trainer demonstration account for course evaluation and instruction.',
                    'active' => true,
                ]
            );
        }
        // Support staff members do not have a dedicated profile table;
        // their access is established through role 'support_staff' and active academy membership.
    }
}
