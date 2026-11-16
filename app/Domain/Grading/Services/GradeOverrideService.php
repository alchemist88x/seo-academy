<?php

namespace App\Domain\Grading\Services;

use App\Domain\Grading\Exceptions\GradeOverrideStaleException;
use App\Domain\Grading\Models\GradeOverrideEvent;
use Illuminate\Support\Facades\DB;
use LogicException;

class GradeOverrideService
{
    /**
     * Record a grade override event with CAS verification and monotonic sequencing.
     *
     * @throws GradeOverrideStaleException
     */
    public function recordOverride(
        string $enrollmentId,
        string $assessmentType,
        ?string $assessmentId,
        string|int|float|null $expectedPreviousScore,
        string|int|float $newScore,
        string $reason,
        string $overriddenBy
    ): GradeOverrideEvent {
        return DB::transaction(function () use (
            $enrollmentId,
            $assessmentType,
            $assessmentId,
            $expectedPreviousScore,
            $newScore,
            $reason,
            $overriddenBy
        ) {
            $latest = GradeOverrideEvent::where('enrollment_id', $enrollmentId)
                ->where('assessment_type', $assessmentType)
                ->where('assessment_id', $assessmentId)
                ->orderByDesc('sequence_number')
                ->lockForUpdate()
                ->first();

            // CAS validation
            if ($latest !== null) {
                $currentScore = (string) $latest->effective_score;
                $expected = $expectedPreviousScore !== null ? (string) $expectedPreviousScore : null;

                if ($expected === null || DecimalPrecisionEngine::compare($expected, $currentScore, 2) !== 0) {
                    throw new GradeOverrideStaleException(
                        "Stale override rejected: expected previous score [{$expected}] does not match current effective score [{$currentScore}]."
                    );
                }
                $sequenceNumber = $latest->sequence_number + 1;
                $previousEventId = $latest->id;
            } else {
                $sequenceNumber = 1;
                $previousEventId = null;
            }

            $formattedNewScore = DecimalPrecisionEngine::format($newScore, 2);
            $formattedPrevScore = $expectedPreviousScore !== null
                ? DecimalPrecisionEngine::format($expectedPreviousScore, 2)
                : null;

            return GradeOverrideEvent::create([
                'enrollment_id' => $enrollmentId,
                'assessment_type' => $assessmentType,
                'assessment_id' => $assessmentId,
                'sequence_number' => $sequenceNumber,
                'previous_event_id' => $previousEventId,
                'reversal_of_event_id' => null,
                'previous_score' => $formattedPrevScore,
                'new_score' => $formattedNewScore,
                'effective_score' => $formattedNewScore,
                'reason' => $reason,
                'overridden_by' => $overriddenBy,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Explicitly reverse a previous grade override.
     *
     * @throws LogicException
     * @throws GradeOverrideStaleException
     */
    public function reverseOverride(
        GradeOverrideEvent|string $event,
        string $reason,
        string $actorId
    ): GradeOverrideEvent {
        return DB::transaction(function () use ($event, $reason, $actorId) {
            $target = $event instanceof GradeOverrideEvent
                ? $event
                : GradeOverrideEvent::findOrFail($event);

            // Ensure not already reversed
            $alreadyReversed = GradeOverrideEvent::where('reversal_of_event_id', $target->id)->exists();
            if ($alreadyReversed) {
                throw new LogicException("Grade override event [{$target->id}] has already been reversed.");
            }

            // Find current head of the sequence
            $latest = GradeOverrideEvent::where('enrollment_id', $target->enrollment_id)
                ->where('assessment_type', $target->assessment_type)
                ->where('assessment_id', $target->assessment_id)
                ->orderByDesc('sequence_number')
                ->lockForUpdate()
                ->first();

            if (! $latest) {
                throw new LogicException('Cannot reverse override: no existing override events found.');
            }

            // Target previous score becomes restored effective score
            $restoredScore = $target->previous_score;
            if ($restoredScore === null) {
                throw new LogicException('Cannot reverse override: target event had no previous score recorded.');
            }

            $sequenceNumber = $latest->sequence_number + 1;
            $currentScore = (string) $latest->effective_score;

            return GradeOverrideEvent::create([
                'enrollment_id' => $target->enrollment_id,
                'assessment_type' => $target->assessment_type,
                'assessment_id' => $target->assessment_id,
                'sequence_number' => $sequenceNumber,
                'previous_event_id' => $latest->id,
                'reversal_of_event_id' => $target->id,
                'previous_score' => $currentScore,
                'new_score' => $restoredScore,
                'effective_score' => $restoredScore,
                'reason' => $reason,
                'overridden_by' => $actorId,
                'created_at' => now(),
            ]);
        });
    }
}
