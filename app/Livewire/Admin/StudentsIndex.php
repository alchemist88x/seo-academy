<?php

namespace App\Livewire\Admin;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Students\Models\ProficiencyLevel;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Students\Services\ProficiencyLevelService;
use App\Domain\Students\Services\StudentManagementService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Students')]
class StudentsIndex extends Component
{
    use WithFileUploads;
    use WithPagination;

    // Filters
    public string $search = '';

    public string $status = '';

    public string $proficiency = '';

    public string $level = ''; // Legacy alias for proficiency

    public string $batch = '';

    public string $courseId = '';

    public int $perPage = 15;

    // Create / Edit modal state
    public ?string $editingStudentId = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $student_code = '';

    public string $batch_input = '';

    public string $formProficiency = '';

    public string $formLevel = ''; // Legacy alias

    public string $enrolled_at = '';

    public string $notes = '';

    public string $account_status = 'active';

    // View modal state
    public ?StudentProfile $viewingStudent = null;

    // Suspend confirmation modal state
    public ?string $confirmingSuspendId = null;

    public ?StudentProfile $confirmingSuspendProfile = null;

    // Delete confirmation modal state
    public ?string $deletingStudentId = null;

    public ?StudentProfile $deletingStudentProfile = null;

    /** @var array{enrollments_count?: int, active_enrollments_count?: int, assessment_attempts_count?: int, assignment_submissions_count?: int, certificates_count?: int, total_academic_records?: int} */
    public array $deletingStudentImpact = [];

    // CSV Import state
    public $csvFile = null;

    public ?string $importToken = null;

    /** @var array{valid_rows?: array<int, array<string, mixed>>, error_rows?: array<int, array<string, mixed>>, summary?: array<string, int>}|null */
    public ?array $importPreview = null;

    /** @var array{imported?: int}|null */
    public ?array $importResult = null;

    public function mount(TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $this->authorize('viewAny', StudentProfile::class);

        $this->enrolled_at = now()->toDateString();
        $this->formProficiency = '';
        $this->formLevel = '';
    }

    public function updatedName(string $value): void
    {
        $parts = explode(' ', trim($value), 2);
        $this->first_name = $parts[0] ?? '';
        $this->last_name = $parts[1] ?? '';
    }

    public function updatedFirstName(): void
    {
        $this->name = trim("{$this->first_name} {$this->last_name}");
    }

    public function updatedLastName(): void
    {
        $this->name = trim("{$this->first_name} {$this->last_name}");
    }

    public function updatedFormProficiency(string $value): void
    {
        $this->formLevel = $value !== '' ? ucfirst($value) : '';
    }

    public function updatedFormLevel(string $value): void
    {
        $this->formProficiency = StudentProfile::normalizeProficiency($value) ?? '';
    }

    public function updatedProficiency(string $value): void
    {
        $this->level = $value;
        $this->resetPage();
    }

