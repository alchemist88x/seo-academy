<?php

namespace App\Livewire\Admin;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Services\AssignmentManagementService;
use App\Domain\Courses\Models\Course;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Assignments Management')]
class AssignmentsIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $courseFilter = 'all';

    // Modal state for Create / Edit
    public bool $showModal = false;

    public ?string $editingAssignmentId = null;

    public string $title = '';

    public string $courseId = '';

    public string $description = '';

    public string $instructions = '';

    public string $submissionGuidelines = '';

    public string $assignmentType = Assignment::TYPE_FILE_AND_TEXT;

    public float $maxMarks = 100.00;

    public float $passingMarks = 50.00;

    public ?string $dueDate = null;

    public ?string $availableFrom = null;

    public ?string $availableTo = null;

    public string $lateSubmissionPolicy = Assignment::POLICY_ALLOWED_NO_PENALTY;

    public float $latePenaltyPercent = 0.00;

    public ?string $lateHardDeadline = null;

    public ?int $maxAttempts = null;

    public bool $allowResubmission = true;

    public bool $allowMultipleFiles = true;

    public int $maxFileCount = 5;

    public int $maxFileSizeMb = 25;

    public array $allowedFileTypes = ['pdf', 'docx', 'xlsx', 'pptx', 'zip', 'csv', 'txt', 'png', 'jpg', 'webp'];

    public bool $isMandatory = false;

    public string $status = Assignment::STATUS_DRAFT;

    // Rubric criteria builder
    public array $rubricCriteria = [];

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    public function mount(TenantContext $tenantContext): void
    {
        $user = Auth::user();
        if (! $user instanceof User || (! $user->hasRole('admin') && ! $user->hasRole('trainer'))) {
            abort(403, 'Unauthorized.');
        }
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->errorMessage = null;
        $this->successMessage = null;

        $this->editingAssignmentId = null;
        $this->title = '';
        $this->courseId = '';
        $this->description = '';
        $this->instructions = '';
        $this->submissionGuidelines = '';
        $this->assignmentType = Assignment::TYPE_FILE_AND_TEXT;
        $this->maxMarks = 100.00;
        $this->passingMarks = 50.00;
        $this->dueDate = null;
        $this->availableFrom = null;
        $this->availableTo = null;
        $this->lateSubmissionPolicy = Assignment::POLICY_ALLOWED_NO_PENALTY;
        $this->latePenaltyPercent = 0.00;
        $this->lateHardDeadline = null;
        $this->maxAttempts = null;
        $this->allowResubmission = true;
        $this->allowMultipleFiles = true;
        $this->maxFileCount = 5;
        $this->maxFileSizeMb = 25;
        $this->allowedFileTypes = ['pdf', 'docx', 'xlsx', 'pptx', 'zip', 'csv', 'txt', 'png', 'jpg', 'webp'];
        $this->isMandatory = false;
        $this->status = Assignment::STATUS_DRAFT;
        $this->rubricCriteria = [];

        $this->showModal = true;
    }

    public function openEditModal(string $id): void
    {
        $this->resetValidation();
        $this->errorMessage = null;
        $this->successMessage = null;

        $assignment = Assignment::with('rubricCriteria')->findOrFail($id);
        $this->authorizeAction($assignment);

        $this->editingAssignmentId = $assignment->id;
        $this->title = $assignment->title;
        $this->courseId = $assignment->course_id;
        $this->description = (string) $assignment->description;
        $this->instructions = (string) $assignment->instructions;
        $this->submissionGuidelines = (string) $assignment->submissionGuidelines;
        $this->assignmentType = $assignment->assignment_type;
        $this->maxMarks = (float) $assignment->max_marks;
        $this->passingMarks = (float) $assignment->passing_marks;
        $this->dueDate = $assignment->due_date?->format('Y-m-d\TH:i');
        $this->availableFrom = $assignment->available_from?->format('Y-m-d\TH:i');
        $this->availableTo = $assignment->available_to?->format('Y-m-d\TH:i');
        $this->lateSubmissionPolicy = $assignment->late_submission_policy;
        $this->latePenaltyPercent = (float) $assignment->late_penalty_percent;
        $this->lateHardDeadline = $assignment->late_hard_deadline?->format('Y-m-d\TH:i');
        $this->maxAttempts = $assignment->max_attempts;
        $this->allowResubmission = (bool) $assignment->allow_resubmission;
        $this->allowMultipleFiles = (bool) $assignment->allow_multiple_files;
        $this->maxFileCount = (int) $assignment->max_file_count;
        $this->maxFileSizeMb = (int) $assignment->max_file_size_mb;
        $this->allowedFileTypes = $assignment->allowed_file_types ?: ['pdf', 'docx', 'xlsx', 'pptx', 'zip', 'csv', 'txt', 'png', 'jpg', 'webp'];
        $this->isMandatory = (bool) $assignment->is_mandatory;
        $this->status = $assignment->status;

        $this->rubricCriteria = $assignment->rubricCriteria->map(fn ($c) => [
            'id' => $c->id,
            'title' => $c->title,
            'description' => $c->description,
            'max_marks' => (float) $c->max_marks,
        ])->toArray();

        $this->showModal = true;
    }

    public function addRubricCriterion(): void
    {
        $this->rubricCriteria[] = [
            'title' => '',
            'description' => '',
            'max_marks' => 10.0,
        ];
    }

    public function removeRubricCriterion(int $index): void
    {
        unset($this->rubricCriteria[$index]);
        $this->rubricCriteria = array_values($this->rubricCriteria);
    }

    public function save(AssignmentManagementService $service): void
    {
        $this->errorMessage = null;

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'courseId' => ['required', 'uuid'],
            'maxMarks' => ['required', 'numeric', 'min:1', 'max:10000'],
            'passingMarks' => ['required', 'numeric', 'min:0', 'lte:maxMarks'],
            'latePenaltyPercent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'maxFileCount' => ['required', 'integer', 'min:1', 'max:20'],
            'maxFileSizeMb' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        // Validate rubric criteria sum if criteria exist
        if (! empty($this->rubricCriteria)) {
            $totalRubric = array_sum(array_column($this->rubricCriteria, 'max_marks'));
            if (abs($totalRubric - $this->maxMarks) > 0.01) {
                $this->errorMessage = sprintf(
                    'Total rubric marks (%.2f) must equal assignment max marks (%.2f).',
                    $totalRubric,
                    $this->maxMarks
                );

                return;
            }
        }

        $user = Auth::user();
        $academy = Academy::current() ?? Academy::first();

        $payload = [
            'course_id' => $this->courseId,
            'title' => $this->title,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'submission_guidelines' => $this->submissionGuidelines,
            'assignment_type' => $this->assignmentType,
            'max_marks' => $this->maxMarks,
            'passing_marks' => $this->passingMarks,
            'due_date' => $this->dueDate ? $this->dueDate : null,
            'available_from' => $this->availableFrom ? $this->availableFrom : null,
            'available_to' => $this->availableTo ? $this->availableTo : null,
            'late_submission_policy' => $this->lateSubmissionPolicy,
            'late_penalty_percent' => $this->latePenaltyPercent,
            'late_hard_deadline' => $this->lateHardDeadline ? $this->lateHardDeadline : null,
            'max_attempts' => $this->maxAttempts,
            'allow_resubmission' => $this->allowResubmission,
            'allow_multiple_files' => $this->allowMultipleFiles,
            'max_file_count' => $this->maxFileCount,
            'max_file_size_mb' => $this->maxFileSizeMb,
            'allowed_file_types' => $this->allowedFileTypes,
            'rubric_mode' => ! empty($this->rubricCriteria) ? 'criteria' : 'none',
            'is_mandatory' => $this->isMandatory,
            'status' => $this->status,
            'rubric_criteria' => $this->rubricCriteria,
        ];

        try {
            if ($this->editingAssignmentId) {
                $assignment = Assignment::findOrFail($this->editingAssignmentId);
                $this->authorizeAction($assignment);
                $service->updateAssignment($assignment, $payload, $user);
                $this->successMessage = 'Assignment updated successfully.';
            } else {
                $service->createAssignment($payload, $user, $academy);
                $this->successMessage = 'Assignment created successfully.';
            }

            $this->showModal = false;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function togglePublish(string $id, AssignmentManagementService $service): void
    {
        $assignment = Assignment::findOrFail($id);
        $this->authorizeAction($assignment);
        $user = Auth::user();

        if ($assignment->isPublished()) {
            $service->archiveAssignment($assignment, $user);
            $this->successMessage = 'Assignment archived.';
        } else {
            $service->publishAssignment($assignment, $user);
            $this->successMessage = 'Assignment published.';
        }
    }

    protected function authorizeAction(Assignment $assignment): void
    {
        $user = Auth::user();
        if ($user->hasRole('admin')) {
            return;
        }

        if ($user->hasRole('trainer')) {
            $isAssigned = $assignment->course?->trainers()->where('users.id', $user->id)->exists();
            if (! $isAssigned) {
                abort(403, 'You are not assigned to this course.');
            }

            return;
        }

        abort(403, 'Unauthorized.');
    }

    public function render()
    {
        $user = Auth::user();
        $academy = Academy::current() ?? Academy::first();

        $coursesQuery = Course::query()->forAcademy($academy);
        if ($user->hasRole('trainer') && ! $user->hasRole('admin')) {
            $coursesQuery->assignedToTrainer($user);
        }
        $availableCourses = $coursesQuery->orderBy('title')->get(['id', 'title']);

        $query = Assignment::query()
            ->with(['course', 'creator'])
            ->withCount('submissions');

        if ($user->hasRole('trainer') && ! $user->hasRole('admin')) {
            $query->whereIn('course_id', $availableCourses->pluck('id'));
        }

        if ($this->search !== '') {
            $query->where('title', 'like', '%'.$this->search.'%');
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->courseFilter !== 'all') {
            $query->where('course_id', $this->courseFilter);
        }

        $assignments = $query->orderByDesc('created_at')->paginate(15);

        return view('livewire.admin.assignments-index', [
            'assignments' => $assignments,
            'courses' => $availableCourses,
        ]);
    }
}
