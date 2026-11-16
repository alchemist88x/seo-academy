<?php

namespace App\Domain\Grading\Services;

use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Assignments\Models\AssignmentEvaluation;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Services\CertificateIssuanceService;
use App\Domain\Grading\Models\AssessmentWeight;
use App\Domain\Grading\Models\CourseGradingScheme;
use App\Domain\Grading\Models\EnrollmentCurrentGrade;
use App\Domain\Grading\Models\EnrollmentFinalGrade;
use App\Domain\Grading\Models\EnrollmentGradeDraft;
use App\Domain\Grading\Models\EnrollmentGradeLifecycleEvent;
use App\Domain\Grading\Models\GradeOverrideEvent;
use App\Domain\Grading\Models\GradingCategory;
use App\Domain\Platform\Services\IdempotencyService;
use App\Domain\Quizzes\Models\QuizSubmission;
use App\Domain\Students\Models\Enrollment;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GradebookCalculationService
{
    public function __construct(
        private readonly ?IdempotencyService $idempotencyService = null,
    ) {}

    /**
     * Calculate student academic grade draft for an enrollment using the course's active grading scheme.
     * Preserves 16-decimal internal precision across all intermediate stages,
     * and applies exact two-decimal half-up rounding only to the final composite score.
     */
    public function calculateEnrollmentGrade(
        Enrollment $enrollment,
        ?CourseGradingScheme $scheme = null,
        ?User $actor = null
    ): EnrollmentGradeDraft {
        $scheme = $scheme ?? $this->resolveActiveScheme($enrollment);

        if (! $scheme) {
            throw new DomainException("Course [{$enrollment->course?->title}] does not have an active grading scheme.");
        }

        $categories = $scheme->categories()
            ->with('assessmentWeights')
            ->orderBy('display_order')
            ->get();

        $directWeights = $scheme->assessmentWeights()
            ->whereNull('grading_category_id')
            ->get();

        $missingMandatory = false;
        $categoryBreakdowns = [];
        $activeCategoryWeights = [];
        $runningSum16 = '0.'.str_repeat('0', DecimalPrecisionEngine::INTERNAL_SCALE);

        if ($categories->isNotEmpty()) {
            // Category-based calculation
            foreach ($categories as $cat) {
                $catResult = $this->calculateCategoryScore($enrollment, $cat);

                if ($catResult['missing_mandatory']) {
                    $missingMandatory = true;
                }

                $categoryBreakdowns[$cat->id] = $catResult;

                if (! $catResult['is_exempt']) {
                    $activeCategoryWeights[$cat->id] = (string) $cat->weight;
                }
            }

            // Redistribute category weights proportionally if any category is exempt
            $redistributedCategoryWeights = ! empty($activeCategoryWeights)
                ? DecimalPrecisionEngine::redistributeWeights($activeCategoryWeights, DecimalPrecisionEngine::INTERNAL_SCALE)
                : [];

            foreach ($redistributedCategoryWeights as $catId => $normalizedWeight) {
                $catScore16 = $categoryBreakdowns[$catId]['unrounded_score'];
                $contrib16 = DecimalPrecisionEngine::weightedScore($catScore16, $normalizedWeight, DecimalPrecisionEngine::INTERNAL_SCALE);
                $runningSum16 = DecimalPrecisionEngine::add($runningSum16, $contrib16, DecimalPrecisionEngine::INTERNAL_SCALE);

                $categoryBreakdowns[$catId]['normalized_weight'] = $normalizedWeight;
                $categoryBreakdowns[$catId]['course_contribution'] = $contrib16;
            }
        } elseif ($directWeights->isNotEmpty()) {
            // Direct assessment weights calculation without categories
            $directResult = $this->calculateDirectWeightsScore($enrollment, $directWeights);
            if ($directResult['missing_mandatory']) {
                $missingMandatory = true;
            }
            $runningSum16 = $directResult['unrounded_score'];
            $categoryBreakdowns['direct'] = $directResult;
        } else {
            // No weights defined: empty 0 score
            $runningSum16 = '0.'.str_repeat('0', DecimalPrecisionEngine::INTERNAL_SCALE);
        }

        // Apply two-decimal half-up rounding ONLY to the final composite percentage
        $finalScore2Dec = DecimalPrecisionEngine::roundHalfUp($runningSum16, DecimalPrecisionEngine::FINAL_GRADE_SCALE);

        // Academic passing determination: must meet threshold AND have no missing mandatory assessments
        $passingThreshold = (string) ($scheme->passing_grade ?? '70.00');
        $isPassing = (! $missingMandatory) && DecimalPrecisionEngine::isPassing(
            $finalScore2Dec,
            $passingThreshold,
            DecimalPrecisionEngine::FINAL_GRADE_SCALE
        );

        $academicStatus = $missingMandatory
            ? 'incomplete'
            : ($isPassing ? 'passed' : 'failed');

        $letterGrade = $this->resolveLetterGrade($finalScore2Dec);

        $breakdown = [
            'unrounded_score' => $runningSum16,
            'final_score' => $finalScore2Dec,
            'passing_grade' => $passingThreshold,
            'is_passing' => $isPassing,
            'academic_status' => $academicStatus,
            'missing_mandatory_assessments' => $missingMandatory,
            'letter_grade' => $letterGrade,
            'scheme_id' => $scheme->id,
            'scheme_version' => $scheme->version,
            'categories' => $categoryBreakdowns,
            'calculated_at' => now()->toIso8601String(),
        ];

        return DB::transaction(function () use (
            $enrollment,
            $scheme,
            $finalScore2Dec,
            $letterGrade,
            $breakdown,
            $isPassing,
            $academicStatus,
            $missingMandatory,
            $actor
        ) {
            $draft = EnrollmentGradeDraft::updateOrCreate(
                ['enrollment_id' => $enrollment->id],
                [
                    'grading_scheme_id' => $scheme->id,
                    'current_score' => $finalScore2Dec,
                    'current_letter_grade' => $letterGrade,
                    'calculation_breakdown' => $breakdown,
                    'is_passing' => $isPassing,
                    'academic_status' => $academicStatus,
                    'missing_mandatory_assessments' => $missingMandatory,
                    'last_calculated_at' => now(),
                ]
            );

            // Ensure current grade pointer exists
            $current = EnrollmentCurrentGrade::firstOrNew(['enrollment_id' => $enrollment->id]);
            $current->working_draft_grade_id = $draft->id;
            if (empty($current->status)) {
                $current->status = 'draft';
            }
            $current->save();

            // Record immutable draft calculated lifecycle event
            EnrollmentGradeLifecycleEvent::create([
                'enrollment_id' => $enrollment->id,
                'event_type' => 'draft_calculated',
                'grade_snapshot_id' => null,
                'actor_id' => $actor?->id,
                'payload' => [
                    'current_score' => $finalScore2Dec,
                    'letter_grade' => $letterGrade,
                    'is_passing' => $isPassing,
                    'academic_status' => $academicStatus,
                    'missing_mandatory' => $missingMandatory,
                ],
                'created_at' => now(),
            ]);

            return $draft;
        });
    }

    /**
     * Publish official immutable final grade for an enrollment.
     * Enforces role and course assignment boundaries (admin or assigned trainer only).
     * Strictly idempotent via IdempotencyService.
     *
     * @throws AuthorizationException
     */
    public function publishEnrollmentGrade(
        Enrollment $enrollment,
        User $publisher,
        string $idempotencyKey,
        ?string $reason = null
    ): EnrollmentFinalGrade {
        // Enforce authorization: admin or assigned trainer only
        $this->assertCanPublish($publisher, $enrollment);

        $idempotency = $this->idempotencyService ?? app(IdempotencyService::class);

        $claim = $idempotency->claimReservation(
            actorId: (string) $publisher->id,
            action: 'publish_final_grade',
            key: $idempotencyKey,
            resourceType: 'enrollment',
            resourceId: (string) $enrollment->id,
            payload: ['reason' => $reason]
        );

        if (! $claim['is_new'] && $claim['status'] === IdempotencyService::STATUS_COMPLETED) {
            $cachedId = $claim['response_body']['final_grade_id'] ?? null;
            if ($cachedId) {
                $cachedRecord = EnrollmentFinalGrade::find($cachedId);
                if ($cachedRecord) {
                    return $cachedRecord;
                }
            }
        }

        try {
            $finalGrade = DB::transaction(function () use ($enrollment, $publisher, $reason) {
                // Ensure fresh draft is calculated
                $draft = $this->calculateEnrollmentGrade($enrollment, actor: $publisher);

                $current = EnrollmentCurrentGrade::where('enrollment_id', $enrollment->id)
                    ->lockForUpdate()
                    ->first();

                $previousFinalId = $current?->published_final_grade_id;
                $version = 1;
                $supersedesId = null;

                if ($previousFinalId !== null) {
                    $previousFinal = EnrollmentFinalGrade::find($previousFinalId);
                    if ($previousFinal) {
                        $version = $previousFinal->version + 1;
                        $supersedesId = $previousFinal->id;
                    }
                }

                $snapshot = array_merge($draft->calculation_breakdown ?? [], [
                    'published_by' => (string) $publisher->id,
                    'publisher_role' => $publisher->hasRole('admin') ? 'admin' : 'trainer',
                    'publication_reason' => $reason,
                    'published_at' => now()->toIso8601String(),
                    'supersedes_grade_id' => $supersedesId,
                ]);

                // Create immutable EnrollmentFinalGrade record
                $finalGrade = EnrollmentFinalGrade::create([
                    'enrollment_id' => $enrollment->id,
                    'grading_scheme_id' => $draft->grading_scheme_id,
                    'version' => $version,
                    'final_score' => $draft->current_score,
                    'letter_grade' => $draft->current_letter_grade,
                    'is_passing' => $draft->is_passing,
                    'academic_status' => $draft->academic_status,
                    'calculation_snapshot' => $snapshot,
                    'published_at' => now(),
                    'published_by' => $publisher->id,
                    'supersedes_grade_id' => $supersedesId,
                    'created_at' => now(),
                ]);

                // Update pointer table with optimistic concurrency control
                if ($current) {
                    $current->published_final_grade_id = $finalGrade->id;
                    $current->status = 'published';
                    $current->lock_version = $current->lock_version + 1;
                    $current->save();
                } else {
                    EnrollmentCurrentGrade::create([
                        'enrollment_id' => $enrollment->id,
                        'published_final_grade_id' => $finalGrade->id,
                        'working_draft_grade_id' => $draft->id,
                        'status' => 'published',
                        'lock_version' => 1,
                    ]);
                }

                // Log immutable lifecycle event
                EnrollmentGradeLifecycleEvent::create([
                    'enrollment_id' => $enrollment->id,
                    'event_type' => $supersedesId ? 'grade_superseded' : 'grade_published',
                    'grade_snapshot_id' => $finalGrade->id,
                    'actor_id' => $publisher->id,
                    'payload' => [
                        'version' => $version,
                        'supersedes_grade_id' => $supersedesId,
                        'final_score' => (string) $finalGrade->final_score,
                        'letter_grade' => $finalGrade->letter_grade,
                        'is_passing' => $finalGrade->is_passing,
                        'academic_status' => $finalGrade->academic_status,
                        'reason' => $reason,
                    ],
                    'created_at' => now(),
                ]);

                return $finalGrade;
            });

            $idempotency->completeReservation(
                $claim['record_id'],
                $claim['ownership_token'],
                200,
                [
                    'final_grade_id' => $finalGrade->id,
                    'version' => $finalGrade->version,
                    'final_score' => (string) $finalGrade->final_score,
                    'is_passing' => $finalGrade->is_passing,
                    'academic_status' => $finalGrade->academic_status,
                ]
            );

            // Automated certificate issuance: if enrollment is completed and published grade is passing
            if ($this->isEligibleForCertificate($enrollment->fresh())) {
                try {
                    app(CertificateIssuanceService::class)->issueCertificate($enrollment->fresh());
                } catch (\Throwable $e) {
                    Log::warning('Automated certificate issuance upon grade publication encountered an error', [
                        'enrollment_id' => $enrollment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $finalGrade;
        } catch (\Throwable $e) {
            $idempotency->failReservation($claim['record_id'], $claim['ownership_token'], $e->getMessage());
            throw $e;
        }
    }

    /**
     * Withdraw an active published grade.
     * Enforces role and course assignment boundaries.
     *
     * @throws AuthorizationException
     */
    public function withdrawEnrollmentGrade(
        Enrollment $enrollment,
        User $actor,
        string $reason
    ): EnrollmentCurrentGrade {
        $this->assertCanPublish($actor, $enrollment);

        return DB::transaction(function () use ($enrollment, $actor, $reason) {
            $current = EnrollmentCurrentGrade::where('enrollment_id', $enrollment->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Safe idempotency: if already withdrawn, return existing record without mutating lock version or creating duplicate events
            if ($current->status === 'withdrawn') {
                return $current;
            }

            if ($current->published_final_grade_id === null) {
                throw new \InvalidArgumentException('Cannot withdraw an enrollment that has no published grade.');
            }

            $publishedGradeId = $current->published_final_grade_id;

            $current->status = 'withdrawn';
            $current->lock_version = $current->lock_version + 1;
            $current->save();

            EnrollmentGradeLifecycleEvent::create([
                'enrollment_id' => $enrollment->id,
                'event_type' => 'grade_withdrawn',
                'grade_snapshot_id' => $publishedGradeId,
                'actor_id' => $actor->id,
                'payload' => [
                    'reason' => $reason,
                    'withdrawn_by' => (string) $actor->id,
                ],
                'created_at' => now(),
            ]);

            // Policy: Withdrawing a published passing grade revokes active certificate validity
            $cert = Certificate::where('enrollment_id', $enrollment->id)
                ->whereIn('status', [
                    Certificate::STATUS_ISSUED,
                    Certificate::STATUS_REINSTATED,
                ])
                ->first();
            if ($cert) {
                app(CertificateIssuanceService::class)->flagForAudit(
                    $cert,
                    $actor,
                    'Published grade was withdrawn: '.$reason
                );
            }

            return $current;
        });
    }

    /**
     * Determine certificate eligibility: requires course completion AND a published passing grade.
     * Mandatory Policy 1 & 2: Course completion is independent of academic passing;
     * certificate eligibility requires BOTH.
     * When no active grading scheme exists on the course, preserves previously approved behavior (course completion alone).
     */
    public function isEligibleForCertificate(Enrollment $enrollment): bool
    {
        $isCourseCompleted = $enrollment->status === Enrollment::STATUS_COMPLETED
            || $enrollment->completed_at !== null;

        if (! $isCourseCompleted) {
            return false;
        }

        $course = $enrollment->course;
        // If course has no active grading scheme, preserve previously approved behavior (completion alone qualifies)
        if (! $course || $course->active_grading_scheme_id === null) {
            return true;
        }

        $currentGrade = $enrollment->currentGrade;
        if (! $currentGrade || $currentGrade->status !== 'published') {
            return false;
        }

        $publishedFinal = $currentGrade->publishedGrade;
        $isPassed = $publishedFinal !== null && (bool) $publishedFinal->is_passing;

        return $isPassed;
    }

    /**
     * Assert publisher has permission to publish or withdraw grades for the course.
     * Administrators and trainers assigned to the course may publish grades.
     *
     * @throws AuthorizationException
     */
    public function assertCanPublish(User $user, Enrollment $enrollment): void
    {
        if (! $user->is_active) {
            throw new AuthorizationException('Inactive users cannot perform grade operations.');
        }

        if ($user->role === 'admin' || $user->hasRole('admin')) {
            return;
        }

        if ($user->role === 'trainer' || $user->hasRole('trainer')) {
            $course = $enrollment->course;
            $isAssigned = $course && $course->trainers()
                ->where('users.id', $user->getKey())
                ->exists();

            if ($isAssigned) {
                return;
            }
        }

        throw new AuthorizationException('Unauthorized: Only administrators or assigned trainers may publish grades for this course.');
    }

    /**
     * Resolve the active grading scheme for an enrollment's course.
     */
    private function resolveActiveScheme(Enrollment $enrollment): ?CourseGradingScheme
    {
        $course = $enrollment->course;
        if (! $course) {
            return null;
        }

        if ($course->active_grading_scheme_id) {
            return CourseGradingScheme::where('id', $course->active_grading_scheme_id)
                ->where('course_id', $course->id)
                ->where('is_active', true)
                ->first();
        }

        return CourseGradingScheme::where('course_id', $course->id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Calculate score for a single grading category, including itemized assessment resolution,
     * override detection, missing mandatory checks, and proportional redistribution of exempt items.
     *
     * @return array{
     *     category_id: string,
     *     name: string,
     *     weight: string,
     *     is_exempt: bool,
     *     missing_mandatory: bool,
     *     unrounded_score: string,
     *     assessments: array<int, mixed>
     * }
     */
    private function calculateCategoryScore(Enrollment $enrollment, GradingCategory $category): array
    {
        $weights = $category->assessmentWeights;
        if ($weights->isEmpty()) {
            // Category has no weights defined; considered exempt or 0 score
            return [
                'category_id' => $category->id,
                'name' => $category->name,
                'weight' => (string) $category->weight,
                'is_exempt' => false,
                'missing_mandatory' => false,
                'unrounded_score' => '0.'.str_repeat('0', DecimalPrecisionEngine::INTERNAL_SCALE),
                'assessments' => [],
            ];
        }

        $items = [];
        $activeItemWeights = [];
        $missingMandatory = false;

        $hasExplicitWeights = $weights->contains(fn ($w) => $w->custom_weight !== null);
        $equalWeightShare = ! $hasExplicitWeights
            ? DecimalPrecisionEngine::div('100', (string) $weights->count(), DecimalPrecisionEngine::INTERNAL_SCALE)
            : null;

        foreach ($weights as $w) {
            $itemScore = $this->resolveAssessmentScore($enrollment, $w);
            $isExempt = $itemScore['is_exempt'];

            if ($w->is_mandatory && $itemScore['is_missing'] && ! $isExempt) {
                $missingMandatory = true;
            }

            $rawWeight = $hasExplicitWeights
                ? (string) ($w->custom_weight ?? '0')
                : $equalWeightShare;

            $items[$w->id] = [
                'assessment_weight_id' => $w->id,
                'assessment_type' => $w->assessment_type,
                'assessment_id' => $w->assessment_id,
                'is_mandatory' => (bool) $w->is_mandatory,
                'is_exempt' => $isExempt,
                'is_overridden' => $itemScore['is_overridden'],
                'override_reason' => $itemScore['override_reason'] ?? null,
                'score_percentage' => $itemScore['score_percentage'],
                'raw_weight' => $rawWeight,
            ];

            if (! $isExempt) {
                $activeItemWeights[$w->id] = $rawWeight;
            }
        }

        if (empty($activeItemWeights)) {
            // All items exempt -> entire category is exempt
            return [
                'category_id' => $category->id,
                'name' => $category->name,
                'weight' => (string) $category->weight,
                'is_exempt' => true,
                'missing_mandatory' => false,
                'unrounded_score' => '0.'.str_repeat('0', DecimalPrecisionEngine::INTERNAL_SCALE),
                'assessments' => $items,
            ];
        }

        // Proportional redistribution of weights among non-exempt items
        $redistributedWeights = DecimalPrecisionEngine::redistributeWeights(
            $activeItemWeights,
            DecimalPrecisionEngine::INTERNAL_SCALE
        );

        $catRunningSum16 = '0.'.str_repeat('0', DecimalPrecisionEngine::INTERNAL_SCALE);

        foreach ($redistributedWeights as $wId => $normalizedWeight) {
            $score16 = $items[$wId]['score_percentage'];
            $contrib16 = DecimalPrecisionEngine::weightedScore($score16, $normalizedWeight, DecimalPrecisionEngine::INTERNAL_SCALE);
            $catRunningSum16 = DecimalPrecisionEngine::add($catRunningSum16, $contrib16, DecimalPrecisionEngine::INTERNAL_SCALE);

            $items[$wId]['normalized_weight'] = $normalizedWeight;
            $items[$wId]['category_contribution'] = $contrib16;
        }

        return [
            'category_id' => $category->id,
            'name' => $category->name,
            'weight' => (string) $category->weight,
            'is_exempt' => false,
            'missing_mandatory' => $missingMandatory,
            'unrounded_score' => $catRunningSum16,
            'assessments' => array_values($items),
        ];
    }

    /**
     * Calculate score across direct assessment weights (without categories).
     */
    private function calculateDirectWeightsScore(Enrollment $enrollment, $weights): array
    {
        $items = [];
        $activeItemWeights = [];
        $missingMandatory = false;

        $hasExplicitWeights = $weights->contains(fn ($w) => $w->custom_weight !== null);
        $equalWeightShare = ! $hasExplicitWeights
            ? DecimalPrecisionEngine::div('100', (string) $weights->count(), DecimalPrecisionEngine::INTERNAL_SCALE)
            : null;

        foreach ($weights as $w) {
            $itemScore = $this->resolveAssessmentScore($enrollment, $w);
            $isExempt = $itemScore['is_exempt'];

            if ($w->is_mandatory && $itemScore['is_missing'] && ! $isExempt) {
                $missingMandatory = true;
            }

            $rawWeight = $hasExplicitWeights
                ? (string) ($w->custom_weight ?? '0')
                : $equalWeightShare;

            $items[$w->id] = [
                'assessment_weight_id' => $w->id,
                'assessment_type' => $w->assessment_type,
                'assessment_id' => $w->assessment_id,
                'is_mandatory' => (bool) $w->is_mandatory,
                'is_exempt' => $isExempt,
                'is_overridden' => $itemScore['is_overridden'],
                'override_reason' => $itemScore['override_reason'] ?? null,
                'score_percentage' => $itemScore['score_percentage'],
                'raw_weight' => $rawWeight,
            ];

            if (! $isExempt) {
                $activeItemWeights[$w->id] = $rawWeight;
            }
        }

        if (empty($activeItemWeights)) {
            return [
                'unrounded_score' => '0.'.str_repeat('0', DecimalPrecisionEngine::INTERNAL_SCALE),
                'missing_mandatory' => false,
                'assessments' => $items,
            ];
        }

        $redistributedWeights = DecimalPrecisionEngine::redistributeWeights(
            $activeItemWeights,
            DecimalPrecisionEngine::INTERNAL_SCALE
        );

        $runningSum16 = '0.'.str_repeat('0', DecimalPrecisionEngine::INTERNAL_SCALE);

        foreach ($redistributedWeights as $wId => $normalizedWeight) {
            $score16 = $items[$wId]['score_percentage'];
            $contrib16 = DecimalPrecisionEngine::weightedScore($score16, $normalizedWeight, DecimalPrecisionEngine::INTERNAL_SCALE);
            $runningSum16 = DecimalPrecisionEngine::add($runningSum16, $contrib16, DecimalPrecisionEngine::INTERNAL_SCALE);

            $items[$wId]['normalized_weight'] = $normalizedWeight;
            $items[$wId]['contribution'] = $contrib16;
        }

        return [
            'unrounded_score' => $runningSum16,
            'missing_mandatory' => $missingMandatory,
            'assessments' => array_values($items),
        ];
    }

    /**
     * Resolve the earned score percentage for an assessment item.
     * Evaluates active grade overrides, assignment evaluations, quiz submissions, and examination attempts.
     *
     * @return array{
     *     score_percentage: string,
     *     is_missing: bool,
     *     is_exempt: bool,
     *     is_overridden: bool,
     *     override_reason: ?string
     * }
     */
    private function resolveAssessmentScore(Enrollment $enrollment, AssessmentWeight $weight): array
    {
        // 1. Check for active non-reversed GradeOverrideEvent
        $override = GradeOverrideEvent::where('enrollment_id', $enrollment->id)
            ->where('assessment_type', $weight->assessment_type)
            ->where('assessment_id', $weight->assessment_id)
            ->whereNull('reversal_of_event_id')
            ->orderByDesc('sequence_number')
            ->first();

        if ($override !== null) {
            // Check if override signifies exemption
            $isExempt = str_contains(strtolower($override->reason), 'exempt');
            $score = DecimalPrecisionEngine::format($override->effective_score, DecimalPrecisionEngine::INTERNAL_SCALE);

            return [
                'score_percentage' => $score,
                'is_missing' => false,
                'is_exempt' => $isExempt,
                'is_overridden' => true,
                'override_reason' => $override->reason,
            ];
        }

        // 2. Fetch original score from specific assessment tables
        $asmtType = strtolower($weight->assessment_type);
        $asmtId = $weight->assessment_id;

        if ($asmtType === 'quiz' || $asmtType === 'exam') {
            // Check AssessmentAttempt first (supports both quizzes and examinations)
            $attempt = AssessmentAttempt::where('enrollment_id', $enrollment->id)
                ->where('assessment_id', $asmtId)
                ->whereIn('status', [AssessmentAttempt::STATUS_EVALUATED, AssessmentAttempt::STATUS_SUBMITTED])
                ->orderByDesc('score_percentage')
                ->first();

            if ($attempt && $attempt->score_percentage !== null) {
                return [
                    'score_percentage' => DecimalPrecisionEngine::format($attempt->score_percentage, DecimalPrecisionEngine::INTERNAL_SCALE),
                    'is_missing' => false,
                    'is_exempt' => false,
                    'is_overridden' => false,
                    'override_reason' => null,
                ];
            }

            // Fallback to legacy QuizSubmission
            $userId = $enrollment->studentProfile?->user_id;
            if ($userId) {
                $sub = QuizSubmission::where('quiz_id', $asmtId)
                    ->where('user_id', $userId)
                    ->orderByDesc('score')
                    ->first();

                if ($sub && $sub->score !== null) {
                    $pct = DecimalPrecisionEngine::rawToPercentage($sub->score, 100, DecimalPrecisionEngine::INTERNAL_SCALE);

                    return [
                        'score_percentage' => $pct,
                        'is_missing' => false,
                        'is_exempt' => false,
                        'is_overridden' => false,
                        'override_reason' => null,
                    ];
                }
            }
        } elseif ($asmtType === 'assignment') {
            $sub = AssignmentSubmission::where('enrollment_id', $enrollment->id)
                ->where('assignment_id', $asmtId)
                ->orderByDesc('version_number')
                ->first();

            if ($sub) {
                $eval = AssignmentEvaluation::where('submission_id', $sub->id)
                    ->where('status', AssignmentEvaluation::STATUS_GRADED)
                    ->latest('graded_at')
                    ->first();

                if ($eval) {
                    $earned = $eval->points_awarded ?? $eval->raw_score ?? 0;
                    $possible = $eval->points_possible ?? 100;
                    $pct = DecimalPrecisionEngine::rawToPercentage($earned, $possible, DecimalPrecisionEngine::INTERNAL_SCALE);

                    return [
                        'score_percentage' => $pct,
                        'is_missing' => false,
                        'is_exempt' => false,
                        'is_overridden' => false,
                        'override_reason' => null,
                    ];
                }
            }
        }

        // Missing assessment attempt / submission
        return [
            'score_percentage' => '0.'.str_repeat('0', DecimalPrecisionEngine::INTERNAL_SCALE),
            'is_missing' => true,
            'is_exempt' => false,
            'is_overridden' => false,
            'override_reason' => null,
        ];
    }

    /**
     * Map a rounded percentage score to an academic letter grade.
     */
    private function resolveLetterGrade(string $score2Dec): string
    {
        if (DecimalPrecisionEngine::compare($score2Dec, '90.00', 2) >= 0) {
            return 'A';
        }
        if (DecimalPrecisionEngine::compare($score2Dec, '80.00', 2) >= 0) {
            return 'B';
        }
        if (DecimalPrecisionEngine::compare($score2Dec, '70.00', 2) >= 0) {
            return 'C';
        }
        if (DecimalPrecisionEngine::compare($score2Dec, '60.00', 2) >= 0) {
            return 'D';
        }

        return 'F';
    }
}
