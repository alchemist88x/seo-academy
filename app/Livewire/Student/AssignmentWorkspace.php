<?php

namespace App\Livewire\Student;

use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Assignments\Services\AssignmentSubmissionService;
use App\Domain\Courses\Models\Course;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Assignment Workspace')]
class AssignmentWorkspace extends Component
{
    use WithFileUploads;

    public Course $course;

    public Assignment $assignment;

    public ?Enrollment $enrollment = null;

    // Submission Form State
    public string $writtenContent = '';

    public string $studentNotes = '';

    public array $uploadedFiles = [];

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    public function mount(Course $course, Assignment $assignment, TenantContext $tenantContext): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->hasRole('student')) {
            abort(403, 'Unauthorized.');
        }

        $this->course = $course;
        $this->assignment = $assignment->load(['rubricCriteria']);

        // Verify enrollment access
        $studentProfile = $user->studentProfile;
        $this->enrollment = Enrollment::query()
            ->where('student_profile_id', $studentProfile?->id)
            ->where('course_id', $course->id)
            ->allowsCourseAccess()
            ->first();

        if (! $this->enrollment) {
            abort(403, 'You do not have active enrollment access to this course.');
        }

        if (! $this->assignment->isPublished()) {
            abort(404, 'This assignment is not available.');
        }
    }

    public function removeFile(int $index): void
    {
        unset($this->uploadedFiles[$index]);
        $this->uploadedFiles = array_values($this->uploadedFiles);
    }

    public function submitWork(AssignmentSubmissionService $submissionService): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        $user = Auth::user();

        try {
            $submission = $submissionService->submit($this->assignment, $user, [
                'content' => $this->writtenContent,
                'student_notes' => $this->studentNotes,
                'files' => $this->uploadedFiles,
            ]);

            $this->successMessage = sprintf(
                'Work successfully submitted as Version %d! Your deliverable is now under trainer review.',
                $submission->version_number
            );

            // Reset form fields
            $this->writtenContent = '';
            $this->studentNotes = '';
            $this->uploadedFiles = [];
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $user = Auth::user();

        // Retrieve all student submission versions for this assignment
        $submissions = AssignmentSubmission::query()
            ->where('assignment_id', $this->assignment->id)
            ->where('user_id', $user->id)
            ->with(['attachments', 'evaluations' => function ($q) {
                $q->latest('graded_at');
            }])
            ->orderByDesc('version_number')
            ->get();

        $latestSubmission = $submissions->first();
        $canSubmit = $this->assignment->canStudentSubmit($user);

        return view('livewire.student.assignment-workspace', [
            'submissions' => $submissions,
            'latestSubmission' => $latestSubmission,
            'canSubmit' => $canSubmit,
        ]);
    }
}
