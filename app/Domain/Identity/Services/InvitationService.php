<?php

namespace App\Domain\Identity\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Academies\Models\AcademyUser;
use App\Domain\Identity\Models\Invitation;
use App\Domain\Platform\Concerns\RecordsAuditLog;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Trainers\Models\TrainerProfile;
use App\Mail\AcademyInvitationMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class InvitationService
{
    use RecordsAuditLog;

    public function create(Academy $academy, array $data, User $actor): Invitation
    {
        $validated = Validator::make($data, [
            'email' => ['required', 'string', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['required', 'string', Rule::in(MembershipManagementService::ACADEMY_ROLES)],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ])->validate();

        $email = Str::lower(trim($validated['email']));
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresInDays = (int) ($validated['expires_in_days'] ?? 7);

        $invitation = DB::transaction(function () use ($academy, $validated, $email, $tokenHash, $expiresInDays, $actor): Invitation {
            $invitation = Invitation::create([
                'academy_id' => $academy->getKey(),
                'invited_by' => $actor->getKey(),
                'email' => $email,
                'name' => isset($validated['name']) ? trim($validated['name']) : null,
                'role' => $validated['role'],
                'token_hash' => $tokenHash,
                'status' => 'pending',
                'expires_at' => now()->addDays($expiresInDays),
            ]);

            $this->recordAudit(
                $academy,
                $actor,
                'invitation.created',
                Invitation::class,
                $invitation->getKey(),
                [
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'expires_at' => $invitation->expires_at->toIso8601String(),
                ],
            );

            return $invitation;
        });

        Mail::to($invitation->email)->send(new AcademyInvitationMail($invitation, $rawToken, $academy));

        return $invitation;
    }

    public function findValidByToken(string $rawToken): ?Invitation
    {
        if (trim($rawToken) === '') {
            return null;
        }

        $tokenHash = hash('sha256', $rawToken);

        $invitation = Invitation::query()
            ->with('academy')
            ->where('token_hash', $tokenHash)
            ->first();

        if ($invitation === null || ! $invitation->isPending()) {
            return null;
        }

        return $invitation;
    }

    public function accept(string $rawToken, array $userData = [], ?User $authenticatedUser = null): User
    {
        if (trim($rawToken) === '') {
            throw ValidationException::withMessages([
                'token' => 'Invalid invitation token.',
            ]);
        }

        $tokenHash = hash('sha256', $rawToken);

        return DB::transaction(function () use ($tokenHash, $userData, $authenticatedUser): User {
            $invitation = Invitation::query()
                ->with('academy')
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                throw ValidationException::withMessages([
                    'token' => 'This invitation token is invalid.',
                ]);
            }

            if ($invitation->status !== 'pending') {
                throw ValidationException::withMessages([
                    'token' => 'This invitation has already been used or revoked.',
                ]);
            }

            if ($invitation->isExpired()) {
                $invitation->update(['status' => 'expired']);

                throw ValidationException::withMessages([
                    'token' => 'This invitation has expired.',
                ]);
            }

            if ($authenticatedUser !== null) {
                if (Str::lower($authenticatedUser->email) !== Str::lower($invitation->email)) {
                    throw ValidationException::withMessages([
                        'email' => 'The signed-in account does not match the invited email address.',
                    ]);
                }

                $user = $authenticatedUser;
            } else {
                $user = User::where('email', Str::lower($invitation->email))->first();

                if ($user === null) {
                    $validated = Validator::make($userData, [
                        'name' => ['nullable', 'string', 'max:255'],
                        'password' => ['required', 'string', 'min:8'],
                        'phone' => ['nullable', 'string', 'max:50'],
                    ])->validate();

                    $user = User::create([
                        'name' => trim((string) ($validated['name'] ?? $invitation->name ?? Str::before($invitation->email, '@'))),
                        'email' => Str::lower($invitation->email),
                        'phone' => $validated['phone'] ?? null,
                        'password' => $validated['password'],
                        'email_verified_at' => now(),
                        'is_active' => true,
                    ]);
                }
            }

            $membership = AcademyUser::query()
                ->where('academy_id', $invitation->academy_id)
                ->where('user_id', $user->getKey())
                ->first();

            if ($membership === null) {
                AcademyUser::create([
                    'academy_id' => $invitation->academy_id,
                    'user_id' => $user->getKey(),
                    'role' => $invitation->role,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $membership->update([
                    'role' => $invitation->role,
                    'status' => 'active',
                    'suspended_at' => null,
                    'updated_at' => now(),
                ]);
            }

            Role::firstOrCreate(['name' => $invitation->role, 'guard_name' => 'web']);
            if (! $user->hasRole($invitation->role)) {
                $user->assignRole($invitation->role);
            }

            if ($invitation->role === 'student') {
                $hasProfile = StudentProfile::query()
                    ->where('academy_id', $invitation->academy_id)
                    ->where('user_id', $user->getKey())
                    ->exists();

                if (! $hasProfile) {
                    StudentProfile::create([
                        'user_id' => $user->getKey(),
                        'academy_id' => $invitation->academy_id,
                        'student_code' => $this->generateStudentCode($invitation->academy),
                        'enrolled_at' => now()->toDateString(),
                        'level' => 'Beginner',
                        'active' => true,
                    ]);
                }
            }

            if ($invitation->role === 'trainer') {
                $hasTrainer = TrainerProfile::query()
                    ->where('academy_id', $invitation->academy_id)
                    ->where('user_id', $user->getKey())
                    ->exists();

                if (! $hasTrainer) {
                    TrainerProfile::create([
                        'user_id' => $user->getKey(),
                        'academy_id' => $invitation->academy_id,
                        'active' => true,
                    ]);
                }
            }

            $invitation->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            $this->recordAudit(
                $invitation->academy,
                $user,
                'invitation.accepted',
                Invitation::class,
                $invitation->getKey(),
                [
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                ],
            );

            return $user->fresh();
        });
    }

    public function revoke(Invitation $invitation, User $actor): Invitation
    {
        return DB::transaction(function () use ($invitation, $actor): Invitation {
            $invitation->update([
                'status' => 'revoked',
                'revoked_at' => now(),
            ]);

            $this->recordAudit(
                $invitation->academy,
                $actor,
                'invitation.revoked',
                Invitation::class,
                $invitation->getKey(),
                [
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                ],
            );

            return $invitation->fresh();
        });
    }

    private function generateStudentCode(Academy $academy): string
    {
        $prefix = strtoupper(Str::limit(preg_replace('/[^A-Za-z0-9]/', '', $academy->slug ?: 'ACAD') ?: 'ACAD', 4, ''));

        do {
            $code = sprintf('%s-%s-%s', $prefix, date('Y'), strtoupper(Str::random(5)));
        } while (StudentProfile::where('student_code', $code)->exists());

        return $code;
    }
}
