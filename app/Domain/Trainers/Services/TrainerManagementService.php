<?php

namespace App\Domain\Trainers\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Academies\Models\AcademyUser;
use App\Domain\Courses\Models\Course;
use App\Domain\Platform\Concerns\RecordsAuditLog;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Trainers\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class TrainerManagementService
{
    use RecordsAuditLog;

    public function listForAcademy(Academy $academy, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $status = strtolower(trim((string) ($filters['status'] ?? '')));

        $query = match ($status) {
            'deleted' => TrainerProfile::onlyTrashed(),
            'all' => TrainerProfile::withTrashed(),
            default => TrainerProfile::query(),
        };

        $query->forAcademy($academy)
            ->with('user');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('specialty', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($status === 'active') {
            $query->where('active', true);
        } elseif (in_array($status, ['inactive', 'suspended'], true)) {
            $query->where('active', false);
        } elseif (array_key_exists('active', $filters) && $filters['active'] !== '' && $filters['active'] !== null) {
            $active = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($active !== null) {
                $query->where('active', $active);
            }
        }

        return $query->latest()->paginate($perPage);
    }

    public function createOrAttach(Academy $academy, array $data, User $actor): TrainerProfile
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:8'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $email = Str::lower(trim($validated['email']));

        return DB::transaction(function () use ($academy, $validated, $email, $actor): TrainerProfile {
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

            $existingProfile = TrainerProfile::query()
                ->where('academy_id', $academy->getKey())
                ->where('user_id', $user->getKey())
                ->first();

            if ($existingProfile !== null) {
                throw ValidationException::withMessages([
                    'email' => 'This user already has a trainer profile in this academy.',
                ]);
            }

            $membership = AcademyUser::query()
                ->where('academy_id', $academy->getKey())
                ->where('user_id', $user->getKey())
                ->first();

            if ($membership === null) {
                AcademyUser::create([
                    'academy_id' => $academy->getKey(),
                    'user_id' => $user->getKey(),
                    'role' => 'trainer',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $membership->update([
                    'role' => 'trainer',
                    'status' => 'active',
                    'suspended_at' => null,
                    'updated_at' => now(),
                ]);
            }

            Role::firstOrCreate(['name' => 'trainer', 'guard_name' => 'web']);
            if (! $user->hasRole('trainer')) {
                $user->assignRole('trainer');
            }

            $profile = TrainerProfile::create([
                'user_id' => $user->getKey(),
                'academy_id' => $academy->getKey(),
                'specialty' => $validated['specialty'] ?? null,
                'bio' => $validated['bio'] ?? null,
                'active' => true,
            ]);

            $this->recordAudit(
                $academy,
                $actor,
                'trainer.created',
                TrainerProfile::class,
                $profile->getKey(),
                [
                    'trainer_user_id' => $user->getKey(),
                    'email' => $user->email,
                ],
            );

            return $profile->load('user');
        });
    }

    public function update(TrainerProfile $profile, array $data, User $actor): TrainerProfile
    {
        if (empty($data['name']) && (isset($data['first_name']) || isset($data['last_name']))) {
            $firstName = trim((string) ($data['first_name'] ?? ''));
            $lastName = trim((string) ($data['last_name'] ?? ''));
            $data['name'] = trim("{$firstName} {$lastName}") ?: ($profile->user?->name ?? '');
        }

        $validated = Validator::make($data, [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($profile->user_id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:8'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        return DB::transaction(function () use ($profile, $validated, $data, $actor): TrainerProfile {
            $user = $profile->user;
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
                if ($userUpdates !== []) {
                    $user->update($userUpdates);
                }
            }

            $profileUpdates = array_intersect_key($validated, array_flip(['specialty', 'bio']));
            if (array_key_exists('specialty', $data) && ! isset($profileUpdates['specialty'])) {
                $profileUpdates['specialty'] = $data['specialty'] !== '' ? $data['specialty'] : null;
            }
            if (array_key_exists('bio', $data) && ! isset($profileUpdates['bio'])) {
                $profileUpdates['bio'] = $data['bio'] !== '' ? $data['bio'] : null;
            }

            if ($profileUpdates !== []) {
                $profile->update($profileUpdates);
            }

            $this->recordAudit(
                $profile->academy,
                $actor,
                'trainer.updated',
                TrainerProfile::class,
                $profile->getKey(),
                ['updated_fields' => array_keys($validated)],
            );

            return $profile->fresh(['user']);
        });
    }

    public function setActive(TrainerProfile $profile, bool $active, User $actor): TrainerProfile
    {
        return DB::transaction(function () use ($profile, $active, $actor): TrainerProfile {
            $profile->update(['active' => $active]);

            $profile->user?->update([
                'is_active' => $active,
                'suspended_at' => $active ? null : ($profile->user->suspended_at ?? now()),
            ]);

            AcademyUser::query()
                ->where('academy_id', $profile->academy_id)
                ->where('user_id', $profile->user_id)
                ->update([
                    'status' => $active ? 'active' : 'suspended',
                    'suspended_at' => $active ? null : now(),
                    'updated_at' => now(),
                ]);

            $this->recordAudit(
                $profile->academy,
                $actor,
                $active ? 'trainer.activated' : 'trainer.suspended',
                TrainerProfile::class,
                $profile->getKey(),
                [
                    'trainer_user_id' => $profile->user_id,
                    'active' => $active,
                ],
            );

            return $profile->fresh(['user']);
        });
    }

    /**
     * Compute related record counts and associated records for trainer deletion.
     *
     * @return array{assigned_courses: array<int, array{id: string, title: string, status: string}>, assigned_courses_count: int, evaluations_count: int, result_publications_count: int}
     */
    public function getImpactSummary(TrainerProfile $profile): array
    {
        $userId = $profile->user_id;

        $assignedCourses = Course::query()
            ->whereHas('trainers', function (Builder $q) use ($userId): void {
                $q->where('users.id', $userId);
            })
            ->select(['id', 'title', 'status'])
            ->get()
            ->map(fn (Course $course) => [
                'id' => (string) $course->getKey(),
                'title' => (string) $course->title,
                'status' => (string) $course->status,
            ])
            ->values()
            ->all();

        $evaluationsCount = DB::table('assignment_evaluations')->where('evaluator_id', $userId)->count();
        $resultPublicationsCount = DB::table('assessment_result_publication_events')->where('published_by', $userId)->count();

        return [
            'assigned_courses' => $assignedCourses,
            'assigned_courses_count' => count($assignedCourses),
            'evaluations_count' => $evaluationsCount,
            'result_publications_count' => $resultPublicationsCount,
        ];
    }

    /**
     * Determine whether the user retains other active roles or profiles in the academy.
     */
    protected function userHasOtherActiveRolesOrProfiles(User $user, Academy $academy, string $ignoringProfileType): bool
    {
        if ($user->hasRole('admin') || $user->hasRole('support_staff')) {
            return true;
        }

        if ($ignoringProfileType === 'trainer') {
            return StudentProfile::query()
                ->where('academy_id', $academy->getKey())
                ->where('user_id', $user->getKey())
                ->whereNull('deleted_at')
                ->where('active', true)
                ->exists();
        }

        if ($ignoringProfileType === 'student') {
            return TrainerProfile::query()
                ->where('academy_id', $academy->getKey())
                ->where('user_id', $user->getKey())
                ->whereNull('deleted_at')
                ->where('active', true)
                ->exists();
        }

        return false;
    }

    /**
     * Safely delete a trainer profile with authentication deactivation.
     * Preserves assigned courses, historical authorship, evaluations, and grade publications.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function delete(TrainerProfile $profile, User $actor): void
    {
        if (! $actor->is_active || ! $actor->hasRole('admin')) {
            throw new AuthorizationException('Unauthorized: Only administrators may delete trainers.');
        }

        if ($profile->user_id === $actor->getKey()) {
            throw ValidationException::withMessages([
                'trainer' => 'You cannot delete your own trainer account.',
            ]);
        }

        // Idempotency: If already deleted, nothing to do
        if ($profile->trashed()) {
            return;
        }

        DB::transaction(function () use ($profile, $actor): void {
            $lockedProfile = TrainerProfile::withTrashed()->lockForUpdate()->find($profile->getKey());
            if ($lockedProfile === null || $lockedProfile->trashed()) {
                return;
            }

            $academy = $lockedProfile->academy;
            $userId = $lockedProfile->user_id;
            $user = $lockedProfile->user;

            if ($user !== null && $user->hasRole('admin')) {
                $otherAdminsCount = User::role('admin')
                    ->where('is_active', true)
                    ->where('id', '!=', $user->getKey())
                    ->lockForUpdate()
                    ->count();

                if ($otherAdminsCount === 0) {
                    throw ValidationException::withMessages([
                        'trainer' => 'Cannot delete this trainer account because it is the last active administrator.',
                    ]);
                }
            }

            $academyUser = AcademyUser::query()
                ->where('academy_id', $lockedProfile->academy_id)
                ->where('user_id', $userId)
                ->first();

            // Capture pre-deletion state snapshot if not already present
            if ($lockedProfile->pre_deletion_state === null) {
                $hasOtherActiveRoles = $user !== null && $academy !== null
                    ? $this->userHasOtherActiveRolesOrProfiles($user, $academy, 'trainer')
                    : false;

                $wasActive = $user !== null ? (bool) $user->is_active : false;
                $willDeactivateUser = (! $hasOtherActiveRoles) && $wasActive;

                $lockedProfile->pre_deletion_state = [
                    'user_is_active' => $user !== null ? (bool) $user->is_active : true,
                    'user_suspended_at' => $user?->suspended_at?->format('Y-m-d H:i:s'),
                    'academy_user_status' => (string) ($academyUser?->status ?? 'active'),
                    'academy_user_suspended_at' => $academyUser?->suspended_at?->format('Y-m-d H:i:s'),
                    'profile_active' => (bool) $lockedProfile->active,
                    'deactivated_user' => $willDeactivateUser,
                    'has_other_active_roles' => $hasOtherActiveRoles,
                    'captured_at' => now()->format('Y-m-d H:i:s'),
                ];
                $lockedProfile->active = false;
                $lockedProfile->save();
            }

            // Soft-delete profile
            $lockedProfile->delete();

            // Handle user and membership deactivation only if no other active roles exist
            $snapshot = $lockedProfile->pre_deletion_state;
            $shouldDeactivate = (bool) ($snapshot['deactivated_user'] ?? false);

            if ($shouldDeactivate && $user !== null) {
                $user->is_active = false;
                $user->suspended_at = now();
                $user->save();

                if ($academyUser !== null) {
                    $academyUser->status = 'suspended';
                    $academyUser->suspended_at = now();
                    $academyUser->save();
                }
            }

            if ($academy !== null) {
                $this->recordAudit(
                    $academy,
                    $actor,
                    'trainer.deleted',
                    TrainerProfile::class,
                    $lockedProfile->getKey(),
                    [
                        'user_id' => $userId,
                        'user_email' => $user?->email,
                    ],
                );
            }
        });
    }

    /**
     * Restore a soft-deleted trainer profile and reactivate login access.
     *
     * @throws AuthorizationException
     */
    public function restore(string $profileId, User $actor): TrainerProfile
    {
        if (! $actor->is_active || ! $actor->hasRole('admin')) {
            throw new AuthorizationException('Unauthorized: Only administrators may restore trainers.');
        }

        return DB::transaction(function () use ($profileId, $actor): TrainerProfile {
            $profile = TrainerProfile::withTrashed()
                ->lockForUpdate()
                ->with(['user', 'academy'])
                ->findOrFail($profileId);

            if (! $profile->trashed()) {
                return $profile;
            }

            $profile->restore();

            $snapshot = $profile->pre_deletion_state;
            $user = $profile->user;
            $academy = $profile->academy;

            if ($snapshot !== null) {
                $origUserIsActive = (bool) ($snapshot['user_is_active'] ?? true);
                $origUserSuspendedAt = ! empty($snapshot['user_suspended_at'])
                    ? Carbon::parse($snapshot['user_suspended_at'])
                    : null;
                $origAcademyStatus = (string) ($snapshot['academy_user_status'] ?? 'active');
                $origAcademySuspendedAt = ! empty($snapshot['academy_user_suspended_at'])
                    ? Carbon::parse($snapshot['academy_user_suspended_at'])
                    : null;
                $origProfileActive = (bool) ($snapshot['profile_active'] ?? true);
                $deactivatedUser = (bool) ($snapshot['deactivated_user'] ?? false);

                // Restore profile active state and reset snapshot
                $profile->active = $origProfileActive;
                $profile->pre_deletion_state = null;
                $profile->save();

                // Restore user authentication and membership
                if ($user !== null) {
                    if ($deactivatedUser) {
                        $user->is_active = $origUserIsActive;
                        $user->suspended_at = $origUserSuspendedAt;
                        $user->save();
                    } elseif (! $origUserIsActive) {
                        // User was already suspended prior to deletion -> preserve suspension
                        $user->is_active = false;
                        $user->suspended_at = $origUserSuspendedAt ?? $user->suspended_at;
                        $user->save();
                    }
                }

                if ($academy !== null) {
                    if ($deactivatedUser) {
                        AcademyUser::query()
                            ->where('academy_id', $academy->getKey())
                            ->where('user_id', $profile->user_id)
                            ->update([
                                'status' => $origAcademyStatus,
                                'suspended_at' => $origAcademySuspendedAt,
                                'updated_at' => now(),
                            ]);
                    } elseif ($origAcademyStatus === 'suspended') {
                        AcademyUser::query()
                            ->where('academy_id', $academy->getKey())
                            ->where('user_id', $profile->user_id)
                            ->update([
                                'status' => 'suspended',
                                'suspended_at' => $origAcademySuspendedAt,
                                'updated_at' => now(),
                            ]);
                    }
                }
            } else {
                // Fallback for legacy soft-deleted profiles without snapshot
                $profile->active = true;
                $profile->save();

                if ($user !== null) {
                    $user->is_active = true;
                    $user->suspended_at = null;
                    $user->save();
                }

                if ($academy !== null) {
                    AcademyUser::query()
                        ->where('academy_id', $academy->getKey())
                        ->where('user_id', $profile->user_id)
                        ->update([
                            'status' => 'active',
                            'suspended_at' => null,
                            'updated_at' => now(),
                        ]);
                }
            }

            if ($academy !== null) {
                $this->recordAudit(
                    $academy,
                    $actor,
                    'trainer.restored',
                    TrainerProfile::class,
                    $profile->getKey(),
                    [
                        'user_id' => $profile->user_id,
                        'user_email' => $user?->email,
                    ],
                );
            }

            return $profile->fresh(['user', 'academy']);
        });
    }
}
