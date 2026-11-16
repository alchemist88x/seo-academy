<?php

namespace App\Livewire\Admin;

use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Assessments\Services\AssessmentService;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Assessment Grading')]
class AssessmentGrading extends Component
{
    public Quiz $assessment;

    public ?AssessmentAttempt $selectedAttempt = null;

    public array $manualGrades = [];

    public ?string $attemptFeedback = null;

    public ?string $feedbackMessage = null;

    public function mount(Quiz $assessment, TenantContext $tenantContext): void
    {
        $user = Auth::user();
        if (! $user instanceof User || (! $user->hasRole('admin') && ! $user->hasRole('trainer'))) {
            abort(403, 'Unauthorized.');
        }

        if ($user->hasRole('trainer') && ! $user->hasRole('admin')) {
            $course = $assessment->course ?? $assessment->module?->course ?? $assessment->lesson?->module?->course;
            if ($course && ! $course->trainers()->where('users.id', $user->id)->exists()) {
                abort(403, 'Trainers can only evaluate assessments for courses assigned to them.');
            }
        }

        $academy = $tenantContext->getAcademy();
        if ($academy && $assessment->academy_id !== $academy->id) {
            abort(404, 'Assessment not found.');
        }

        $this->assessment = $assessment;
    }

    public function selectAttempt(string $attemptId): void
    {
        $attempt = AssessmentAttempt::query()
            ->with(['student', 'enrollment', 'answers.question', 'answers.bankQuestion'])
            ->where('assessment_id', $this->assessment->id)
            ->findOrFail($attemptId);

        $this->selectedAttempt = $attempt;
        $this->attemptFeedback = $attempt->feedback;
        $this->manualGrades = [];

        foreach ($attempt->answers as $answer) {
            $this->manualGrades[$answer->id] = [
                'points_awarded' => $answer->points_awarded ?? 0,
                'feedback' => $answer->feedback ?? '',
            ];
        }
    }

    public function saveGrading(AssessmentService $assessmentService): void
    {
        if (! $this->selectedAttempt) {
            return;
        }

        $user = Auth::user();

        $assessmentService->gradeAttempt(
            attempt: $this->selectedAttempt,
            evaluator: $user,
            grades: $this->manualGrades,
            overallFeedback: $this->attemptFeedback
        );

        $this->feedbackMessage = 'Attempt graded successfully.';
        $this->selectedAttempt->refresh();
    }

    public function render()
    {
        $attempts = AssessmentAttempt::query()
            ->with(['student', 'enrollment'])
            ->where('assessment_id', $this->assessment->id)
            ->latest()
            ->paginate(20);

        return view('livewire.admin.assessment-grading', [
            'attempts' => $attempts,
        ]);
    }
}
