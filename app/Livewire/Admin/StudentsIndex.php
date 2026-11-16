<?php

namespace App\Livewire\Admin;

use App\Domain\Academies\Models\Academy;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Students\Services\StudentManagementService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Students')]
class StudentsIndex extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $level = '';

    // Create / Edit modal state
    public ?string $editingStudentId = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $student_code = '';

    public string $batch = '';

    public string $formLevel = 'Beginner';

    public string $enrolled_at = '';

    public string $notes = '';

    public string $account_status = 'active';

    // View modal state
    public ?StudentProfile $viewingStudent = null;

    // CSV Import state
    public $csvFile = null;

    /** @var array{valid_rows?: array<int, array<string, mixed>>, error_rows?: array<int, array<string, mixed>>, summary?: array<string, int>}|null */
    public ?array $importPreview = null;

    /** @var array{imported?: int}|null */
    public ?array $importResult = null;

    public function mount(TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $this->authorize('viewAny', StudentProfile::class);

        $this->enrolled_at = now()->toDateString();
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

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingLevel(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'level']);
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
        $this->batch = (string) ($profile->batch ?? '');
        $this->formLevel = (string) ($profile->level ?: 'Beginner');
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
            ->with(['user.certificates', 'enrollments.course', 'enrollments.currentGrade'])
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

        if ($this->editingStudentId !== null) {
            $profile = StudentProfile::query()
                ->forAcademy($academy)
                ->with('user')
                ->findOrFail($this->editingStudentId);

            $this->authorize('update', $profile);

            $service->update($profile, [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'name' => $fullName,
                'email' => $this->email,
                'phone' => $this->phone !== '' ? $this->phone : null,
                'student_code' => $this->student_code,
                'batch' => $this->batch !== '' ? $this->batch : null,
                'level' => $this->formLevel,
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
                'batch' => $this->batch !== '' ? $this->batch : null,
                'level' => $this->formLevel,
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
            message: $updated->active ? 'Student activated.' : 'Student deactivated.',
        );
    }

    public function openImportModal(TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $this->authorize('import', [StudentProfile::class, $academy]);

        $this->resetValidation();
        $this->csvFile = null;
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

        $result = $service->commitCsvImport($academy, $validRows, Auth::user());

        $this->importResult = [
            'imported' => (int) ($result['imported'] ?? 0),
        ];
        $this->importPreview = null;
        $this->csvFile = null;

        $this->dispatch('toast', type: 'success', message: "Imported {$this->importResult['imported']} student(s).");
    }

    public function render(TenantContext $tenantContext, StudentManagementService $service)
    {
        $academy = $this->resolveAcademy($tenantContext);

        $activeFilter = match ($this->status) {
            'active' => true,
            'inactive' => false,
            default => null,
        };

        $students = $service->listForAcademy($academy, [
            'search' => $this->search,
            'level' => $this->level,
            'active' => $activeFilter,
        ], 15);

        return view('livewire.admin.students-index', [
            'academy' => $academy,
            'students' => $students,
            'levels' => ['Beginner', 'Intermediate', 'Advanced'],
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
        $this->batch = '';
        $this->formLevel = 'Beginner';
        $this->enrolled_at = now()->toDateString();
        $this->notes = '';
        $this->account_status = 'active';
    }
}
