<?php

namespace App\Domain\Students\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Academies\Models\AcademyUser;
use App\Domain\Platform\Concerns\RecordsAuditLog;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class StudentManagementService
{
    use RecordsAuditLog;

    public function listForAcademy(Academy $academy, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = StudentProfile::query()
            ->forAcademy($academy)
            ->with(['user', 'academy'])
            ->withCount('enrollments');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('student_code', 'like', "%{$search}%")
                    ->orWhere('batch', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $batch = trim((string) ($filters['batch'] ?? ''));
        if ($batch !== '') {
            $query->where('batch', $batch);
        }

        $level = trim((string) ($filters['level'] ?? ''));
        if ($level !== '') {
            $query->where('level', $level);
        }

        if (array_key_exists('active', $filters) && $filters['active'] !== '' && $filters['active'] !== null) {
            $active = filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($active !== null) {
                $query->where('active', $active);
            }
        }

        return $query->latest()->paginate($perPage);
    }

    public function createOrAttach(Academy $academy, array $data, User $actor): StudentProfile
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
            'student_code' => ['nullable', 'string', 'max:100'],
            'batch' => ['nullable', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'max:100'],
            'enrolled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', 'in:active,suspended,inactive'],
        ])->validate();

        $email = Str::lower(trim($validated['email']));
        $status = ($validated['status'] ?? 'active') === 'suspended' ? 'suspended' : 'active';
        $isActive = $status === 'active';

        return DB::transaction(function () use ($academy, $validated, $email, $status, $isActive, $actor): StudentProfile {
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

            $existingProfile = StudentProfile::query()
                ->where('academy_id', $academy->getKey())
                ->where('user_id', $user->getKey())
                ->first();

            if ($existingProfile !== null) {
                throw ValidationException::withMessages([
                    'email' => 'This user already has a student profile in this academy.',
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
                    'role' => 'student',
                    'status' => $status,
                    'suspended_at' => $isActive ? null : now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $membership->update([
                    'status' => $status,
                    'suspended_at' => $isActive ? null : now(),
                    'updated_at' => now(),
                ]);
            }

            Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
            if (! $user->hasRole('student')) {
                $user->assignRole('student');
            }

            $studentCode = trim((string) ($validated['student_code'] ?? ''));
            if ($studentCode === '') {
                $studentCode = $this->generateStudentCode($academy);
            } elseif (StudentProfile::where('student_code', $studentCode)->exists()) {
                throw ValidationException::withMessages([
                    'student_code' => 'The student code has already been taken.',
                ]);
            }

            $profile = StudentProfile::create([
                'user_id' => $user->getKey(),
                'academy_id' => $academy->getKey(),
                'student_code' => $studentCode,
                'batch' => $validated['batch'] ?? null,
                'enrolled_at' => $validated['enrolled_at'] ?? now()->toDateString(),
                'level' => $validated['level'] ?? 'Beginner',
                'active' => $isActive,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->recordAudit(
                $academy,
                $actor,
                'student.created',
                StudentProfile::class,
                $profile->getKey(),
                [
                    'student_user_id' => $user->getKey(),
                    'email' => $user->email,
                    'student_code' => $profile->student_code,
                ],
            );

            return $profile->load('user');
        });
    }

    public function update(StudentProfile $profile, array $data, User $actor): StudentProfile
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
            'student_code' => ['sometimes', 'required', 'string', 'max:100'],
            'batch' => ['nullable', 'string', 'max:255'],
            'level' => ['sometimes', 'required', 'string', 'max:100'],
            'enrolled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'string', 'in:active,suspended,inactive'],
        ])->validate();

        if (isset($validated['student_code']) && $validated['student_code'] !== $profile->student_code) {
            $exists = StudentProfile::query()
                ->where('student_code', $validated['student_code'])
                ->where('id', '!=', $profile->getKey())
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'student_code' => 'The student code has already been taken.',
                ]);
            }
        }

        return DB::transaction(function () use ($profile, $validated, $data, $actor): StudentProfile {
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
                $userUpdates['suspended_at'] = $isActive ? null : now();

                $profile->update(['active' => $isActive]);

                AcademyUser::query()
                    ->where('academy_id', $profile->academy_id)
                    ->where('user_id', $profile->user_id)
                    ->update([
                        'status' => $validated['status'],
                        'suspended_at' => $isActive ? null : now(),
                        'updated_at' => now(),
                    ]);
            }

            if ($userUpdates !== [] && $profile->user !== null) {
                $profile->user->update($userUpdates);
            }

            $profileUpdates = array_intersect_key(
                $validated,
                array_flip(['student_code', 'batch', 'level', 'enrolled_at', 'notes']),
            );
            if (array_key_exists('notes', $data) && ! isset($profileUpdates['notes'])) {
                $profileUpdates['notes'] = $data['notes'] !== '' ? $data['notes'] : null;
            }
            if (array_key_exists('batch', $data) && ! isset($profileUpdates['batch'])) {
                $profileUpdates['batch'] = $data['batch'] !== '' ? $data['batch'] : null;
            }

            if ($profileUpdates !== []) {
                $profile->update($profileUpdates);
            }

            $this->recordAudit(
                $profile->academy,
                $actor,
                'student.updated',
                StudentProfile::class,
                $profile->getKey(),
                ['updated_fields' => array_keys($validated)],
            );

            return $profile->fresh(['user', 'academy']);
        });
    }

    public function setActive(StudentProfile $profile, bool $active, User $actor): StudentProfile
    {
        return DB::transaction(function () use ($profile, $active, $actor): StudentProfile {
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
                $active ? 'student.activated' : 'student.suspended',
                StudentProfile::class,
                $profile->getKey(),
                [
                    'student_user_id' => $profile->user_id,
                    'active' => $active,
                ],
            );

            return $profile->fresh(['user']);
        });
    }

    /**
     * @return array<int, string>
     */
    public function csvTemplateHeaders(): array
    {
        return ['name', 'email', 'phone', 'student_code', 'batch', 'level', 'enrolled_at', 'notes'];
    }

    public function sanitizeCsvCell(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$trimmed;
        }

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $trimmed;
    }

    public function previewCsv(Academy $academy, string $csvContent): array
    {
        $cleanContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent) ?? $csvContent;
        $lines = preg_split('/\r\n|\r|\n/', trim($cleanContent)) ?: [];
        $lines = array_values(array_filter($lines, fn (string $line): bool => trim($line) !== ''));

        if ($lines === []) {
            throw ValidationException::withMessages([
                'csv' => 'The CSV file is empty.',
            ]);
        }

        $rawHeaders = str_getcsv(array_shift($lines));
        $headers = array_map(fn (?string $h): string => Str::lower(trim((string) $h)), $rawHeaders);

        if (! in_array('name', $headers, true) || ! in_array('email', $headers, true)) {
            throw ValidationException::withMessages([
                'csv' => 'CSV must include at least "name" and "email" headers.',
            ]);
        }

        $existingEmails = StudentProfile::query()
            ->forAcademy($academy)
            ->join('users', 'users.id', '=', 'student_profiles.user_id')
            ->pluck('users.email')
            ->map(fn (string $email): string => Str::lower($email))
            ->flip()
            ->all();

        $existingCodes = StudentProfile::query()
            ->pluck('student_code')
            ->flip()
            ->all();

        $seenEmails = [];
        $seenCodes = [];
        $validRows = [];
        $errorRows = [];

        foreach ($lines as $index => $line) {
            $rowNumber = $index + 2;
            $cells = str_getcsv($line);
            $assoc = [];

            foreach ($headers as $colIndex => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = $this->sanitizeCsvCell((string) ($cells[$colIndex] ?? ''));
            }

            $email = Str::lower(trim((string) ($assoc['email'] ?? '')));
            $assoc['email'] = $email;

            if (($assoc['level'] ?? '') === '') {
                $assoc['level'] = 'Beginner';
            }

            $validator = Validator::make($assoc, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'student_code' => ['nullable', 'string', 'max:100'],
                'batch' => ['nullable', 'string', 'max:255'],
                'level' => ['nullable', 'string', 'max:100'],
                'enrolled_at' => ['nullable', 'date'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ]);

            $errors = $validator->errors()->all();

            if ($email !== '' && isset($seenEmails[$email])) {
                $errors[] = "Duplicate email '{$email}' in CSV file.";
            }

            if ($email !== '' && isset($existingEmails[$email])) {
                $errors[] = "Student with email '{$email}' already exists in this academy.";
            }

            $code = trim((string) ($assoc['student_code'] ?? ''));
            if ($code !== '') {
                if (isset($seenCodes[$code])) {
                    $errors[] = "Duplicate student_code '{$code}' in CSV file.";
                }
                if (isset($existingCodes[$code])) {
                    $errors[] = "Student code '{$code}' already exists.";
                }
            }

            if ($errors !== []) {
                $errorRows[] = [
                    'row_number' => $rowNumber,
                    'raw' => $assoc,
                    'errors' => $errors,
                ];

                continue;
            }

            $seenEmails[$email] = true;
            if ($code !== '') {
                $seenCodes[$code] = true;
            }

            $validRows[] = [
                'row_number' => $rowNumber,
                'data' => [
                    'name' => $assoc['name'],
                    'email' => $email,
                    'phone' => ($assoc['phone'] ?? '') !== '' ? $assoc['phone'] : null,
                    'student_code' => $code !== '' ? $code : null,
                    'batch' => ($assoc['batch'] ?? '') !== '' ? $assoc['batch'] : null,
                    'level' => $assoc['level'] ?: 'Beginner',
                    'enrolled_at' => ($assoc['enrolled_at'] ?? '') !== '' ? $assoc['enrolled_at'] : null,
                    'notes' => ($assoc['notes'] ?? '') !== '' ? $assoc['notes'] : null,
                ],
            ];
        }

        return [
            'valid_rows' => $validRows,
            'error_rows' => $errorRows,
            'summary' => [
                'total' => count($validRows) + count($errorRows),
                'valid' => count($validRows),
                'errors' => count($errorRows),
            ],
        ];
    }

    public function commitCsvImport(Academy $academy, array $validRows, User $actor): array
    {
        return DB::transaction(function () use ($academy, $validRows, $actor): array {
            $profiles = [];

            foreach ($validRows as $row) {
                $rowData = is_array($row) && array_key_exists('data', $row) ? $row['data'] : $row;
                $profiles[] = $this->createOrAttach($academy, $rowData, $actor);
            }

            $this->recordAudit(
                $academy,
                $actor,
                'student.csv_imported',
                Academy::class,
                $academy->getKey(),
                ['imported_count' => count($profiles)],
            );

            return [
                'imported' => count($profiles),
                'profiles' => $profiles,
            ];
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
