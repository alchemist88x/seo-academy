<?php

namespace App\Domain\Learning\Services;

use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentEvaluation;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Services\CertificateIssuanceService;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Learning\Events\CourseCompleted;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Students\Events\EnrollmentCompleted;
use App\Domain\Students\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CourseCompletionService
{
    public function __construct(
        protected CourseNavigationService $navigationService,
        protected CertificateIssuanceService $certificateService
    ) {}

    /**
     * Determine whether an enrollment satisfies all course and module completion requirements.
     *
     * Rules:
     * - Only active, non-hidden lessons count towards completion.
     * - Module completion rules:
     *     - 'all_lessons': All active lessons in the module must be completed.
     *     - 'required_count': At least required_lessons_count lessons must be completed.
     *     - 'none': Module is optional and does not block course completion.
     * - If a course has no active lessons, it cannot be completed.
     */
    public function evaluateCourseCompletion(Enrollment $enrollment): bool
    {
        $course = $enrollment->course;
        if ($course === null) {
            return false;
        }

        $modules = $course->modules()
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', 'Active')
                    ->orWhere('status', 'active');
            })
            ->orderBy('display_order')
            ->with(['lessons' => function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhere('status', 'Active')
                        ->orWhere('status', 'active');
                })
                    ->where('visibility', '!=', 'hidden')
                    ->orderBy('display_order');
            }])
            ->get();

        if ($modules->isEmpty()) {
            return false;
        }

        $allLessonsCount = 0;

        // Fetch completed lesson IDs for this enrollment
        $completedLessonIds = LessonProgress::forEnrollment($enrollment)
            ->where('status', 'completed')
            ->pluck('lesson_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        foreach ($modules as $module) {
            $moduleLessons = $module->lessons;
            $moduleLessonsCount = $moduleLessons->count();
            $allLessonsCount += $moduleLessonsCount;

            $rule = $module->completion_rule ?? 'all_lessons';

            if ($rule === 'none') {
                continue;
            }

            $completedInModule = 0;
            foreach ($moduleLessons as $lesson) {
                if (in_array((string) $lesson->id, $completedLessonIds, true)) {
                    $completedInModule++;
                }
            }

            if ($rule === 'required_count') {
                $requiredCount = $module->required_lessons_count ?? $moduleLessonsCount;
                if ($completedInModule < $requiredCount) {
                    return false;
                }
            } else {
                // Default: all_lessons
                if ($moduleLessonsCount > 0 && $completedInModule < $moduleLessonsCount) {
                    return false;
                }
            }
        }

        if ($allLessonsCount === 0) {
            return false;
        }

        // Evaluate Assessment requirements
        $requiredAssessments = Quiz::query()
            ->where('is_required', true)
            ->where('status', 'Active')
            ->where(function ($q) use ($course, $modules) {
                $q->where('course_id', $course->id)
                    ->orWhereIn('module_id', $modules->pluck('id'));
            })
            ->get();

        if ($requiredAssessments->isNotEmpty()) {
            foreach ($requiredAssessments as $assessment) {
                $hasPassed = AssessmentAttempt::query()
                    ->where('enrollment_id', $enrollment->id)
                    ->where('assessment_id', $assessment->id)
                    ->where('status', AssessmentAttempt::STATUS_EVALUATED)
                    ->where('passed', true)
                    ->exists();

                if (! $hasPassed) {
                    return false;
                }
            }
        }

        // Evaluate Mandatory Practical Assignment requirements
        $mandatoryAssignments = Assignment::query()
            ->where('is_mandatory', true)
            ->whereIn('status', [Assignment::STATUS_PUBLISHED, 'Active', 'active'])
            ->where(function ($q) use ($course, $modules) {
                $q->where('course_id', $course->id)
                    ->orWhereIn('module_id', $modules->pluck('id'));
            })
            ->get();

        if ($mandatoryAssignments->isNotEmpty()) {
            foreach ($mandatoryAssignments as $assignment) {
                // Rule 1: Find the latest submission version for this student's enrollment and assignment
                /** @var AssignmentSubmission|null $latestSubmission */
                $latestSubmission = AssignmentSubmission::query()
                    ->where('enrollment_id', $enrollment->id)
                    ->where('assignment_id', $assignment->id)
                    ->orderByDesc('version_number')
                    ->orderByDesc('created_at')
                    ->first();

                // Rule 4: If no submission exists, requirement is incomplete
                if (! $latestSubmission) {
                    return false;
                }

                // Rule 6 & 7: If latest submission is awaiting grading or has revision requested, requirement is incomplete
                if ($latestSubmission->status !== AssignmentSubmission::STATUS_GRADED) {
                    return false;
                }

                // Rule 2: Load the latest evaluation belonging to that submission (deterministic ordering)
                /** @var AssignmentEvaluation|null $latestEvaluation */
                $latestEvaluation = $latestSubmission->evaluations()
                    ->orderByDesc('graded_at')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->first();

                // Rule 5: If no evaluation exists, requirement is incomplete
                if (! $latestEvaluation) {
                    return false;
                }

                // Rule 3, 8 & 9: Latest evaluation must be graded and passed; cannot be overridden by older passing evaluation
                if ($latestEvaluation->status !== AssignmentEvaluation::STATUS_GRADED || ! $latestEvaluation->is_passed) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Attempt to transition an enrollment to completed if completion requirements are satisfied.
     *
     * Principles:
     * - Safe lifecycle transition: Active -> Completed.
     * - Suspended, cancelled, or expired enrollments cannot be completed automatically.
     * - Idempotent: repeated calls on completed enrollments return true without altering completed_at.
     * - Database transactions ensure atomic lifecycle update and certificate issuance.
     * - Dispatches EnrollmentCompleted and CourseCompleted events after commit.
     */
    public function completeCourse(Enrollment $enrollment, ?User $actor = null): bool
    {
        return DB::transaction(function () use ($enrollment) {
            /** @var Enrollment $lockedEnrollment */
            $lockedEnrollment = Enrollment::query()
                ->where('id', $enrollment->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 1. Idempotency check: already completed
            if ($lockedEnrollment->isCompleted()) {
                // Ensure certificate is issued if eligible
                try {
                    $this->certificateService->issueCertificate($lockedEnrollment);
                } catch (\InvalidArgumentException) {
                    // Ineligible for certificate (e.g., active scheme requires published passing grade); completion remains intact
                }

                return true;
            }

            // 2. Lifecycle eligibility: only valid active enrollments can transition
            if ($lockedEnrollment->status !== Enrollment::STATUS_ACTIVE) {
                return false;
            }

            if (! $lockedEnrollment->isValid()) {
                return false;
            }

            if (! $lockedEnrollment->canTransitionTo(Enrollment::STATUS_COMPLETED)) {
                return false;
            }

            // 3. Curriculum requirement evaluation
            if (! $this->evaluateCourseCompletion($lockedEnrollment)) {
                return false;
            }

            // 4. Perform state transition
            $now = Carbon::now();
            $lockedEnrollment->status = Enrollment::STATUS_COMPLETED;
            if ($lockedEnrollment->completed_at === null) {
                $lockedEnrollment->completed_at = $now;
            }
            $lockedEnrollment->save();

            // 5. Issue certificate if eligible per Policy 2
            try {
                $this->certificateService->issueCertificate($lockedEnrollment);
            } catch (\InvalidArgumentException) {
                // Policy 1: Course completion is independent of academic passing.
                // If not eligible (e.g. grading scheme active and no published passing grade yet, or failing),
                // enrollment completion succeeds independently.
            }

            // 6. Dispatch domain events after commit
            DB::afterCommit(function () use ($lockedEnrollment) {
                event(new EnrollmentCompleted($lockedEnrollment));
                if ($lockedEnrollment->course) {
                    event(new CourseCompleted($lockedEnrollment->course, $lockedEnrollment));
                }
            });

            // Update the in-memory enrollment instance
            $enrollment->status = $lockedEnrollment->status;
            $enrollment->completed_at = $lockedEnrollment->completed_at;

            return true;
        });
    }

    /**
     * Retrieve a detailed course completion status report for an enrollment.
     *
     * @return array{
     *     is_completed: bool,
     *     completed_at: ?Carbon,
     *     is_eligible: bool,
     *     can_transition: bool,
     *     certificate: ?Certificate,
     *     modules: array<int, array<string, mixed>>
     * }
     */
    public function getCompletionStatus(Enrollment $enrollment): array
    {
        $course = $enrollment->course;
        $modules = $course ? $course->modules()
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', 'Active')
                    ->orWhere('status', 'active');
            })
            ->orderBy('display_order')
            ->with(['lessons' => function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhere('status', 'Active')
                        ->orWhere('status', 'active');
                })
                    ->where('visibility', '!=', 'hidden')
                    ->orderBy('display_order');
            }])
            ->get() : collect();

        $completedLessonIds = LessonProgress::forEnrollment($enrollment)
            ->where('status', 'completed')
            ->pluck('lesson_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $modulesReport = [];

        foreach ($modules as $module) {
            $moduleLessons = $module->lessons;
            $moduleLessonsCount = $moduleLessons->count();
            $rule = $module->completion_rule ?? 'all_lessons';

            $completedCount = 0;
            foreach ($moduleLessons as $lesson) {
                if (in_array((string) $lesson->id, $completedLessonIds, true)) {
                    $completedCount++;
                }
            }

            $requiredCount = $rule === 'required_count'
                ? ($module->required_lessons_count ?? $moduleLessonsCount)
                : ($rule === 'none' ? 0 : $moduleLessonsCount);

            $isSatisfied = $rule === 'none' || ($completedCount >= $requiredCount);

            $modulesReport[] = [
                'module_id' => $module->id,
                'title' => $module->title,
                'rule' => $rule,
                'total_lessons' => $moduleLessonsCount,
                'completed_lessons' => $completedCount,
                'required_lessons' => $requiredCount,
                'is_satisfied' => $isSatisfied,
            ];
        }

        $certificate = $this->certificateService->getCertificateForEnrollment($enrollment);

        $assessmentsReport = [];
        $courseAssessments = Quiz::query()
            ->where('status', 'Active')
            ->where(function ($q) use ($course, $modules) {
                if ($course) {
                    $q->where('course_id', $course->id)
                        ->orWhereIn('module_id', $modules->pluck('id'));
                }
            })
            ->get();

        foreach ($courseAssessments as $assessment) {
            $hasPassed = AssessmentAttempt::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('assessment_id', $assessment->id)
                ->where('status', AssessmentAttempt::STATUS_EVALUATED)
                ->where('passed', true)
                ->exists();

            $assessmentsReport[] = [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'is_required' => (bool) $assessment->is_required,
                'is_passed' => $hasPassed,
            ];
        }

        return [
            'is_completed' => $enrollment->isCompleted(),
            'completed_at' => $enrollment->completed_at,
            'is_eligible' => $this->evaluateCourseCompletion($enrollment),
            'can_transition' => $enrollment->isActive() && $enrollment->isValid(),
            'certificate' => $certificate,
            'modules' => $modulesReport,
            'assessments' => $assessmentsReport,
        ];
    }
}
