<?php

namespace App\Livewire\Admin;

use App\Domain\Platform\Services\FileManagementService;
use App\Domain\Platform\Services\SystemHealthService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('System Health')]
class SystemHealthDashboard extends Component
{
    public function render(SystemHealthService $healthService, FileManagementService $fileService): View
    {
        return view('livewire.admin.system-health-dashboard', [
            'appHealth' => $healthService->getApplicationHealth(),
            'dbHealth' => $healthService->getDatabaseHealth(),
            'storageHealth' => $healthService->getStorageHealth(),
            'queueHealth' => $healthService->getQueueHealth(),
            'schedulerHealth' => $healthService->getSchedulerHealth(),
            'phpHealth' => $healthService->getPhpHealth(),
            'fileService' => $fileService,
        ]);
    }
}
