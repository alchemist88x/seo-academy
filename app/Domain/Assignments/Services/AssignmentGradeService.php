<?php

namespace App\Domain\Assignments\Services;

use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Students\Models\Enrollment;
use Illuminate\Support\Collection;

/**
 * Clean boundary service exposing assignment grading records for Phase 5 Gradebook & Transcripts.
 */
class AssignmentGradeService
{
    /**
     * Get a summary of all assignment grades for a specific student enrollment.
     *
     * @return Collection<int, array{
     *     assignment_id: string,
     *     title: string,
     *     is_mandatory: bool,
     *     max_marks: float,
     *     passing_marks: float,
     *     status: string,
     *     submission_count: int,
     *     latest_version: ?int,
     *     grade: ?float,
     *     score_percentage: ?float,
     *     is_passed: bool,
     *     submitted_at: ?string,
     *     graded_at: ?string
     * }>
     */
    public function getEnrollmentAssignmentSummary(Enrollment $enrollment): Collection
    {
        $assignments = Assignment::query()
            ->where('course_id', $enrollment->course_id)
            ->published()
            ->get();

        return $assignments->map(function (Assignment $assignment) use ($enrollment) {
            $latestSubmission = AssignmentSubmission::query()
                ->where('assignment_id', $assignment->id)
                ->where('enrollment_id', $enrollment->id)
                ->orderByDesc('version_number')
                ->with('latestEvaluation')
                ->first();

            $eval = $latestSubmission?->latestEvaluation;
            $isPassed = $eval ? (bool) $eval->is_passed : false;

            return [
                'assignment_id' => $assignment->id,
                'title' => $assignment->title,
                'is_mandatory' => (bool) $assignment->is_mandatory,
                'max_marks' => $eval ? (float) $eval->points_possible : (float) $assignment->max_marks,
                'passing_marks' => (float) $assignment->passing_marks,
                'status' => $latestSubmission ? $latestSubmission->status : 'unsubmitted',
                'submission_count' => AssignmentSubmission::where('assignment_id', $assignment->id)->where('enrollment_id', $enrollment->id)->count(),
                'latest_version' => $latestSubmission?->version_number,
                'grade' => $eval ? (float) $eval->points_awarded : null,
                'score_percentage' => $eval ? (float) $eval->score_percentage : null,
                'is_passed' => $isPassed,
                'submitted_at' => $latestSubmission?->submitted_at?->toISOString(),
                'graded_at' => $eval?->graded_at?->toISOString(),
            ];
        });
    }

    /**
     * Get aggregate assignment completion and average score metrics for an enrollment.
     *
     * @return array{
     *     total_assignments: int,
     *     submitted_count: int,
     *     graded_count: int,
     *     passed_count: int,
     *     mandatory_pending: int,
     *     average_score_percentage: float
     * }
     */
    public function getEnrollmentMetrics(Enrollment $enrollment): array
    {
        $summary = $this->getEnrollmentAssignmentSummary($enrollment);

        $total = $summary->count();
        $submitted = $summary->whereIn('status', [AssignmentSubmission::STATUS_SUBMITTED, AssignmentSubmission::STATUS_RESUBMITTED, AssignmentSubmission::STATUS_UNDER_REVIEW, AssignmentSubmission::STATUS_GRADED, AssignmentSubmission::STATUS_REVISION_REQUESTED])->count();
        $graded = $summary->where('status', AssignmentSubmission::STATUS_GRADED)->count();
        $passed = $summary->where('is_passed', true)->count();
        $mandatoryPending = $summary->where('is_mandatory', true)->where('is_passed', false)->count();

        $gradedItems = $summary->whereNotNull('score_percentage');
        $avgScore = $gradedItems->isNotEmpty() ? round($gradedItems->avg('score_percentage'), 2) : 0.00;

        return [
            'total_assignments' => $total,
            'submitted_count' => $submitted,
            'graded_count' => $graded,
            'passed_count' => $passed,
            'mandatory_pending' => $mandatoryPending,
            'average_score_percentage' => $avgScore,
        ];
    }
}
