<?php

namespace App\Livewire\Student;

use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Assessments\Services\AssessmentService;
use App\Domain\Courses\Models\Course;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Course Assessment')]
class AssessmentPlayer extends Component
{
    public Course $course;

    public Quiz $assessment;

    public ?Enrollment $enrollment = null;

    public array $assessmentData = [];

    public ?AssessmentAttempt $currentAttempt = null;

    public array $answers = [];

    public array $revisions = [];

    public bool $isSubmitted = false;

    public bool $isExpired = false;

    public ?array $submissionResult = null;

    public string $errorMessage = '';

    public string $saveStatus = '';

    public ?int $remainingSeconds = null;

    public function mount(
        Course $course,
        Quiz $assessment,
        TenantContext $tenantContext,
        AssessmentService $assessmentService
    ): void {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->is_active) {
            abort(403, 'User account is inactive or unauthenticated.');
        }

        $academy = $tenantContext->getAcademy();
        if ($academy === null || (string) $course->academy_id !== (string) $academy->getKey()) {
            abort(404, 'Course not found in the active academy.');
        }

        $studentProfile = $user->studentProfile;
        if (! $studentProfile) {
            abort(403, 'No student profile exists for this account.');
        }

        $this->enrollment = $studentProfile->enrollments()
            ->where('course_id', $course->id)
            ->first();

        if (! $this->enrollment || ! $this->enrollment->allowsCourseAccess()) {
            abort(403, 'You do not have an active enrollment for this course.');
        }

        $this->course = $course;
        $this->assessment = $assessment;

        $this->loadAssessmentData($assessmentService);
    }

    public function startAttempt(AssessmentService $assessmentService): void
    {
        $user = Auth::user();
        if (! $user || ! $this->enrollment) {
            return;
        }

        try {
            $this->errorMessage = '';
            $this->isExpired = false;
            $this->currentAttempt = $assessmentService->createAttempt($this->enrollment, $this->assessment, $user);
            $this->answers = [];
            $this->revisions = [];
            $this->isSubmitted = false;
            $this->submissionResult = null;
            $this->saveStatus = '';
            $this->loadAssessmentData($assessmentService);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function updatedAnswers(mixed $value, string $key): void
    {
        $this->saveAnswer($key, $value, app(AssessmentService::class));
    }

    public function saveAnswer(string $questionId, mixed $answer, AssessmentService $assessmentService): void
    {
        $user = Auth::user();
        if (! $user || ! $this->currentAttempt) {
            return;
        }

        $this->answers[$questionId] = $answer;
        $this->revisions[$questionId] = ($this->revisions[$questionId] ?? 0) + 1;
        $idempotencyKey = (string) Str::uuid();
        $this->saveStatus = 'saving';

        try {
            $assessmentService->saveAttemptAnswer(
                attempt: $this->currentAttempt,
                questionId: $questionId,
                studentAnswer: $answer,
                revision: $this->revisions[$questionId],
                idempotencyKey: $idempotencyKey,
                user: $user
            );
            $this->saveStatus = 'saved';
        } catch (\Throwable $e) {
            $this->saveStatus = 'error';
            $this->errorMessage = $e->getMessage();
            if (str_contains(strtolower($e->getMessage()), 'expired')) {
                $this->isExpired = true;
                $this->currentAttempt = null;
                $this->loadAssessmentData($assessmentService);
            }
        }
    }

    public function checkTimer(AssessmentService $assessmentService): void
    {
        if (! $this->currentAttempt) {
            return;
        }

        $this->currentAttempt->refresh();

        if (! $this->currentAttempt->isInProgress()) {
            $this->isExpired = true;
            $this->currentAttempt = null;
            $this->loadAssessmentData($assessmentService);

            return;
        }

        if ($this->currentAttempt->expires_at) {
            $now = Carbon::now();
            $this->remainingSeconds = max(0, $now->diffInSeconds($this->currentAttempt->expires_at, false));

            if ($this->currentAttempt->finalize_after && $now->greaterThanOrEqualTo($this->currentAttempt->finalize_after)) {
                $assessmentService->finalizationService->finalizeAttempt($this->currentAttempt, 'timeout_expired');
                $this->isExpired = true;
                $this->currentAttempt = null;
                $this->loadAssessmentData($assessmentService);
            }
        }
    }

    public function submitAttempt(AssessmentService $assessmentService): void
    {
        $user = Auth::user();
        if (! $user || ! $this->currentAttempt) {
            return;
        }

        try {
            $this->errorMessage = '';
            $attempt = $assessmentService->submitAttempt($this->currentAttempt, $this->answers, $user);
            $this->isSubmitted = true;
            $this->currentAttempt = null;
            $this->submissionResult = [
                'status' => $attempt->status,
                'score_percentage' => (float) $attempt->score_percentage,
                'earned_points' => (float) $attempt->earned_points,
                'total_points' => (float) $attempt->total_points,
                'passed' => (bool) $attempt->passed,
                'requires_manual_grading' => (bool) $attempt->requires_manual_grading,
            ];

            $this->loadAssessmentData($assessmentService);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            if (str_contains(strtolower($e->getMessage()), 'expired')) {
                $this->isExpired = true;
                $this->currentAttempt = null;
                $this->loadAssessmentData($assessmentService);
            }
        }
    }

    public function render()
    {
        return view('livewire.student.assessment-player');
    }

    private function loadAssessmentData(AssessmentService $assessmentService): void
    {
        $user = Auth::user();
        if (! $user || ! $this->enrollment) {
            return;
        }

        $this->assessmentData = $assessmentService->getStudentAssessmentData($this->assessment, $this->enrollment, $user);

        // Resume in-progress attempt if exists and currentAttempt not already set
        if (! $this->currentAttempt && ! empty($this->assessmentData['in_progress_attempt'])) {
            $this->currentAttempt = AssessmentAttempt::find($this->assessmentData['in_progress_attempt']['id']);
        }

        if ($this->currentAttempt && ! empty($this->assessmentData['in_progress_attempt'])) {
            $inProgress = $this->assessmentData['in_progress_attempt'];
            $this->remainingSeconds = $inProgress['remaining_seconds'] ?? null;
            if (! empty($inProgress['answers']) && is_array($inProgress['answers'])) {
                foreach ($inProgress['answers'] as $qId => $val) {
                    if (! isset($this->answers[$qId])) {
                        $this->answers[$qId] = $val;
                    }
                }
            }
        }
    }
}
