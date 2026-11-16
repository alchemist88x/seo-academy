<?php

namespace Database\Factories;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'academy_id' => Academy::current()?->id,
            'student_profile_id' => StudentProfile::factory(),
            'course_id' => Course::factory(),
            'assigned_by' => null,
            'enrolled_at' => now()->toDateString(),
            'starts_at' => now(),
            'expires_at' => null,
            'status' => Enrollment::STATUS_ACTIVE,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'suspended_at' => null,
            'suspension_reason' => null,
            'completed_at' => null,
            'notes' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => Enrollment::STATUS_PENDING,
            'starts_at' => null,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => Enrollment::STATUS_ACTIVE,
            'starts_at' => now(),
        ]);
    }

    public function suspended(?string $reason = 'Administrative review'): static
    {
        return $this->state(fn () => [
            'status' => Enrollment::STATUS_SUSPENDED,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => Enrollment::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => Enrollment::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);
    }

    public function cancelled(?string $reason = 'Administrative cancellation'): static
    {
        return $this->state(fn () => [
            'status' => Enrollment::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    public function assignedBy(User $admin): static
    {
        return $this->state(fn () => [
            'assigned_by' => $admin->id,
        ]);
    }
}
