<?php

namespace App\Livewire\Student;

use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Services\CertificateIssuanceService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('My Certificates')]
class StudentCertificatesIndex extends Component
{
    public function toggleConsent(string $certificateId, CertificateIssuanceService $issuanceService): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $certificate = Certificate::where('id', $certificateId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $newConsent = ! (bool) $certificate->public_identity_consent;
        $issuanceService->setPublicIdentityConsent($certificate, $user, $newConsent);

        session()->flash('success', $newConsent
            ? 'Public disclosure enabled: your full name is now visible on public verification.'
            : 'Public disclosure disabled: your name is now masked on public verification.'
        );
    }

    public function render(TenantContext $tenantContext)
    {
        $user = Auth::user();
        $academy = $tenantContext->getAcademy();

        $certificates = Certificate::query()
            ->with(['course.academy', 'enrollment'])
            ->where('user_id', $user?->id)
            ->latest('created_at')
            ->get();

        return view('livewire.student.student-certificates-index', [
            'certificates' => $certificates,
            'academy' => $academy,
        ]);
    }
}
