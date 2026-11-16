<?php

namespace App\Livewire\Grading;

use App\Domain\Courses\Models\Course;
use App\Domain\Grading\Services\GradebookCalculationService;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Course Gradebook')]
class GradebookMatrix extends Component
{
    use WithPagination;

    public Course $course;

    public string $search = '';

    public string $statusFilter = 'all';

    // Publish Modal State
    public bool $showPublishModal = false;

    public ?string $publishingEnrollmentId = null;

    public ?Enrollment $publishingEnrollment = null;

    public string $publicationReason = '';

    // Calculation details modal state
    public bool $showBreakdownModal = false;

    public ?array $activeBreakdown = null;

    public ?string $activeStudentName = null;

    public ?string $feedbackMessage = null;

    public ?string $errorMessage = null;

    public function mount(Course $course, TenantContext $tenantContext): void
    {
        $this->course = $course;
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        // Authorize: Admin or Assigned Trainer only
        $this->authorizeAccess($user);
    }

    public function calculateDraft(string $enrollmentId, GradebookCalculationService $calcService): void
    {
        $this->clearMessages();
        $user = Auth::user();

        try {
            $enrollment = Enrollment::with('course')->findOrFail($enrollmentId);
            $calcService->calculateEnrollmentGrade($enrollment, actor: $user);
            $this->feedbackMessage = 'Grade draft calculated successfully.';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to calculate draft: '.$e->getMessage();
        }
    }

    public function openPublishModal(string $enrollmentId): void
    {
        $this->clearMessages();
        $this->publishingEnrollmentId = $enrollmentId;
        $this->publishingEnrollment = Enrollment::with(['studentProfile.user', 'course'])->findOrFail($enrollmentId);
        $this->publicationReason = 'Official course grade publication';
        $this->showPublishModal = true;
    }

    public function publishGrade(GradebookCalculationService $calcService): void
    {
        $this->clearMessages();
        $user = Auth::user();

        if (! $user instanceof User || ! $this->publishingEnrollment) {
            return;
        }

        try {
            $idempotencyKey = 'pub_'.$this->publishingEnrollment->id.'_'.now()->timestamp.'_'.Str::random(8);

            $calcService->publishEnrollmentGrade(
                $this->publishingEnrollment,
                $user,
                $idempotencyKey,
                $this->publicationReason ?: 'Published via Gradebook'
            );

            $this->feedbackMessage = 'Grade published successfully.';
            $this->showPublishModal = false;
            $this->publishingEnrollment = null;
            $this->publishingEnrollmentId = null;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to publish grade: '.$e->getMessage();
        }
    }

    public function viewBreakdown(string $enrollmentId): void
    {
        $this->clearMessages();
        $enrollment = Enrollment::with(['studentProfile.user', 'gradeDraft', 'currentGrade.publishedGrade'])->findOrFail($enrollmentId);
        $this->activeStudentName = $enrollment->studentProfile?->user?->name ?? 'Student';

        if ($enrollment->currentGrade?->publishedGrade) {
            $this->activeBreakdown = $enrollment->currentGrade->publishedGrade->calculation_snapshot;
        } elseif ($enrollment->gradeDraft) {
            $this->activeBreakdown = $enrollment->gradeDraft->calculation_breakdown;
        } else {
            $this->activeBreakdown = null;
        }

        $this->showBreakdownModal = true;
    }

    public function closeModals(): void
    {
        $this->showPublishModal = false;
        $this->showBreakdownModal = false;
        $this->publishingEnrollment = null;
        $this->publishingEnrollmentId = null;
        $this->activeBreakdown = null;
    }

    public function render()
    {
        $query = Enrollment::query()
            ->where('course_id', $this->course->id)
            ->with(['studentProfile.user', 'gradeDraft', 'currentGrade.publishedGrade'])
            ->latest('enrolled_at');

        if (! empty($this->search)) {
            $searchTerm = '%'.trim($this->search).'%';
            $query->whereHas('studentProfile.user', function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm);
            });
        }

        if ($this->statusFilter === 'published') {
            $query->whereHas('currentGrade', fn ($q) => $q->where('status', 'published'));
        } elseif ($this->statusFilter === 'draft') {
            $query->where(function ($q) {
                $q->whereDoesntHave('currentGrade')
                    ->orWhereHas('currentGrade', fn ($sub) => $sub->where('status', '!=', 'published'));
            });
        }

        $enrollments = $query->paginate(15);

        return view('livewire.grading.gradebook-matrix', [
            'enrollments' => $enrollments,
            'scheme' => $this->course->activeGradingScheme,
        ]);
    }

    private function authorizeAccess(User $user): void
    {
        if ($user->role === 'admin' || $user->hasRole('admin')) {
            return;
        }

        if ($user->role === 'trainer' || $user->hasRole('trainer')) {
            $isAssigned = $this->course->trainers()
                ->where('users.id', $user->getKey())
                ->exists();

            if ($isAssigned) {
                return;
            }
        }

        abort(403, 'Unauthorized to view gradebook for this course.');
    }

    private function clearMessages(): void
    {
        $this->feedbackMessage = null;
        $this->errorMessage = null;
    }
}
