<?php

namespace App\Domain\Trainers\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Academies\Models\AcademyUser;
use App\Domain\Platform\Concerns\RecordsAuditLog;
use App\Domain\Trainers\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
        $query = TrainerProfile::query()
            ->forAcademy($academy)
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

        if (array_key_exists('active', $filters) && $filters['active'] !== '' && $filters['active'] !== null) {
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
}
