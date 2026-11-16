<?php

namespace App\Livewire\Admin;

use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Assignments\Services\AssignmentGradingService;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Grade Practical Assignment')]
class AssignmentGradingView extends Component
{
    public Assignment $assignment;

    public AssignmentSubmission $submission;

    // Version switcher
    public string $selectedSubmissionId;

    // Grading Form State
    public float $pointsAwarded = 0.0;

    public string $overallFeedback = '';

    public string $internalNotes = '';

    public array $rubricEvaluations = [];

    // Revision Request Modal
    public bool $showRevisionModal = false;

    public string $revisionReason = '';

    // Admin Override
    public bool $isOverride = false;

    public string $overrideReason = '';

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    public function mount(Assignment $assignment, AssignmentSubmission $submission, TenantContext $tenantContext): void
    {
        $user = Auth::user();
        if (! $user instanceof User || (! $user->hasRole('admin') && ! $user->hasRole('trainer'))) {
            abort(403, 'Unauthorized.');
        }

        if ($user->hasRole('trainer') && ! $user->hasRole('admin')) {
            $isAssigned = $assignment->course?->trainers()->where('users.id', $user->id)->exists();
            if (! $isAssigned) {
                abort(403, 'You are not assigned to grade this course.');
            }
        }

        $this->assignment = $assignment;
        $this->submission = $submission->load(['user.studentProfile', 'attachments', 'evaluations.evaluator']);
        $this->selectedSubmissionId = $submission->id;

        $this->initForm();
    }

    public function switchVersion(string $submissionId): void
    {
        $this->submission = AssignmentSubmission::query()
            ->where('assignment_id', $this->assignment->id)
            ->where('id', $submissionId)
            ->with(['user.studentProfile', 'attachments', 'evaluations.evaluator'])
            ->firstOrFail();

        $this->selectedSubmissionId = $submissionId;
        $this->initForm();
    }

    protected function initForm(): void
    {
        $latestEval = $this->submission->latestEvaluation;

        $this->pointsAwarded = $latestEval ? (float) $latestEval->points_awarded : 0.0;
        $this->overallFeedback = $latestEval ? (string) $latestEval->overall_feedback : '';
        $this->internalNotes = $latestEval ? (string) $latestEval->internal_notes : '';

        $this->rubricEvaluations = [];
        $rubricSnapshot = $latestEval?->rubric_snapshot;

        foreach ($this->assignment->rubricCriteria as $crit) {
            $existing = null;
            if (is_array($rubricSnapshot)) {
                foreach ($rubricSnapshot as $item) {
                    if (($item['criterion_id'] ?? null) === $crit->id) {
                        $existing = $item;
                        break;
                    }
                }
            }

            $this->rubricEvaluations[$crit->id] = [
                'awarded_marks' => $existing ? (float) $existing['awarded_marks'] : (float) $crit->max_marks,
                'feedback' => $existing['feedback'] ?? '',
            ];
        }

        $this->recomputeTotalFromRubric();
    }

    public function updatedRubricEvaluations(): void
    {
        $this->recomputeTotalFromRubric();
    }

    protected function recomputeTotalFromRubric(): void
    {
        if (! empty($this->rubricEvaluations)) {
            $total = 0.0;
            foreach ($this->rubricEvaluations as $eval) {
                $total += (float) ($eval['awarded_marks'] ?? 0.0);
            }
            $this->pointsAwarded = round($total, 2);
        }
    }

    public function submitGrade(AssignmentGradingService $gradingService): void
    {
        $this->errorMessage = null;
        $user = Auth::user();

        $this->validate([
            'pointsAwarded' => ['required', 'numeric', 'min:0', 'max:'.$this->assignment->max_marks],
            'overallFeedback' => ['nullable', 'string'],
            'internalNotes' => ['nullable', 'string'],
        ]);

        if ($this->isOverride && empty(trim($this->overrideReason))) {
            $this->errorMessage = 'Please provide an audit explanation for this administrative grade override.';

            return;
        }

        try {
            $gradingService->grade($this->submission, $user, [
                'points_awarded' => $this->pointsAwarded,
                'overall_feedback' => $this->overallFeedback,
                'internal_notes' => $this->internalNotes,
                'rubric_evaluations' => ! empty($this->rubricEvaluations) ? $this->rubricEvaluations : null,
                'is_override' => $this->isOverride,
                'override_reason' => $this->overrideReason,
            ]);

            $this->successMessage = 'Grade and evaluation recorded successfully.';
            $this->submission->refresh();
            $this->submission->load(['evaluations.evaluator']);
            $this->isOverride = false;
            $this->overrideReason = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function openRevisionModal(): void
    {
        $this->revisionReason = '';
        $this->showRevisionModal = true;
    }

    public function submitRevisionRequest(AssignmentGradingService $gradingService): void
    {
        $this->errorMessage = null;
        $user = Auth::user();

        $this->validate([
            'revisionReason' => ['required', 'string', 'min:5'],
        ]);

        try {
            $gradingService->requestRevision(
                $this->submission,
                $user,
                $this->revisionReason,
                $this->internalNotes ?: null
            );

            $this->successMessage = 'Revision requested. The student has been notified.';
            $this->showRevisionModal = false;
            $this->submission->refresh();
            $this->submission->load(['evaluations.evaluator']);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        // Get all versions for this student
        $allVersions = AssignmentSubmission::query()
            ->where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->submission->user_id)
            ->orderByDesc('version_number')
            ->get(['id', 'version_number', 'submitted_at', 'status', 'is_late', 'grade']);

        return view('livewire.admin.assignment-grading-view', [
            'allVersions' => $allVersions,
            'evaluations' => $this->submission->evaluations,
        ]);
    }
}
