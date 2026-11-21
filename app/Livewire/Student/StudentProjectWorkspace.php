<?php

namespace App\Livewire\Student;

use App\Domain\Projects\Models\PracticalProject;
use App\Domain\Projects\Models\ProjectSubmission;
use App\Domain\Projects\Models\ProjectSubmissionAttachment;
use App\Domain\Projects\Services\PracticalProjectService;
use App\Domain\Projects\Services\ProjectSubmissionService;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Project Workspace')]
class StudentProjectWorkspace extends Component
{
    use WithFileUploads;

    public PracticalProject $project;

    // Submission form fields
    public string $websiteUrl = '';

    public string $content = '';

    public string $studentNotes = '';

    public $uploadedFiles = [];

    public string $attachmentType = ProjectSubmissionAttachment::TYPE_SEO_AUDIT_REPORT;

    public function mount(TenantContext $tenantContext, PracticalProject $project, PracticalProjectService $projectService): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->hasRole('student')) {
            abort(403, 'Unauthorized.');
        }

        $profile = $user->studentProfile;
        if (! $profile) {
            abort(403, 'Student profile not found.');
        }

        // Verify eligibility
        $isEligible = $projectService->getAvailableProjectsForStudent($profile)
            ->where('practical_projects.id', $project->id)
            ->exists();

        if (! $isEligible) {
            abort(403, 'You are not authorized to view this project.');
        }

        $this->project = $project;
    }

    public function submitWork(ProjectSubmissionService $submissionService): void
    {
        $this->validate([
            'websiteUrl' => 'nullable|url|max:500',
            'content' => 'required|string|min:20',
            'uploadedFiles.*' => 'nullable|file|max:25600', // max 25MB
        ]);

        $user = Auth::user();

        $attachmentsData = [];
        if (! empty($this->uploadedFiles)) {
            foreach ($this->uploadedFiles as $file) {
                $attachmentsData[] = [
                    'file' => $file,
                    'type' => $this->attachmentType,
                ];
            }
        }

        $submission = $submissionService->submit($this->project, $user, [
            'website_url' => $this->websiteUrl,
            'content' => $this->content,
            'student_notes' => $this->studentNotes,
            'attachments' => $attachmentsData,
        ]);

        // Reset inputs
        $this->websiteUrl = '';
        $this->content = '';
        $this->studentNotes = '';
        $this->uploadedFiles = [];

        session()->flash('message', "Submission v{$submission->version_number} received! It is now queued for evaluation.");
    }

    public function render()
    {
        $user = Auth::user();

        $submissions = ProjectSubmission::query()
            ->where('project_id', $this->project->id)
            ->where('user_id', $user->getKey())
            ->with(['attachments', 'evaluations.evaluator', 'achievement'])
            ->orderByDesc('version_number')
            ->get();

        $latestSubmission = $submissions->first();
        $isApproved = $latestSubmission && $latestSubmission->isApproved();
        $isPending = $latestSubmission && in_array($latestSubmission->status, [ProjectSubmission::STATUS_SUBMITTED, ProjectSubmission::STATUS_UNDER_REVIEW, ProjectSubmission::STATUS_RESUBMITTED], true);

        return view('livewire.student.student-project-workspace', [
            'submissions' => $submissions,
            'latestSubmission' => $latestSubmission,
            'isApproved' => $isApproved,
            'isPending' => $isPending,
            'attachmentTypes' => ProjectSubmissionAttachment::TYPES,
        ]);
    }
}
