<?php

namespace App\Domain\Identity\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Academies\Models\AcademyUser;
use App\Domain\Platform\Concerns\RecordsAuditLog;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Trainers\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class MembershipManagementService
{
    use RecordsAuditLog;

    public const ACADEMY_ROLES = [
        'admin',
        'trainer',
        'support_staff',
        'student',
    ];

    public const VALID_STATUSES = [
        'active',
        'suspended',
        'revoked',
    ];

    public function listForAcademy(Academy $academy, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = AcademyUser::query()
            ->where('academy_id', $academy->getKey())
            ->with('user');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->whereHas('user', function (Builder $userQuery) use ($search): void {
                $userQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $role = trim((string) ($filters['role'] ?? ''));
        if ($role !== '') {
            $query->where('role', $role);
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function assignRole(AcademyUser $membership, string $role, User $actor): AcademyUser
    {
        Validator::make(
            ['role' => $role],
            ['role' => ['required', 'string', Rule::in(self::ACADEMY_ROLES)]],
        )->validate();

        return DB::transaction(function () use ($membership, $role, $actor): AcademyUser {
            $previousRole = $membership->role;

            $membership->update([
                'role' => $role,
                'updated_at' => now(),
            ]);

            $user = $membership->user;
            if ($user !== null) {
                Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
                $user->syncRoles([$role]);
            }

            $this->recordAudit(
                $membership->academy,
                $actor,
                'membership.role_assigned',
                AcademyUser::class,
                $membership->getKey(),
                [
                    'target_user_id' => $membership->user_id,
                    'previous_role' => $previousRole,
                    'role' => $role,
                ],
            );

            return $membership->fresh(['user']);
        });
    }

    public function setStatus(AcademyUser $membership, string $status, User $actor): AcademyUser
    {
        Validator::make(
            ['status' => $status],
            ['status' => ['required', 'string', Rule::in(self::VALID_STATUSES)]],
        )->validate();

        return DB::transaction(function () use ($membership, $status, $actor): AcademyUser {
            $previousStatus = $membership->status;

            $membership->update([
                'status' => $status,
                'suspended_at' => $status === 'suspended' ? now() : ($status === 'active' ? null : $membership->suspended_at),
                'updated_at' => now(),
            ]);

            if ($membership->user !== null) {
                $membership->user->update([
                    'is_active' => $status === 'active',
                    'suspended_at' => $status === 'active' ? null : ($membership->user->suspended_at ?? now()),
                ]);
            }

            $this->recordAudit(
                $membership->academy,
                $actor,
                'membership.status_updated',
                AcademyUser::class,
                $membership->getKey(),
                [
                    'target_user_id' => $membership->user_id,
                    'previous_status' => $previousStatus,
                    'status' => $status,
                ],
            );

            return $membership->fresh(['user']);
        });
    }

    public function revoke(AcademyUser $membership, User $actor): void
    {
        DB::transaction(function () use ($membership, $actor): void {
            $academy = $membership->academy;
            $membershipId = $membership->getKey();
            $targetUserId = $membership->user_id;
            $role = $membership->role;

            $this->recordAudit(
                $academy,
                $actor,
                'membership.revoked',
                AcademyUser::class,
                $membershipId,
                [
                    'target_user_id' => $targetUserId,
                    'role' => $role,
                ],
            );

            $membership->delete();
        });
    }

    public function createSupportStaff(Academy $academy, array $data, User $actor): AcademyUser
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:8'],
        ])->validate();

        $email = Str::lower(trim($validated['email']));

        return DB::transaction(function () use ($academy, $validated, $email, $actor): AcademyUser {
            $user = User::where('email', $email)->first();

            if ($user === null) {
                $user = User::create([
                    'name' => trim($validated['name']),
                    'email' => $email,
                    'phone' => $validated['phone'] ?? null,
                    'password' => $validated['password'] ?? Str::random(24),
                    'is_active' => true,
                ]);
            } elseif (! empty($validated['phone']) && empty($user->phone)) {
                $user->update(['phone' => $validated['phone']]);
            }

            $membership = AcademyUser::query()
                ->where('academy_id', $academy->getKey())
                ->where('user_id', $user->getKey())
                ->first();

            if ($membership === null) {
                $membership = AcademyUser::create([
                    'academy_id' => $academy->getKey(),
                    'user_id' => $user->getKey(),
                    'role' => 'support_staff',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $membership->update([
                    'role' => 'support_staff',
                    'status' => 'active',
                    'suspended_at' => null,
                    'updated_at' => now(),
                ]);
            }

            Role::firstOrCreate(['name' => 'support_staff', 'guard_name' => 'web']);
            if (! $user->hasRole('support_staff')) {
                $user->assignRole('support_staff');
            }

            $this->recordAudit(
                $academy,
                $actor,
                'support_staff.created',
                AcademyUser::class,
                $membership->getKey(),
                [
                    'target_user_id' => $user->getKey(),
                    'email' => $user->email,
                ],
            );

            return $membership->load('user');
        });
    }

    public function createUser(Academy $academy, array $data, User $actor): AcademyUser
    {
        if (empty($data['name']) && (! empty($data['first_name']) || ! empty($data['last_name']))) {
            $firstName = trim((string) ($data['first_name'] ?? ''));
            $lastName = trim((string) ($data['last_name'] ?? ''));
            $data['name'] = trim("{$firstName} {$lastName}");
        }

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(self::ACADEMY_ROLES)],
            'status' => ['nullable', 'string', Rule::in(self::VALID_STATUSES)],
        ])->validate();

        $email = Str::lower(trim($validated['email']));
        $role = $validated['role'];
        $status = $validated['status'] ?? 'active';
        $isActive = $status === 'active';

        return DB::transaction(function () use ($academy, $validated, $email, $role, $status, $isActive, $actor): AcademyUser {
            $user = User::where('email', $email)->first();

            if ($user === null) {
                $user = User::create([
                    'name' => trim($validated['name']),
                    'email' => $email,
                    'phone' => $validated['phone'] ?? null,
                    'password' => $validated['password'] ?? Str::random(24),
                    'is_active' => $isActive,
                    'suspended_at' => $isActive ? null : now(),
                ]);
            } else {
                $userUpdates = [];
                if (! empty($validated['phone']) && empty($user->phone)) {
                    $userUpdates['phone'] = $validated['phone'];
                }
                if (! $isActive && $user->is_active) {
                    $userUpdates['is_active'] = false;
                    $userUpdates['suspended_at'] = now();
                }
                if ($userUpdates !== []) {
                    $user->update($userUpdates);
                }
            }

            $membership = AcademyUser::query()
                ->where('academy_id', $academy->getKey())
                ->where('user_id', $user->getKey())
                ->first();

            if ($membership === null) {
                $membership = AcademyUser::create([
                    'academy_id' => $academy->getKey(),
                    'user_id' => $user->getKey(),
                    'role' => $role,
                    'status' => $status,
                    'suspended_at' => $isActive ? null : now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $membership->update([
                    'role' => $role,
                    'status' => $status,
                    'suspended_at' => $isActive ? null : now(),
                    'updated_at' => now(),
                ]);
            }

            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            if (! $user->hasRole($role)) {
                $user->syncRoles([$role]);
            }

            // Create role-specific profile if not present
            if ($role === 'student') {
                $existingProfile = StudentProfile::query()
                    ->where('academy_id', $academy->getKey())
                    ->where('user_id', $user->getKey())
                    ->first();

                if ($existingProfile === null) {
                    $studentCode = 'STD-'.strtoupper(Str::random(6));
                    StudentProfile::create([
                        'user_id' => $user->getKey(),
                        'academy_id' => $academy->getKey(),
                        'student_code' => $studentCode,
                        'enrolled_at' => now()->toDateString(),
                        'level' => 'Beginner',
                        'active' => $isActive,
                    ]);
                }
            } elseif ($role === 'trainer') {
                $existingProfile = TrainerProfile::query()
                    ->where('academy_id', $academy->getKey())
                    ->where('user_id', $user->getKey())
                    ->first();

                if ($existingProfile === null) {
                    TrainerProfile::create([
                        'user_id' => $user->getKey(),
                        'academy_id' => $academy->getKey(),
                        'active' => $isActive,
                    ]);
                }
            }

            $this->recordAudit(
                $academy,
                $actor,
                'user.created',
                AcademyUser::class,
                $membership->getKey(),
                [
                    'target_user_id' => $user->getKey(),
                    'email' => $user->email,
                    'role' => $role,
                ],
            );

            return $membership->load('user');
        });
    }

    public function updateUser(AcademyUser $membership, array $data, User $actor): AcademyUser
    {
        if (empty($data['name']) && (isset($data['first_name']) || isset($data['last_name']))) {
            $firstName = trim((string) ($data['first_name'] ?? ''));
            $lastName = trim((string) ($data['last_name'] ?? ''));
            $data['name'] = trim("{$firstName} {$lastName}") ?: ($membership->user?->name ?? '');
        }

        $validated = Validator::make($data, [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($membership->user_id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['sometimes', 'required', 'string', Rule::in(self::ACADEMY_ROLES)],
            'status' => ['sometimes', 'required', 'string', Rule::in(self::VALID_STATUSES)],
        ])->validate();

        return DB::transaction(function () use ($membership, $validated, $data, $actor): AcademyUser {
            $user = $membership->user;
            if ($user !== null) {
                $userUpdates = [];

                if (isset($validated['name'])) {
                    $userUpdates['name'] = $validated['name'];
                }
                if (isset($validated['email'])) {
                    $userUpdates['email'] = Str::lower(trim($validated['email']));
                }
                if (array_key_exists('phone', $data)) {
                    $userUpdates['phone'] = $data['phone'] !== '' ? $data['phone'] : null;
                }
                if (! empty($validated['password'])) {
                    $userUpdates['password'] = $validated['password'];
                }

                if (isset($validated['status'])) {
                    $isActive = $validated['status'] === 'active';
                    $userUpdates['is_active'] = $isActive;
                    $userUpdates['suspended_at'] = $isActive ? null : ($user->suspended_at ?? now());
                }

                if ($userUpdates !== []) {
                    $user->update($userUpdates);
                }
            }

            $membershipUpdates = [];
            if (isset($validated['role']) && $validated['role'] !== $membership->role) {
                $newRole = $validated['role'];
                $membershipUpdates['role'] = $newRole;

                if ($user !== null) {
                    Role::firstOrCreate(['name' => $newRole, 'guard_name' => 'web']);
                    $user->syncRoles([$newRole]);

                    if ($newRole === 'student' && ! StudentProfile::where('user_id', $user->id)->where('academy_id', $membership->academy_id)->exists()) {
                        StudentProfile::create([
                            'user_id' => $user->id,
                            'academy_id' => $membership->academy_id,
                            'student_code' => 'STD-'.strtoupper(Str::random(6)),
                            'enrolled_at' => now()->toDateString(),
                            'level' => 'Beginner',
                            'active' => true,
                        ]);
                    } elseif ($newRole === 'trainer' && ! TrainerProfile::where('user_id', $user->id)->where('academy_id', $membership->academy_id)->exists()) {
                        TrainerProfile::create([
                            'user_id' => $user->id,
                            'academy_id' => $membership->academy_id,
                            'active' => true,
                        ]);
                    }
                }
            }

            if (isset($validated['status'])) {
                $membershipUpdates['status'] = $validated['status'];
                $membershipUpdates['suspended_at'] = $validated['status'] === 'suspended' ? now() : null;
            }

            if ($membershipUpdates !== []) {
                $membershipUpdates['updated_at'] = now();
                $membership->update($membershipUpdates);
            }

            $this->recordAudit(
                $membership->academy,
                $actor,
                'user.updated',
                AcademyUser::class,
                $membership->getKey(),
                ['updated_fields' => array_keys($validated)],
            );

            return $membership->fresh(['user']);
        });
    }
}
