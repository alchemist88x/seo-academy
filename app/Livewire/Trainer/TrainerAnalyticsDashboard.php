<?php

namespace App\Livewire\Trainer;

use App\Domain\Analytics\Services\TrainerAnalyticsService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Trainer Performance & Learning Analytics')]
class TrainerAnalyticsDashboard extends Component
{
    public function mount(): void
    {
        $user = Auth::user();
        if (! $user || (! $user->hasRole('trainer') && ! $user->hasRole('admin'))) {
            abort(403, 'Unauthorized. Trainer access required.');
        }
    }

    public function render(TenantContext $tenantContext, TrainerAnalyticsService $trainerAnalytics)
    {
        $user = Auth::user();
        $academy = $tenantContext->getAcademy()
            ?? $user->academies()->where('academies.is_active', true)->first();

        if ($academy === null) {
            abort(404, 'No active academy found.');
        }

        $analytics = $trainerAnalytics->getTrainerAnalytics($user, $academy);

        return view('livewire.trainer.trainer-analytics-dashboard', [
            'analytics' => $analytics,
            'trainer' => $user,
        ]);
    }
}
