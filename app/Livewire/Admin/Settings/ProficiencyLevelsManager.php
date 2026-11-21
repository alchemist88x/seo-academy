<?php

namespace App\Livewire\Admin\Settings;

use App\Domain\Academies\Models\Academy;
use App\Domain\Students\Models\ProficiencyLevel;
use App\Domain\Students\Services\ProficiencyLevelService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Proficiency Levels')]
class ProficiencyLevelsManager extends Component
{
    // Form fields
    public ?string $editingLevelId = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public int $display_order = 1;

    public string $color = 'blue';

    public bool $is_active = true;

    // Deactivation confirmation modal state
    public ?string $confirmingDeactivateId = null;

    public ?ProficiencyLevel $deactivatingLevel = null;

    public int $deactivatingStudentsCount = 0;

    // Delete confirmation modal state
    public ?string $confirmingDeleteId = null;

    public ?ProficiencyLevel $deletingLevel = null;

    public int $deletingStudentsCount = 0;

    public function mount(TenantContext $tenantContext): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasRole('admin')) {
            abort(403, 'Unauthorized access.');
        }

        $academy = $this->resolveAcademy($tenantContext);
        app(ProficiencyLevelService::class)->ensureDefaultLevels($academy);
    }

    public function openCreateModal(TenantContext $tenantContext, ProficiencyLevelService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $this->resetForm();

        $maxOrder = ProficiencyLevel::where('academy_id', $academy->getKey())->max('display_order') ?? 0;
        $this->display_order = $maxOrder + 1;

        $this->dispatch('open-modal', 'proficiency-level-modal');
    }

    public function openEditModal(string $levelId, TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $level = ProficiencyLevel::where('academy_id', $academy->getKey())->findOrFail($levelId);

        $this->resetValidation();
        $this->editingLevelId = $level->id;
        $this->name = $level->name;
        $this->slug = $level->slug;
        $this->description = (string) ($level->description ?? '');
        $this->display_order = (int) $level->display_order;
        $this->color = $level->color ?: 'blue';
        $this->is_active = (bool) $level->is_active;

        $this->dispatch('open-modal', 'proficiency-level-modal');
    }

    public function updatedName(string $value): void
    {
        if ($this->editingLevelId === null && empty($this->slug)) {
            $this->slug = Str::slug($value, '_');
        }
    }

    public function saveLevel(TenantContext $tenantContext, ProficiencyLevelService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $actor = Auth::user();

        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'display_order' => ['required', 'integer', 'min:0'],
            'color' => ['required', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        $payload = [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description !== '' ? $this->description : null,
            'display_order' => $this->display_order,
            'color' => $this->color,
            'is_active' => $this->is_active,
        ];

        try {
            if ($this->editingLevelId !== null) {
                $level = ProficiencyLevel::where('academy_id', $academy->getKey())->findOrFail($this->editingLevelId);
                $service->update($level, $payload, $actor);
                $this->dispatch('toast', type: 'success', message: 'Proficiency level updated.');
            } else {
                $service->create($academy, $payload, $actor);
                $this->dispatch('toast', type: 'success', message: 'Proficiency level created.');
            }

            $this->dispatch('close-modal', 'proficiency-level-modal');
            $this->resetForm();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function requestToggleActive(string $levelId, TenantContext $tenantContext, ProficiencyLevelService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $level = ProficiencyLevel::where('academy_id', $academy->getKey())->findOrFail($levelId);

        // If currently active, prompt confirmation and display assigned student count
        if ($level->is_active) {
            $this->confirmingDeactivateId = $level->id;
            $this->deactivatingLevel = $level;
            $this->deactivatingStudentsCount = $level->studentProfiles()->count();
            $this->dispatch('open-modal', 'confirm-deactivate-modal');

            return;
        }

        // If inactive, immediately activate
        $service->toggleActive($level, Auth::user());
        $this->dispatch('toast', type: 'success', message: "Proficiency level '{$level->name}' activated.");
    }

    public function confirmDeactivation(TenantContext $tenantContext, ProficiencyLevelService $service): void
    {
        if (! $this->confirmingDeactivateId) {
            return;
        }

        $academy = $this->resolveAcademy($tenantContext);
        $level = ProficiencyLevel::where('academy_id', $academy->getKey())->findOrFail($this->confirmingDeactivateId);

        $service->toggleActive($level, Auth::user());
        $this->dispatch('close-modal', 'confirm-deactivate-modal');
        $this->dispatch('toast', type: 'warning', message: "Proficiency level '{$level->name}' deactivated. Existing student assignments are preserved.");

        $this->confirmingDeactivateId = null;
        $this->deactivatingLevel = null;
        $this->deactivatingStudentsCount = 0;
    }

    public function confirmDeleteLevel(string $levelId, TenantContext $tenantContext): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $level = ProficiencyLevel::where('academy_id', $academy->getKey())->findOrFail($levelId);

        $this->confirmingDeleteId = $level->id;
        $this->deletingLevel = $level;
        $this->deletingStudentsCount = $level->studentProfiles()->count();

        $this->dispatch('open-modal', 'confirm-delete-modal');
    }

    public function deleteLevel(TenantContext $tenantContext, ProficiencyLevelService $service): void
    {
        if (! $this->confirmingDeleteId) {
            return;
        }

        $academy = $this->resolveAcademy($tenantContext);
        $level = ProficiencyLevel::where('academy_id', $academy->getKey())->findOrFail($this->confirmingDeleteId);

        try {
            $service->delete($level, Auth::user());
            $this->dispatch('close-modal', 'confirm-delete-modal');
            $this->dispatch('toast', type: 'success', message: 'Proficiency level deleted.');
            $this->confirmingDeleteId = null;
            $this->deletingLevel = null;
        } catch (ValidationException $e) {
            $this->dispatch('close-modal', 'confirm-delete-modal');
            $this->dispatch('toast', type: 'error', message: $e->validator->errors()->first('error'));
        }
    }

    public function moveUp(string $levelId, TenantContext $tenantContext, ProficiencyLevelService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $levels = ProficiencyLevel::where('academy_id', $academy->getKey())->ordered()->get();

        $currentIndex = $levels->search(fn ($lvl) => $lvl->id === $levelId);
        if ($currentIndex === false || $currentIndex <= 0) {
            return;
        }

        $orderedIds = $levels->pluck('id')->toArray();
        $temp = $orderedIds[$currentIndex];
        $orderedIds[$currentIndex] = $orderedIds[$currentIndex - 1];
        $orderedIds[$currentIndex - 1] = $temp;

        $service->reorder($academy, $orderedIds, Auth::user());
        $this->dispatch('toast', type: 'info', message: 'Display order updated.');
    }

    public function moveDown(string $levelId, TenantContext $tenantContext, ProficiencyLevelService $service): void
    {
        $academy = $this->resolveAcademy($tenantContext);
        $levels = ProficiencyLevel::where('academy_id', $academy->getKey())->ordered()->get();

        $currentIndex = $levels->search(fn ($lvl) => $lvl->id === $levelId);
        if ($currentIndex === false || $currentIndex >= $levels->count() - 1) {
            return;
        }

        $orderedIds = $levels->pluck('id')->toArray();
        $temp = $orderedIds[$currentIndex];
        $orderedIds[$currentIndex] = $orderedIds[$currentIndex + 1];
        $orderedIds[$currentIndex + 1] = $temp;

        $service->reorder($academy, $orderedIds, Auth::user());
        $this->dispatch('toast', type: 'info', message: 'Display order updated.');
    }

    public function render(TenantContext $tenantContext, ProficiencyLevelService $service)
    {
        $academy = $this->resolveAcademy($tenantContext);
        $levels = $service->listForAcademy($academy);

        return view('livewire.admin.settings.proficiency-levels-manager', [
            'academy' => $academy,
            'levels' => $levels,
            'colorOptions' => [
                'blue' => 'Blue (Foundational)',
                'amber' => 'Amber (Practitioner)',
                'purple' => 'Purple (Specialist)',
                'emerald' => 'Emerald (Achiever)',
                'cyan' => 'Cyan (Technical)',
                'rose' => 'Rose (Mastery)',
                'indigo' => 'Indigo (Strategic)',
            ],
        ]);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingLevelId = null;
        $this->name = '';
        $this->slug = '';
        $this->description = '';
        $this->display_order = 1;
        $this->color = 'blue';
        $this->is_active = true;
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
