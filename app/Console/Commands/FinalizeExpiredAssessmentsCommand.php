<?php

namespace App\Console\Commands;

use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Assessments\Services\AssessmentFinalizationService;
use App\Domain\Platform\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FinalizeExpiredAssessmentsCommand extends Command
{
    protected $signature = 'assessments:finalize-expired {--limit=50 : Maximum number of attempts to finalize in one batch}';

    protected $description = 'Finalize in-progress assessment attempts that have passed their expiration deadline.';

    public function handle(AssessmentFinalizationService $finalizationService): int
    {
        // Guard against execution during backup restoration
        if (BackupService::isRestorationInProgress()) {
            $this->warn('System restoration is currently in progress. Skipping assessment finalization.');
            Log::info('Skipped assessments:finalize-expired because BackupService restoration is in progress.');

            return 0;
        }

        $limit = max(1, (int) $this->option('limit'));
        $now = Carbon::now();

        $expiredAttempts = AssessmentAttempt::query()
            ->where('status', AssessmentAttempt::STATUS_IN_PROGRESS)
            ->whereNotNull('finalize_after')
            ->where('finalize_after', '<=', $now)
            ->orderBy('finalize_after')
            ->limit($limit)
            ->get();

        $finalizedCount = 0;

        foreach ($expiredAttempts as $attempt) {
            try {
                $finalizationService->finalizeAttempt($attempt, 'timeout_expired');
                $finalizedCount++;
            } catch (\Throwable $e) {
                Log::error("Failed to finalize expired assessment attempt [{$attempt->id}]: ".$e->getMessage(), [
                    'attempt_id' => $attempt->id,
                    'exception' => $e,
                ]);
            }
        }

        $this->info("Successfully finalized {$finalizedCount} expired assessment attempt(s).");

        return 0;
    }
}