    public function updatedLevel(string $value): void
    {
        $this->proficiency = StudentProfile::normalizeProficiency($value);
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingBatch(): void
    {
        $this->resetPage();
    }

    public function updatingCourseId(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'proficiency', 'level', 'batch', 'courseId', 'perPage']);
        $this->perPage = 15;
        $this->resetPage();
    }

    public function openCreateModal(TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $this->authorize('create', [StudentProfile::class, $academy]);

        $this->resetForm();
        $this->dispatch('open-modal', 'student-form-modal');
    }

    public function openEditModal(string $profileId, TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $profile = StudentProfile::query()
            ->forAcademy($academy)
            ->with('user')
            ->findOrFail($profileId);

        $this->authorize('update', $profile);

        $this->resetValidation();
        $this->editingStudentId = (string) $profile->getKey();
        $nameParts = explode(' ', (string) ($profile->user?->name ?? ''), 2);
        $this->first_name = $nameParts[0] ?? '';
        $this->last_name = $nameParts[1] ?? '';
        $this->name = (string) ($profile->user?->name ?? '');
        $this->email = (string) ($profile->user?->email ?? '');
        $this->phone = (string) ($profile->user?->phone ?? '');
        $this->password = '';
        $this->student_code = (string) $profile->student_code;
        $this->batch_input = (string) ($profile->batch ?? '');
        $this->formProficiency = (string) ($profile->proficiency ?? '');
        $this->formLevel = $profile->proficiency ? ucfirst($profile->proficiency) : '';
        $this->enrolled_at = $profile->enrolled_at?->toDateString() ?? '';
        $this->notes = (string) ($profile->notes ?? '');
        $this->account_status = $profile->active ? 'active' : 'suspended';

        $this->dispatch('open-modal', 'student-form-modal');
    }

    public function openViewModal(string $profileId, TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $profile = StudentProfile::query()
            ->forAcademy($academy)
            ->with([
                'user.certificates',
                'enrollments.course.modules.lessons',
                'enrollments.currentGrade',
            ])
            ->withCount('enrollments')
            ->findOrFail($profileId);

        $this->authorize('view', $profile);

        $this->viewingStudent = $profile;
        $this->dispatch('open-modal', 'student-view-modal');
    }

    public function saveStudent(TenantContext $tenantContext, StudentManagementService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $actor = Auth::user();

        $fullName = $this->name !== '' ? $this->name : trim("{$this->first_name} {$this->last_name}");
        $proficiency = ($this->formProficiency !== '' && $this->formProficiency !== null)
            ? StudentProfile::normalizeProficiency($this->formProficiency)
            : (($this->formLevel !== '' && $this->formLevel !== null) ? StudentProfile::normalizeProficiency($this->formLevel) : null);
        $batch = $this->batch_input !== '' ? $this->batch_input : ($this->batch !== '' ? $this->batch : null);
        if ($this->editingStudentId !== null) {
            $profile = StudentProfile::query()
                ->forAcademy($academy)
                ->with('user')
                ->findOrFail($this->editingStudentId);

            $this->authorize('update', $profile);

            if ($this->batch !== '' && $this->batch !== (string) ($profile->batch ?? '')) {
                $batch = $this->batch;
            }

            $service->update($profile, [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'name' => $fullName,
                'email' => $this->email,
                'phone' => $this->phone !== '' ? $this->phone : null,
                'student_code' => $this->student_code,
                'batch' => $batch,
                'proficiency' => $proficiency,
                'level' => $proficiency !== null ? ucfirst($proficiency) : null,
                'enrolled_at' => $this->enrolled_at !== '' ? $this->enrolled_at : null,
                'notes' => $this->notes !== '' ? $this->notes : null,
                'status' => $this->account_status,
                'password' => $this->password !== '' ? $this->password : null,
            ], $actor);

            $this->dispatch('toast', type: 'success', message: 'Student profile updated.');
        } else {
            $this->authorize('create', [StudentProfile::class, $academy]);

            $payload = [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'name' => $fullName,
                'email' => $this->email,
                'phone' => $this->phone !== '' ? $this->phone : null,
                'student_code' => $this->student_code !== '' ? $this->student_code : null,
                'batch' => $batch,
                'proficiency' => $proficiency,
                'level' => $proficiency !== null ? ucfirst($proficiency) : null,
                'enrolled_at' => $this->enrolled_at !== '' ? $this->enrolled_at : null,
                'notes' => $this->notes !== '' ? $this->notes : null,
                'status' => $this->account_status,
            ];

            if ($this->password !== '') {
                $payload['password'] = $this->password;
            }

            $service->createOrAttach($academy, $payload, $actor);

            $this->dispatch('toast', type: 'success', message: 'Student added to academy.');
        }

        $this->resetForm();
        $this->dispatch('close-modal', 'student-form-modal');
    }

    public function promptSuspend(string $profileId, TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $profile = StudentProfile::query()
            ->forAcademy($academy)
            ->with('user')
            ->findOrFail($profileId);

        $this->authorize('update', $profile);

        if ($profile->active) {
            $this->confirmingSuspendId = $profileId;
            $this->confirmingSuspendProfile = $profile;
            $this->dispatch('open-modal', 'confirm-suspend-modal');
        } else {
            // Reactivating can happen immediately without destructive warning
            $this->toggleActive($profileId, $tenantContext, app(StudentManagementService::class));
        }
    }

    public function cancelSuspend(): void
    {
        $this->confirmingSuspendId = null;
        $this->confirmingSuspendProfile = null;
        $this->dispatch('close-modal', 'confirm-suspend-modal');
    }

    public function confirmSuspendAction(TenantContext $tenantContext, StudentManagementService $service): void
    {
        if ($this->confirmingSuspendId === null) {
            return;
        }

        $this->toggleActive($this->confirmingSuspendId, $tenantContext, $service);
        $this->cancelSuspend();
    }

    public function toggleActive(string $profileId, TenantContext $tenantContext, StudentManagementService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $profile = StudentProfile::query()
            ->forAcademy($academy)
            ->with('user')
            ->findOrFail($profileId);

        $this->authorize('update', $profile);

        $updated = $service->setActive($profile, ! $profile->active, Auth::user());

        $this->dispatch(
            'toast',
            type: 'success',
            message: $updated->active ? 'Student activated.' : 'Student suspended.',
        );
    }

    public function openDeleteModal(string $profileId, TenantContext $tenantContext, StudentManagementService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $profile = StudentProfile::query()
            ->forAcademy($academy)
            ->with(['user', 'enrollments'])
            ->findOrFail($profileId);

        $this->authorize('delete', $profile);

        $this->deletingStudentId = $profileId;
        $this->deletingStudentProfile = $profile;
        $this->deletingStudentImpact = $service->getImpactSummary($profile);

        $this->dispatch('open-modal', 'student-delete-modal');
    }

    public function cancelDelete(): void
    {
        $this->deletingStudentId = null;
        $this->deletingStudentProfile = null;
        $this->deletingStudentImpact = [];
        $this->dispatch('close-modal', 'student-delete-modal');
    }

    public function confirmDeleteStudent(TenantContext $tenantContext, StudentManagementService $service): void
    {
        if ($this->deletingStudentId === null) {
            return;
        }

        $academy = $this->resolveAcademy($tenantContext);
        $profile = StudentProfile::query()
            ->forAcademy($academy)
            ->with('user')
            ->findOrFail($this->deletingStudentId);

        $this->authorize('delete', $profile);

        try {
            $service->delete($profile, Auth::user());
            $this->dispatch('toast', type: 'success', message: 'Student account deleted. Authentication access removed and academic records preserved.');
            $this->cancelDelete();
        } catch (ValidationException $e) {
            $msg = $e->validator->errors()->first('student') ?: $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $msg);
        }
    }

    public function restoreStudent(string $profileId, TenantContext $tenantContext, StudentManagementService $service): void
    {
        $this->resolveAcademy($tenantContext);
        $profile = StudentProfile::onlyTrashed()->findOrFail($profileId);
        $this->authorize('restore', $profile);

        $service->restore($profileId, Auth::user());
        $this->dispatch('toast', type: 'success', message: 'Student account restored and authentication access reactivated.');
    }

    public function openImportModal(TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $this->authorize('import', [StudentProfile::class, $academy]);

        $this->resetValidation();
        $this->csvFile = null;
        $this->importToken = (string) Str::uuid();
        $this->importPreview = null;
        $this->importResult = null;

        $this->dispatch('open-modal', 'student-import-modal');
    }

    public function previewCsvImport(TenantContext $tenantContext, StudentManagementService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $this->authorize('import', [StudentProfile::class, $academy]);

        $this->validate([
            'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $this->importResult = null;

        try {
            $content = (string) $this->csvFile->get();
            $this->importPreview = $service->previewCsv($academy, $content);
            $this->importToken = (string) Str::uuid();
        } catch (ValidationException $e) {
            $this->importPreview = null;
            $this->addError('csvFile', collect($e->errors())->flatten()->first() ?? 'Unable to parse CSV file.');
        }
    }

    public function confirmCsvImport(TenantContext $tenantContext, StudentManagementService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $this->authorize('import', [StudentProfile::class, $academy]);

        $validRows = $this->importPreview['valid_rows'] ?? [];
        if ($validRows === []) {
            $this->addError('csvFile', 'No valid rows available to import.');

            return;
        }

        try {
            $result = $service->commitCsvImport($academy, $validRows, Auth::user(), $this->importToken);

            $this->importResult = [
                'imported' => (int) ($result['imported'] ?? 0),
            ];
            $this->importPreview = null;
            $this->csvFile = null;
            $this->importToken = null;

            $this->dispatch('toast', type: 'success', message: "Imported {$this->importResult['imported']} student(s).");
        } catch (ValidationException $e) {
            $this->addError('csvFile', collect($e->errors())->flatten()->first() ?? 'Import failed.');
        }
    }

    public function downloadRejectedRows(StudentManagementService $service): StreamedResponse
    {
        $errorRows = $this->importPreview['error_rows'] ?? [];
        if (empty($errorRows)) {
            abort(404, 'No rejected rows found.');
        }

        $csv = $service->exportRejectedCsv($errorRows);

        return response()->streamDownload(function () use ($csv): void {
            echo chr(0xEF).chr(0xBB).chr(0xBF); // UTF-8 BOM
            echo $csv;
        }, 'rejected-students-'.now()->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function exportCsv(TenantContext $tenantContext, StudentManagementService $service): StreamedResponse
    {
        $academy = $this->resolveAcademy($tenantContext);
        $this->authorize('export', [StudentProfile::class, $academy]);

        $filters = [
            'search' => $this->search,
            'status' => $this->status,
            'proficiency' => $this->proficiency ?: $this->level,
            'batch' => $this->batch,
            'course_id' => $this->courseId,
        ];

        return $service->exportCsv($academy, $filters, Auth::user());
    }

    public function render(TenantContext $tenantContext, StudentManagementService $service)
    {
        $academy = $this->resolveAcademy($tenantContext);

        $activeFilter = match ($this->status) {
            'active' => true,
            'suspended', 'inactive' => false,
            default => null,
        };

        $students = $service->listForAcademy($academy, [
            'search' => $this->search,
            'status' => $this->status,
            'proficiency' => $this->proficiency ?: $this->level,
            'batch' => $this->batch,
            'course_id' => $this->courseId,
            'active' => $activeFilter,
        ], $this->perPage);

        $availableCourses = Course::query()
            ->forAcademy($academy)
            ->orderBy('title')
            ->get(['id', 'title']);

        $availableBatches = StudentProfile::query()
            ->forAcademy($academy)
            ->whereNotNull('batch')
            ->where('batch', '!=', '')
            ->distinct()
            ->pluck('batch')
            ->sort()
            ->values();

        $proficiencyService = app(ProficiencyLevelService::class);
        $activeLevels = $proficiencyService->listForAcademy($academy, activeOnly: true);
        $proficiencies = $activeLevels->pluck('name', 'slug')->toArray();
        if (empty($proficiencies)) {
            $proficiencies = [
                StudentProfile::PROFICIENCY_BEGINNER => 'Beginner',
                StudentProfile::PROFICIENCY_INTERMEDIATE => 'Intermediate',
                StudentProfile::PROFICIENCY_EXPERT => 'Expert',
            ];
        }
        if ($this->editingStudentId && $this->formProficiency && ! isset($proficiencies[$this->formProficiency])) {
            $inactive = ProficiencyLevel::where('academy_id', $academy->getKey())->where('slug', $this->formProficiency)->first();
            if ($inactive) {
                $proficiencies[$this->formProficiency] = "{$inactive->name} (Inactive)";
            }
        }

        $allLevels = $proficiencyService->listForAcademy($academy);
        $filterProficiencies = ['not_assessed' => 'Not Assessed'];
        foreach ($allLevels as $lvl) {
            $filterProficiencies[$lvl->slug] = $lvl->is_active ? $lvl->name : "{$lvl->name} (Inactive)";
        }

        return view('livewire.admin.students-index', [
            'academy' => $academy,
            'students' => $students,
            'availableCourses' => $availableCourses,
            'availableBatches' => $availableBatches,
            'proficiencies' => $proficiencies,
            'filterProficiencies' => $filterProficiencies,
            // Kept for backward compatibility
            'levels' => array_values($proficiencies),
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

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingStudentId = null;
        $this->first_name = '';
        $this->last_name = '';
        $this->name = '';
        $this->email = '';
        $this->phone = '';
        $this->password = '';
        $this->student_code = '';
        $this->batch_input = '';
        $this->formProficiency = '';
        $this->formLevel = '';
        $this->enrolled_at = now()->toDateString();
        $this->notes = '';
        $this->account_status = 'active';
    }
}
