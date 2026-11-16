<?php

namespace App\Livewire\Admin;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Students\Services\EnrollmentManagementService;
use App\Domain\Tenants\Services\TenantContext;
use App\Domain\Trainers\Models\TrainerProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Enrollment Management')]
class EnrollmentsIndex extends Component
{
    use WithPagination;

    // Filter and search state
    public string $search = '';

    public string $statusFilter = '';

    public string $courseFilter = '';

    public string $trainerFilter = '';

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    // Single Assign Modal state
    public string $assignStudentId = '';

    public string $assignCourseId = '';

    public string $assignStartsAt = '';

    public string $assignExpiresAt = '';

    public string $assignStatus = 'active';

    public string $assignNotes = '';

    public ?string $assignWarningPrerequisite = null;

    public ?string $assignWarningCapacity = null;

    // Bulk Assign Modal state
    public array $bulkStudentIds = [];

    public string $bulkCourseId = '';

    public string $bulkStartsAt = '';

    public string $bulkExpiresAt = '';

    public string $bulkStatus = 'active';

    public string $bulkNotes = '';

    public string $bulkStudentSearch = '';

    /** @var array{assigned_count: int, skipped_count: int, skipped_details: array<int, mixed>}|null */
    public ?array $bulkSummary = null;

    // Status Change / Action Modal state
    public ?string $actionEnrollmentId = null;

    public ?Enrollment $selectedEnrollment = null;

    public string $actionType = ''; // 'activate', 'suspend', 'reactivate', 'cancel', 'complete', 'extend_expiry'

    public string $actionReason = '';

    public string $actionNewExpiry = '';

    // View Details Modal state
    public ?Enrollment $viewingEnrollment = null;

    public $viewAuditLogs = null;

