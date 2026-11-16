<?php

namespace App\Livewire\Admin;

use App\Domain\Platform\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Audit Logs')]
class AuditLogsIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $action = 'all';

    #[Url]
    public string $module = 'all';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $actorId = 'all';

    public ?string $selectedLogId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedAction(): void
    {
        $this->resetPage();
    }

    public function updatedModule(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedActorId(): void
    {
        $this->resetPage();
    }

    public function selectLog(?string $logId): void
    {
        $this->selectedLogId = $logId;
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'action', 'module', 'dateFrom', 'dateTo', 'actorId', 'selectedLogId']);
        $this->resetPage();
    }

    public function render(): View
    {
        $query = AuditLog::query()->with('user')->orderBy('created_at', 'desc');

        if (trim($this->search) !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('action', 'like', $term)
                    ->orWhere('subject_type', 'like', $term)
                    ->orWhere('subject_id', 'like', $term)
                    ->orWhere('ip_address', 'like', $term);
            });
        }

        if ($this->action !== 'all') {
            $query->where('action', $this->action);
        }

        if ($this->module !== 'all') {
            $query->where('subject_type', 'like', '%'.$this->module.'%');
        }

        if (! empty($this->dateFrom)) {
            $query->where('created_at', '>=', $this->dateFrom.' 00:00:00');
        }

        if (! empty($this->dateTo)) {
            $query->where('created_at', '<=', $this->dateTo.' 23:59:59');
        }

        if ($this->actorId !== 'all') {
            $query->where('user_id', $this->actorId);
        }

        $logs = $query->paginate(20);

        $selectedLog = $this->selectedLogId
            ? AuditLog::query()->with('user')->find($this->selectedLogId)
            : null;

        $availableActions = AuditLog::query()->distinct()->pluck('action')->filter()->values();
        $availableModules = AuditLog::query()->distinct()->pluck('subject_type')->filter()->map(function ($type) {
            return class_basename($type);
        })->unique()->values();

        $actors = User::query()->orderBy('name')->get(['id', 'name', 'email']);

        return view('livewire.admin.audit-logs-index', [
            'logs' => $logs,
            'selectedLog' => $selectedLog,
            'availableActions' => $availableActions,
            'availableModules' => $availableModules,
            'actors' => $actors,
        ]);
    }
}
