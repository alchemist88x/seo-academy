<?php

namespace App\Livewire\Admin;

use App\Domain\Academies\Models\Academy;
use App\Domain\Tenants\Services\TenantContext;
use App\Domain\Trainers\Models\TrainerProfile;
use App\Domain\Trainers\Services\TrainerManagementService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Trainers')]
class TrainersIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public ?string $editingTrainerId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $specialty = '';

    public string $bio = '';

    public function mount(TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $this->authorize('viewAny', [TrainerProfile::class, $academy]);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status']);
        $this->resetPage();
    }

    public function openCreateModal(TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $this->authorize('create', [TrainerProfile::class, $academy]);

        $this->resetForm();
        $this->dispatch('open-modal', 'trainer-form-modal');
    }

    public function openEditModal(string $profileId, TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $profile = TrainerProfile::query()
            ->forAcademy($academy)
            ->with('user')
            ->findOrFail($profileId);

        $this->authorize('update', $profile);

        $this->resetValidation();
        $this->editingTrainerId = (string) $profile->getKey();
        $this->name = (string) ($profile->user?->name ?? '');
        $this->email = (string) ($profile->user?->email ?? '');
        $this->phone = (string) ($profile->user?->phone ?? '');
        $this->password = '';
        $this->specialty = (string) ($profile->specialty ?? '');
        $this->bio = (string) ($profile->bio ?? '');

        $this->dispatch('open-modal', 'trainer-form-modal');
    }

    public function saveTrainer(TenantContext $tenantContext, TrainerManagementService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $actor = Auth::user();

        if ($this->editingTrainerId !== null) {
            $profile = TrainerProfile::query()
                ->forAcademy($academy)
                ->with('user')
                ->findOrFail($this->editingTrainerId);

            $this->authorize('update', $profile);

            $service->update($profile, [
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone !== '' ? $this->phone : null,
                'password' => $this->password !== '' ? $this->password : null,
                'specialty' => $this->specialty !== '' ? $this->specialty : null,
                'bio' => $this->bio !== '' ? $this->bio : null,
            ], $actor);

            $this->dispatch('toast', type: 'success', message: 'Trainer profile updated.');
        } else {
            $this->authorize('create', [TrainerProfile::class, $academy]);

            $payload = [
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone !== '' ? $this->phone : null,
                'specialty' => $this->specialty !== '' ? $this->specialty : null,
                'bio' => $this->bio !== '' ? $this->bio : null,
            ];

            if ($this->password !== '') {
                $payload['password'] = $this->password;
            }

            $service->createOrAttach($academy, $payload, $actor);

            $this->dispatch('toast', type: 'success', message: 'Trainer added to academy.');
        }

        $this->resetForm();
        $this->dispatch('close-modal', 'trainer-form-modal');
    }

    public function toggleActive(string $profileId, TenantContext $tenantContext, TrainerManagementService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $profile = TrainerProfile::query()
            ->forAcademy($academy)
            ->with('user')
            ->findOrFail($profileId);

        $this->authorize('update', $profile);

        $updated = $service->setActive($profile, ! $profile->active, Auth::user());

        $this->dispatch(
            'toast',
            type: 'success',
            message: $updated->active ? 'Trainer activated.' : 'Trainer suspended.',
        );
    }

    public function render(TenantContext $tenantContext, TrainerManagementService $service)
    {
        $academy = $this->resolveAcademy($tenantContext);

        $activeFilter = match ($this->status) {
            'active' => true,
            'inactive' => false,
            default => null,
        };

        $trainers = $service->listForAcademy($academy, [
            'search' => $this->search,
            'active' => $activeFilter,
        ], 15);

        return view('livewire.admin.trainers-index', [
            'academy' => $academy,
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

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingTrainerId = null;
        $this->name = '';
        $this->email = '';
        $this->phone = '';
        $this->password = '';
        $this->specialty = '';
        $this->bio = '';
    }
}
