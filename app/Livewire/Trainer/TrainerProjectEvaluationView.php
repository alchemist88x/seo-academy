<?php

namespace App\Livewire\Trainer;

use App\Domain\Projects\Models\ProjectEvaluation;
use App\Domain\Projects\Models\ProjectSubmission;
use App\Domain\Projects\Services\ProjectEvaluationService;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Evaluate Project Submission')]
class TrainerProjectEvaluationView extends Component
{
    public ProjectSubmission $submission;

    public string $decision = ProjectEvaluation::STATUS_APPROVED;

    public float $pointsAwarded = 0.0;

    public array $criteriaRatings = [];

    public string $trainerFeedback = '';

    public string $improvementRecommendations = '';

    public string $revisionReason = '';

    public function mount(TenantContext $tenantContext, ProjectSubmission $submission, ProjectEvaluationService $evaluationService): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->is_active) {
            abort(403, 'Unauthorized.');
        }

        // Authorize evaluator
        $evaluationService->authorizeEvaluator($submission, $user);

        $this->submission = $submission->load(['project.criteria', 'attachments', 'studentProfile.user', 'evaluations.evaluator']);

        $project = $submission->project;
        $latestEval = $submission->latestEvaluation;

        if ($latestEval) {
            $this->decision = $latestEval->status;
            $this->pointsAwarded = (float) $latestEval->points_awarded;
            $this->trainerFeedback = $latestEval->trainer_feedback ?? '';
            $this->improvementRecommendations = $latestEval->improvement_recommendations ?? '';
            $this->revisionReason = $latestEval->revision_reason ?? '';

            if (! empty($latestEval->criteria_feedback)) {
                foreach ($latestEval->criteria_feedback as $cf) {
                    $this->criteriaRatings[$cf['criterion_id']] = [
                        'awarded_marks' => (float) $cf['awarded_marks'],
                        'feedback' => $cf['feedback'] ?? '',
                    ];
                }
            }
        } else {
            $this->pointsAwarded = (float) ($project?->passing_marks ?? 50.0);
            if ($project && $project->criteria->isNotEmpty()) {
                foreach ($project->criteria as $crit) {
                    $this->criteriaRatings[$crit->id] = [
                        'awarded_marks' => (float) $crit->max_marks,
                        'feedback' => '',
                    ];
                }
            }
        }
    }

    public function submitEvaluation(ProjectEvaluationService $evaluationService)
    {
        $user = Auth::user();

        if ($this->decision === ProjectEvaluation::STATUS_REVISION_REQUESTED) {
            $this->validate([
                'revisionReason' => 'required|string|min:5',
            ]);
        }

        $evaluation = $evaluationService->evaluate($this->submission, $user, [
            'decision' => $this->decision,
            'points_awarded' => $this->pointsAwarded,
            'criteria_ratings' => $this->criteriaRatings,
            'trainer_feedback' => $this->trainerFeedback,
            'improvement_recommendations' => $this->improvementRecommendations,
            'revision_reason' => $this->revisionReason,
        ]);

        session()->flash('message', "Evaluation successfully saved with decision: {$evaluation->status}.");

        if ($user->hasRole('admin')) {
            return redirect()->route('admin.projects.index');
        }

        return redirect()->route('trainer.projects.index');
    }

    public function render()
    {
        return view('livewire.trainer.trainer-project-evaluation-view');
    }
}
