<?php

namespace App\Domain\Students\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Notifications\Models\AcademyNotification;
use App\Domain\Platform\Concerns\RecordsAuditLog;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentManagementService
{
    use RecordsAuditLog;

    public function listForAcademy(Academy $academy, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Enrollment::query()
            ->where(function (Builder $q) use ($academy): void {
                $q->where('academy_id', $academy->id)
                    ->orWhereNull('academy_id');
            })
            ->with(['studentProfile.user', 'course', 'assignedBy']);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->whereHas('studentProfile', function (Builder $studentQuery) use ($search): void {
                    $studentQuery->where('student_code', 'like', "%{$search}%")
                        ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                })->orWhereHas('course', function (Builder $courseQuery) use ($search): void {
                    $courseQuery->where('title', 'like', "%{$search}%");
                });
            });
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array(strtolower($status), Enrollment::STATUSES, true)) {
            $query->where('status', strtolower($status));
        }

        $courseId = trim((string) ($filters['course_id'] ?? ''));
        if ($courseId !== '') {
            $query->where('course_id', $courseId);
        }

        $trainerId = trim((string) ($filters['trainer_id'] ?? ''));
        if ($trainerId !== '') {
            $query->whereHas('course.trainers', function (Builder $trainerQuery) use ($trainerId): void {
                $trainerQuery->where('users.id', $trainerId);
            });
        }

        return $query->latest('created_at')->paginate($perPage);
    }

    public function validateEligibility(StudentProfile $student, Course $course): void
    {
        $user = $student->relationLoaded('user') ? $student->user : $student->user()->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'student' => 'Associated student user account could not be found.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'student' => 'Student user account is inactive.',
            ]);
        }

        if (! $user->hasRole('student')) {
            throw ValidationException::withMessages([
                'student' => 'User does not possess the student role.',
            ]);
        }

        if (! $course->isPublished()) {
            throw ValidationException::withMessages([
                'course' => 'Course is not published for enrollment.',
            ]);
        }

        if ($course->visibility === 'private') {
            throw ValidationException::withMessages([
                'course' => 'Course visibility is private and cannot accept enrollments.',
            ]);
        }

        // Prerequisites check
        $prerequisites = $course->prerequisites()->get();
        if ($prerequisites->isNotEmpty()) {
            foreach ($prerequisites as $prereq) {
                $satisfied = Enrollment::query()
                    ->where('student_profile_id', $student->id)
                    ->where('course_id', $prereq->id)
                    ->where(function (Builder $q): void {
                        $q->where('status', Enrollment::STATUS_COMPLETED)
                            ->orWhere(function (Builder $activeQuery): void {
                                $activeQuery->valid();
                            });
                    })
                    ->exists();

                if (! $satisfied) {
                    throw ValidationException::withMessages([
                        'prerequisites' => "Student has not completed the prerequisite course: {$prereq->title}.",
                    ]);
                }
            }
        }

        // Conflicting active enrollment
        $activeExists = Enrollment::query()
            ->where('student_profile_id', $student->id)
            ->where('course_id', $course->id)
            ->whereIn('status', [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_PENDING])
            ->exists();

        if ($activeExists) {
            throw ValidationException::withMessages([
                'enrollment' => 'Student already has an active or pending enrollment for this course.',
            ]);
        }

        // Capacity check
        if ($course->enrollment_limit !== null && $course->enrollment_limit > 0) {
            $currentCount = Enrollment::query()
                ->where('course_id', $course->id)
                ->whereIn('status', [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_PENDING])
                ->lockForUpdate()
                ->count();

            if ($currentCount >= $course->enrollment_limit) {
                throw ValidationException::withMessages([
                    'capacity' => "Course enrollment limit of {$course->enrollment_limit} has been reached.",
                ]);
            }
        }
    }

    public function assign(StudentProfile $student, Course $course, User $admin, array $options = []): Enrollment
    {
        return DB::transaction(function () use ($student, $course, $admin, $options): Enrollment {
            // Lock course row immediately to prevent concurrent over-enrollment races
            Course::query()->where('id', $course->id)->lockForUpdate()->first();

            $this->validateEligibility($student, $course);

            $status = strtolower((string) ($options['status'] ?? Enrollment::STATUS_ACTIVE));
            if (! in_array($status, [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_PENDING], true)) {
                $status = Enrollment::STATUS_ACTIVE;
            }

            $startsAt = isset($options['starts_at'])
                ? ($options['starts_at'] instanceof CarbonInterface ? $options['starts_at'] : Carbon::parse($options['starts_at']))
                : now();

            $expiresAt = null;
            if (! empty($options['expires_at'])) {
                $expiresAt = $options['expires_at'] instanceof CarbonInterface
                    ? $options['expires_at']
                    : Carbon::parse($options['expires_at']);
            }

            $academyId = $course->academy_id
                ?? $student->academy_id
                ?? Academy::current()?->id
                ?? Academy::query()->value('id');

            $existing = Enrollment::query()
                ->where('student_profile_id', $student->id)
                ->where('course_id', $course->id)
                ->first();

            if ($existing) {
                $existing->update([
                    'academy_id' => $academyId,
                    'assigned_by' => $admin->id,
                    'enrolled_at' => $options['enrolled_at'] ?? now()->toDateString(),
                    'starts_at' => $startsAt,
                    'expires_at' => $expiresAt,
                    'status' => $status,
                    'cancelled_at' => null,
                    'cancellation_reason' => null,
                    'suspended_at' => null,
                    'suspension_reason' => null,
                    'completed_at' => null,
                    'notes' => $options['notes'] ?? $existing->notes,
                ]);
                $enrollment = $existing;
            } else {
                $enrollment = Enrollment::create([
                    'academy_id' => $academyId,
                    'student_profile_id' => $student->id,
                    'course_id' => $course->id,
                    'assigned_by' => $admin->id,
                    'enrolled_at' => $options['enrolled_at'] ?? now()->toDateString(),
                    'starts_at' => $startsAt,
                    'expires_at' => $expiresAt,
                    'status' => $status,
                    'notes' => $options['notes'] ?? null,
                ]);
            }

            $academy = Academy::find($academyId) ?? Academy::current() ?? Academy::first();
            if ($academy) {
                $this->recordAudit(
                    $academy,
                    $admin,
                    'enrollment.assigned',
                    Enrollment::class,
                    (string) $enrollment->id,
                    [
                        'course_id' => $course->id,
                        'student_profile_id' => $student->id,
                        'status' => $enrollment->status,
                        'starts_at' => $enrollment->starts_at?->toIso8601String(),
                        'expires_at' => $enrollment->expires_at?->toIso8601String(),
                    ]
                );
            }

            $this->notifyStudent(
                $enrollment,
                'Course Enrollment Assigned',
                "You have been enrolled in {$course->title}.",
                $admin
            );

            return $enrollment;
        });
    }

    public function bulkAssign(array $studentProfileIds, Course $course, User $admin, array $options = []): array
    {
        $assigned = [];
        $skipped = [];

        foreach ($studentProfileIds as $studentProfileId) {
            $student = StudentProfile::with('user')->find($studentProfileId);
            if (! $student) {
                $skipped[] = [
                    'student_profile_id' => $studentProfileId,
                    'reason' => 'Student profile not found.',
                ];

                continue;
            }

            try {
                $enrollment = $this->assign($student, $course, $admin, $options);
                $assigned[] = $enrollment;
            } catch (ValidationException $e) {
                $skipped[] = [
                    'student_profile_id' => $studentProfileId,
                    'student_name' => $student->user?->name ?? 'Unknown',
                    'reason' => implode(' ', Arr::flatten($e->errors())),
                ];
            }
        }

        $academy = $course->academy ?? Academy::current() ?? Academy::first();
        if ($academy) {
            $this->recordAudit(
                $academy,
                $admin,
                'enrollment.bulk_assigned',
                Course::class,
                (string) $course->id,
                [
                    'assigned_count' => count($assigned),
                    'skipped_count' => count($skipped),
                    'student_profile_ids' => array_map(fn ($e) => $e->student_profile_id, $assigned),
                ]
            );
        }

        return [
            'assigned' => $assigned,
            'skipped' => $skipped,
        ];
    }

    public function transitionStatus(
        Enrollment $enrollment,
        string $newStatus,
        User $admin,
        array $params = [],
    ): Enrollment {
        return DB::transaction(function () use ($enrollment, $newStatus, $admin, $params): Enrollment {
            $target = strtolower(trim($newStatus));

            if (! $enrollment->canTransitionTo($target)) {
                throw new \DomainException("Cannot transition enrollment status from '{$enrollment->status}' to '{$target}'.");
            }

            $oldStatus = $enrollment->status;

            match ($target) {
                Enrollment::STATUS_ACTIVE => (function () use ($enrollment, $params): void {
                    $enrollment->starts_at = isset($params['starts_at'])
                        ? Carbon::parse($params['starts_at'])
                        : ($enrollment->starts_at ?? now());
                    $enrollment->suspended_at = null;
                    $enrollment->suspension_reason = null;
                    $enrollment->cancelled_at = null;
                    $enrollment->cancellation_reason = null;
                })(),
                Enrollment::STATUS_SUSPENDED => (function () use ($enrollment, $params): void {
                    $enrollment->suspended_at = now();
                    $enrollment->suspension_reason = $params['reason'] ?? $params['suspension_reason'] ?? 'Administrative suspension';
                })(),
                Enrollment::STATUS_CANCELLED => (function () use ($enrollment, $params): void {
                    $enrollment->cancelled_at = now();
                    $enrollment->cancellation_reason = $params['reason'] ?? $params['cancellation_reason'] ?? 'Administrative cancellation';
                })(),
                Enrollment::STATUS_COMPLETED => (function () use ($enrollment): void {
                    $enrollment->completed_at = now();
                })(),
                Enrollment::STATUS_EXPIRED => (function () use ($enrollment): void {
                    if ($enrollment->expires_at === null || $enrollment->expires_at->isFuture()) {
                        $enrollment->expires_at = now();
                    }
                })(),
                default => null,
            };

            $enrollment->status = $target;

            if (! empty($params['notes'])) {
                $enrollment->notes = $params['notes'];
            }

            $enrollment->save();

            $academy = $enrollment->academy ?? Academy::current() ?? Academy::first();
            if ($academy) {
                $this->recordAudit(
                    $academy,
                    $admin,
                    'enrollment.status_changed',
                    Enrollment::class,
                    (string) $enrollment->id,
                    [
                        'from' => $oldStatus,
                        'to' => $target,
                        'reason' => $params['reason'] ?? null,
                    ]
                );
            }

            $courseTitle = $enrollment->course?->title ?? 'your course';
            if ($target === Enrollment::STATUS_ACTIVE) {
                $this->notifyStudent(
                    $enrollment,
                    'Enrollment Activated',
                    "Your enrollment in {$courseTitle} is now active.",
                    $admin
                );
            } elseif ($target === Enrollment::STATUS_EXPIRED) {
                $this->notifyStudent(
                    $enrollment,
                    'Enrollment Expired',
                    "Your enrollment in {$courseTitle} has expired.",
                    $admin
                );
            }

            return $enrollment;
        });
    }

    public function extendExpiry(Enrollment $enrollment, CarbonInterface|string|null $newExpiry, User $admin): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $newExpiry, $admin): Enrollment {
            $oldExpiry = $enrollment->expires_at;

            $parsedExpiry = null;
            if ($newExpiry !== null) {
                $parsedExpiry = $newExpiry instanceof CarbonInterface ? $newExpiry : Carbon::parse($newExpiry);
            }

            $enrollment->expires_at = $parsedExpiry;

            if ($enrollment->status === Enrollment::STATUS_EXPIRED && ($parsedExpiry === null || $parsedExpiry->isFuture())) {
                $enrollment->status = Enrollment::STATUS_ACTIVE;
            }

            $enrollment->save();

            $academy = $enrollment->academy ?? Academy::current() ?? Academy::first();
            if ($academy) {
                $this->recordAudit(
                    $academy,
                    $admin,
                    'enrollment.dates_updated',
                    Enrollment::class,
                    (string) $enrollment->id,
                    [
                        'old_expires_at' => $oldExpiry?->toIso8601String(),
                        'new_expires_at' => $parsedExpiry?->toIso8601String(),
                    ]
                );
            }

            return $enrollment;
        });
    }

    protected function notifyStudent(Enrollment $enrollment, string $title, string $message, ?User $actor = null): void
    {
        $student = $enrollment->studentProfile ?? StudentProfile::find($enrollment->student_profile_id);
        if (! $student) {
            return;
        }

        $academyId = $enrollment->academy_id
            ?? $student->academy_id
            ?? Academy::current()?->id
            ?? Academy::query()->value('id');

        if ($academyId) {
            AcademyNotification::create([
                'academy_id' => $academyId,
                'audience' => 'student',
                'target' => (string) $student->user_id,
                'category' => 'enrollment',
                'title' => $title,
                'message' => $message,
                'created_by' => $actor?->id,
                'created_at' => now(),
            ]);
        }
    }
}