    public function mount(TenantContext $tenantContext): void
    {
        $this->resolveAcademy($tenantContext);
        $this->authorize('viewAny', Enrollment::class);
        $this->assignStartsAt = now()->toDateString();
        $this->bulkStartsAt = now()->toDateString();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCourseFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTrainerFilter(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'courseFilter', 'trainerFilter']);
        $this->sortField = 'created_at';
        $this->sortDirection = 'desc';
        $this->resetPage();
    }

    // Warnings for single assign
    public function updatedAssignCourseId(): void
    {
        $this->evaluateSingleAssignWarnings();
    }

    public function updatedAssignStudentId(): void
    {
        $this->evaluateSingleAssignWarnings();
    }

    public function evaluateSingleAssignWarnings(): void
    {
        $this->assignWarningPrerequisite = null;
        $this->assignWarningCapacity = null;

        if (! empty($this->assignCourseId)) {
            $course = Course::with('prerequisites')->find($this->assignCourseId);
            if ($course) {
                // Capacity warning
                if ($course->enrollment_limit !== null && $course->enrollment_limit > 0) {
                    $count = Enrollment::query()
                        ->where('course_id', $course->id)
                        ->whereIn('status', [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_PENDING])
                        ->count();

                    if ($count >= $course->enrollment_limit) {
                        $this->assignWarningCapacity = "Course capacity reached: {$count} of {$course->enrollment_limit} seats filled. Further enrollments will be blocked by system rules.";
                    } elseif ($count + 1 === $course->enrollment_limit) {
                        $this->assignWarningCapacity = "Notice: Only 1 seat remains in this course ({$count}/{$course->enrollment_limit}).";
                    }
                }

                // Prerequisite warning
                if (! empty($this->assignStudentId) && $course->prerequisites->isNotEmpty()) {
                    $student = StudentProfile::find($this->assignStudentId);
                    if ($student) {
                        $satisfiedPrereqIds = Enrollment::query()
                            ->where('student_profile_id', $student->id)
                            ->whereIn('course_id', $course->prerequisites->pluck('id'))
                            ->where(function (Builder $q): void {
                                $q->where('status', Enrollment::STATUS_COMPLETED)
                                    ->orWhere(function (Builder $activeQ): void {
                                        $activeQ->valid();
                                    });
                            })
                            ->pluck('course_id')
                            ->all();

                        $unmet = $course->prerequisites->filter(fn ($p) => ! in_array($p->id, $satisfiedPrereqIds, true));
                        if ($unmet->isNotEmpty()) {
                            $unmetTitles = $unmet->pluck('title')->join(', ');
                            $this->assignWarningPrerequisite = "Prerequisite warning: Student has not satisfied: {$unmetTitles}. Assigning will be blocked unless prerequisite is satisfied.";
                        }
                    }
                }
            }
        }
    }

    // Modal Triggers
    public function openSingleAssignModal(): void
    {
        $this->resetValidation();
        $this->assignStudentId = '';
        $this->assignCourseId = '';
        $this->assignStartsAt = now()->toDateString();
        $this->assignExpiresAt = '';
        $this->assignStatus = 'active';
        $this->assignNotes = '';
        $this->assignWarningPrerequisite = null;
        $this->assignWarningCapacity = null;

        $this->dispatch('open-modal', 'enrollment-single-modal');
    }

    public function saveSingleAssign(EnrollmentManagementService $service): void
    {
        $this->authorize('create', Enrollment::class);

        $this->validate([
            'assignStudentId' => ['required', 'uuid', 'exists:student_profiles,id'],
            'assignCourseId' => ['required', 'uuid', 'exists:courses,id'],
            'assignStartsAt' => ['nullable', 'date'],
            'assignExpiresAt' => ['nullable', 'date', 'after_or_equal:assignStartsAt'],
            'assignStatus' => ['required', 'in:active,pending'],
            'assignNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $student = StudentProfile::findOrFail($this->assignStudentId);
        $course = Course::findOrFail($this->assignCourseId);
        $admin = Auth::user();

        try {
            $service->assign($student, $course, $admin, [
                'starts_at' => $this->assignStartsAt !== '' ? $this->assignStartsAt : null,
                'expires_at' => $this->assignExpiresAt !== '' ? $this->assignExpiresAt : null,
                'status' => $this->assignStatus,
                'notes' => $this->assignNotes !== '' ? $this->assignNotes : null,
            ]);

            $this->dispatch('toast', type: 'success', message: 'Enrollment assigned successfully.');
            $this->dispatch('close-modal', 'enrollment-single-modal');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError('assign_'.$key, $messages[0]);
            }
        } catch (\Throwable $e) {
            $this->addError('assign_general', $e->getMessage());
        }
    }

    public function openBulkAssignModal(): void
    {
        $this->resetValidation();
        $this->bulkStudentIds = [];
        $this->bulkCourseId = '';
        $this->bulkStartsAt = now()->toDateString();
        $this->bulkExpiresAt = '';
        $this->bulkStatus = 'active';
        $this->bulkNotes = '';
        $this->bulkStudentSearch = '';
        $this->bulkSummary = null;

        $this->dispatch('open-modal', 'enrollment-bulk-modal');
    }

    public function toggleAllBulkStudents(array $visibleStudentIds): void
    {
        if (count($this->bulkStudentIds) === count($visibleStudentIds)) {
            $this->bulkStudentIds = [];
        } else {
            $this->bulkStudentIds = $visibleStudentIds;
        }
    }

    public function saveBulkAssign(EnrollmentManagementService $service): void
    {
        $this->authorize('create', Enrollment::class);

        $this->validate([
            'bulkStudentIds' => ['required', 'array', 'min:1'],
            'bulkStudentIds.*' => ['uuid', 'exists:student_profiles,id'],
            'bulkCourseId' => ['required', 'uuid', 'exists:courses,id'],
            'bulkStartsAt' => ['nullable', 'date'],
            'bulkExpiresAt' => ['nullable', 'date', 'after_or_equal:bulkStartsAt'],
            'bulkStatus' => ['required', 'in:active,pending'],
            'bulkNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $course = Course::findOrFail($this->bulkCourseId);
        $admin = Auth::user();

        try {
            $result = $service->bulkAssign($this->bulkStudentIds, $course, $admin, [
                'starts_at' => $this->bulkStartsAt !== '' ? $this->bulkStartsAt : null,
                'expires_at' => $this->bulkExpiresAt !== '' ? $this->bulkExpiresAt : null,
                'status' => $this->bulkStatus,
                'notes' => $this->bulkNotes !== '' ? $this->bulkNotes : null,
            ]);

            $this->bulkSummary = [
                'assigned_count' => count($result['assigned']),
                'skipped_count' => count($result['skipped']),
                'skipped_details' => $result['skipped'],
            ];

            $this->dispatch('toast', type: 'success', message: "Bulk assignment complete: {$this->bulkSummary['assigned_count']} assigned, {$this->bulkSummary['skipped_count']} skipped.");
        } catch (\Throwable $e) {
            $this->addError('bulk_general', $e->getMessage());
        }
    }

    public function openActionModal(string $enrollmentId, string $actionType): void
    {
        $this->resetValidation();
        $this->actionEnrollmentId = $enrollmentId;
        $this->actionType = $actionType;
        $this->actionReason = '';
        $this->actionNewExpiry = '';

        $this->selectedEnrollment = Enrollment::with(['studentProfile.user', 'course'])->findOrFail($enrollmentId);

        if ($actionType === 'extend_expiry' && $this->selectedEnrollment->expires_at) {
            $this->actionNewExpiry = $this->selectedEnrollment->expires_at->toDateString();
        }

        $this->dispatch('open-modal', 'enrollment-action-modal');
    }

    public function executeAction(EnrollmentManagementService $service): void
    {
        if (! $this->actionEnrollmentId) {
            return;
        }

        $enrollment = Enrollment::with('course')->findOrFail($this->actionEnrollmentId);
        $this->authorize('update', $enrollment);
        $admin = Auth::user();

        try {
            if ($this->actionType === 'extend_expiry') {
                $this->validate([
                    'actionNewExpiry' => ['required', 'date'],
                ]);
                $service->extendExpiry($enrollment, $this->actionNewExpiry, $admin);
                $this->dispatch('toast', type: 'success', message: 'Enrollment expiry date updated.');
            } else {
                $targetStatus = match ($this->actionType) {
                    'activate', 'reactivate' => Enrollment::STATUS_ACTIVE,
                    'suspend' => Enrollment::STATUS_SUSPENDED,
                    'cancel' => Enrollment::STATUS_CANCELLED,
                    'complete' => Enrollment::STATUS_COMPLETED,
                    default => $this->actionType,
                };

                $params = [];
                if ($targetStatus === Enrollment::STATUS_SUSPENDED) {
                    $this->validate([
                        'actionReason' => ['required', 'string', 'min:3', 'max:255'],
                    ]);
                    $params['reason'] = $this->actionReason;
                } elseif ($targetStatus === Enrollment::STATUS_CANCELLED) {
                    $this->validate([
                        'actionReason' => ['required', 'string', 'min:3', 'max:255'],
                    ]);
                    $params['reason'] = $this->actionReason;
                }

                $service->transitionStatus($enrollment, $targetStatus, $admin, $params);
                $this->dispatch('toast', type: 'success', message: 'Enrollment status updated to '.ucfirst($targetStatus).'.');
            }

            $this->dispatch('close-modal', 'enrollment-action-modal');
            $this->actionEnrollmentId = null;
            $this->selectedEnrollment = null;
        } catch (\DomainException $e) {
            $this->addError('action_general', $e->getMessage());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->addError('action_general', $e->getMessage());
        }
    }

    public function openViewModal(string $enrollmentId): void
    {
        $this->viewingEnrollment = Enrollment::with([
            'studentProfile.user',
            'course.category',
            'course.trainers',
            'course.modules.lessons',
            'assignedBy',
        ])->findOrFail($enrollmentId);

        $this->authorize('view', $this->viewingEnrollment);

        $this->viewAuditLogs = AuditLog::query()
            ->where('subject_type', Enrollment::class)
            ->where('subject_id', $enrollmentId)
            ->with('user')
            ->latest('created_at')
            ->get();

        $this->dispatch('open-modal', 'enrollment-view-modal');
    }

    public function render(TenantContext $tenantContext)
    {
        $academy = $this->resolveAcademy($tenantContext);

        // Metrics calculations
        $metrics = [
            'total' => Enrollment::query()->where(function ($q) use ($academy) {
                $q->where('academy_id', $academy->id)->orWhereNull('academy_id');
            })->count(),
            'active' => Enrollment::query()->where(function ($q) use ($academy) {
                $q->where('academy_id', $academy->id)->orWhereNull('academy_id');
            })->where('status', Enrollment::STATUS_ACTIVE)->count(),
            'pending' => Enrollment::query()->where(function ($q) use ($academy) {
                $q->where('academy_id', $academy->id)->orWhereNull('academy_id');
            })->where('status', Enrollment::STATUS_PENDING)->count(),
            'expired_suspended' => Enrollment::query()->where(function ($q) use ($academy) {
                $q->where('academy_id', $academy->id)->orWhereNull('academy_id');
            })->whereIn('status', [Enrollment::STATUS_EXPIRED, Enrollment::STATUS_SUSPENDED])->count(),
        ];

        // Base query with academy scoping
        $query = Enrollment::query()
            ->where(function (Builder $q) use ($academy): void {
                $q->where('academy_id', $academy->id)
                    ->orWhereNull('academy_id');
            })
            ->with(['studentProfile.user', 'course.trainers', 'assignedBy']);

        // Search filter
        if ($this->search !== '') {
            $term = trim($this->search);
            $query->where(function (Builder $q) use ($term): void {
                $q->whereHas('studentProfile', function (Builder $studentQuery) use ($term): void {
                    $studentQuery->where('student_code', 'like', "%{$term}%")
                        ->orWhereHas('user', function (Builder $userQuery) use ($term): void {
                            $userQuery->where('name', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%");
                        });
                })->orWhereHas('course', function (Builder $courseQuery) use ($term): void {
                    $courseQuery->where('title', 'like', "%{$term}%");
                });
            });
        }

        // Status filter
        if ($this->statusFilter !== '') {
            $query->where('status', strtolower($this->statusFilter));
        }

        // Course filter
        if ($this->courseFilter !== '') {
            $query->where('course_id', $this->courseFilter);
        }

        // Trainer filter
        if ($this->trainerFilter !== '') {
            $trainerId = $this->trainerFilter;
            $query->whereHas('course.trainers', function (Builder $tq) use ($trainerId): void {
                $tq->where('users.id', $trainerId);
            });
        }

        // Sorting
        $allowedSorts = ['created_at', 'enrolled_at', 'starts_at', 'expires_at', 'status'];
        $sortField = in_array($this->sortField, $allowedSorts, true) ? $this->sortField : 'created_at';
        $sortDir = strtolower($this->sortDirection) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $sortDir);

        $enrollments = $query->paginate(15);

        // Course list for dropdowns
        $courses = Course::query()
            ->where('status', 'Published')
            ->orderBy('title')
            ->get();

        // Student list for single assign dropdown
        $students = StudentProfile::query()
            ->with('user')
            ->whereHas('user', fn (Builder $uq) => $uq->where('is_active', true))
            ->orderBy('student_code')
            ->get();

        // Filtered students for bulk assign multi-select
        $bulkStudentQuery = StudentProfile::query()
            ->with('user')
            ->whereHas('user', fn (Builder $uq) => $uq->where('is_active', true));

        if ($this->bulkStudentSearch !== '') {
            $sterm = trim($this->bulkStudentSearch);
            $bulkStudentQuery->where(function (Builder $sq) use ($sterm): void {
                $sq->where('student_code', 'like', "%{$sterm}%")
                    ->orWhereHas('user', function (Builder $uq) use ($sterm): void {
                        $uq->where('name', 'like', "%{$sterm}%")
                            ->orWhere('email', 'like', "%{$sterm}%");
                    });
            });
        }
        $bulkStudents = $bulkStudentQuery->orderBy('student_code')->get();

        // Trainer list for filter dropdown
        $trainers = TrainerProfile::query()->with('user')->get();

        return view('livewire.admin.enrollments-index', [
            'academy' => $academy,
            'metrics' => $metrics,
            'enrollments' => $enrollments,
            'courses' => $courses,
            'students' => $students,
            'bulkStudents' => $bulkStudents,
            'trainers' => $trainers,
        ]);
    }

    private function resolveAcademy(TenantContext $tenantContext): Academy
    {
        $academy = $tenantContext->getAcademy();

        if ($academy === null) {
            abort(404, 'No active academy selected.');
        }

        return $academy;
    }
}
