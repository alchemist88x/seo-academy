<?php

namespace App\Console\Commands;

use App\Domain\Academies\Models\Academy;
use App\Domain\Notifications\Models\AcademyNotification;
use App\Domain\Platform\Concerns\RecordsAuditLog;
use App\Domain\Platform\Services\BackupService;
use App\Domain\Students\Models\Enrollment;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessExpiredEnrollments extends Command
{
    use RecordsAuditLog;

    protected $signature = 'enrollments:process-expired';

    protected $description = 'Process active enrollments whose expiry date has passed and transition them to expired.';

    public function handle(): int
    {
        // Restore-state guard: halt command execution if a restore is actively locked
        if (BackupService::isRestorationInProgress()) {
            $this->warn('System restoration is currently in progress. Skipping enrollment expiration processing to prevent write conflicts.');
            Log::info('Skipped enrollments:process-expired because BackupService restoration is in progress.');

            return 0;
        }

        $now = now();
        $processedCount = 0;

        Enrollment::query()
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->with(['studentProfile.user', 'course'])
            ->chunkById(100, function (Collection $enrollments) use (&$processedCount): void {
                foreach ($enrollments as $enrollment) {
                    if (BackupService::isRestorationInProgress()) {
                        return;
                    }

                    DB::transaction(function () use ($enrollment, &$processedCount): void {
                        $lockedEnrollment = Enrollment::query()
                            ->where('id', $enrollment->id)
                            ->where('status', Enrollment::STATUS_ACTIVE)
                            ->lockForUpdate()
                            ->with(['studentProfile.user', 'course'])
                            ->first();

                        if (! $lockedEnrollment) {
                            return;
                        }

                        $lockedEnrollment->status = Enrollment::STATUS_EXPIRED;
                        $lockedEnrollment->save();

                        $processedCount++;

                        $academy = $lockedEnrollment->academy ?? Academy::current() ?? Academy::first();
                        if ($academy) {
                            $this->recordAudit(
                                $academy,
                                null,
                                'enrollment.status_changed',
                                Enrollment::class,
                                (string) $lockedEnrollment->id,
                                [
                                    'student_profile_id' => $lockedEnrollment->student_profile_id,
                                    'course_id' => $lockedEnrollment->course_id,
                                    'previous_status' => Enrollment::STATUS_ACTIVE,
                                    'new_status' => Enrollment::STATUS_EXPIRED,
                                    'reason' => 'scheduler_expiry_processing',
                                    'expires_at' => $lockedEnrollment->expires_at?->toIso8601String(),
                                ]
                            );
                        }

                        // Dispatch expiry notification if target user exists
                        $user = $lockedEnrollment->studentProfile?->user;
                        if ($user && $academy) {
                            $expiryTs = $lockedEnrollment->expires_at ? Carbon::parse($lockedEnrollment->expires_at)->toIso8601String() : 'no_expiry';
                            $expiryEventMarker = "[expiry_event:{$lockedEnrollment->id}:{$expiryTs}]";

                            $alreadySentForEvent = AcademyNotification::query()
                                ->where('academy_id', $academy->id)
                                ->where('target', (string) $user->id)
                                ->where('category', 'enrollment')
                                ->where('message', 'like', "%{$expiryEventMarker}%")
                                ->exists();

                            if (! $alreadySentForEvent) {
                                AcademyNotification::create([
                                    'academy_id' => $academy->id,
                                    'audience' => 'student',
                                    'target' => (string) $user->id,
                                    'category' => 'enrollment',
                                    'title' => 'Enrollment Expired',
                                    'message' => "Your enrollment in '{$lockedEnrollment->course?->title}' expired on ".($lockedEnrollment->expires_at ? Carbon::parse($lockedEnrollment->expires_at)->format('M d, Y') : 'today').". Please contact an administrator for renewal. {$expiryEventMarker}",
                                    'created_by' => null,
                                    'created_at' => now(),
                                ]);
                            }
                        }
                    });
                }
            });

        $this->info("Processed {$processedCount} expired enrollments.");

        return 0;
    }
}
