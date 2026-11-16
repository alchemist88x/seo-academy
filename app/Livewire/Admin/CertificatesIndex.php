<?php

namespace App\Livewire\Admin;

use App\Domain\Certificates\Models\Certificate;
use App\Domain\Certificates\Services\CertificateIssuanceService;
use App\Domain\Platform\Models\Setting;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Certificate Management')]
class CertificatesIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    // Revocation Modal State
    public bool $showRevokeModal = false;

    public ?string $revokingCertificateId = null;

    public string $revocationReason = '';

    // Reissuance Modal State
    public bool $showReissueModal = false;

    public ?string $reissuingCertificateId = null;

    public string $reissueReason = '';

    // Audit History Modal State
    public bool $showAuditHistoryModal = false;

    public ?string $auditingCertificateId = null;

    public array $auditEventsList = [];

    // Template Settings
    public string $defaultTemplate = 'classic';

    public string $signatoryName = '';

    public string $signatoryTitle = '';

    public bool $settingsSaved = false;

    // Manual Issue Modal State
    public bool $showIssueModal = false;

    public string $issueEnrollmentId = '';

    public string $issueTemplate = 'classic';

    public function mount(TenantContext $tenantContext): void
    {
        $academy = $tenantContext->getAcademy();
        $academyId = $academy?->id;

        $this->defaultTemplate = Setting::query()
            ->where('academy_id', $academyId)
            ->where('group', 'certificates')
            ->where('key', 'default_template')
            ->value('value') ?: 'classic';

        $this->signatoryName = Setting::query()
            ->where('academy_id', $academyId)
            ->where('group', 'certificates')
            ->where('key', 'signatory_name')
            ->value('value') ?: ($academy?->name ? $academy->name.' Director' : 'Academic Director');

        $this->signatoryTitle = Setting::query()
            ->where('academy_id', $academyId)
            ->where('group', 'certificates')
            ->where('key', 'signatory_title')
            ->value('value') ?: 'Lead Instructor & Director';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function saveSettings(TenantContext $tenantContext): void
    {
        $this->validate([
            'defaultTemplate' => ['required', 'string', 'in:classic,modern,minimalist'],
            'signatoryName' => ['required', 'string', 'max:255'],
            'signatoryTitle' => ['required', 'string', 'max:255'],
        ]);

        $academy = $tenantContext->getAcademy();
        $academyId = $academy?->id;

        $settings = [
            'default_template' => $this->defaultTemplate,
            'signatory_name' => $this->signatoryName,
            'signatory_title' => $this->signatoryTitle,
        ];

        foreach ($settings as $key => $val) {
            Setting::updateOrCreate(
                [
                    'academy_id' => $academyId,
                    'group' => 'certificates',
                    'key' => $key,
                ],
                [
                    'value' => $val,
                    'type' => 'string',
                    'is_public' => false,
                ]
            );
        }

        $this->settingsSaved = true;
        session()->flash('success', 'Certificate settings updated successfully.');
    }

    public function promptRevoke(string $certificateId): void
    {
        if (! Auth::user()?->hasRole('admin')) {
            abort(403, 'Unauthorized: Only administrators may revoke certificates.');
        }

        $this->revokingCertificateId = $certificateId;
        $this->revocationReason = '';
        $this->showRevokeModal = true;
    }

    public function cancelRevoke(): void
    {
        $this->revokingCertificateId = null;
        $this->revocationReason = '';
        $this->showRevokeModal = false;
    }

    public function confirmRevoke(CertificateIssuanceService $issuanceService): void
    {
        if (! Auth::user()?->hasRole('admin')) {
            abort(403, 'Unauthorized: Only administrators may revoke certificates.');
        }

        $this->validate([
            'revocationReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $certificate = Certificate::findOrFail($this->revokingCertificateId);
        $issuanceService->revokeCertificate($certificate, Auth::user(), $this->revocationReason);

        $this->cancelRevoke();
        session()->flash('success', 'Certificate '.$certificate->certificate_number.' has been revoked.');
    }

    public function regenerate(string $certificateId, CertificateIssuanceService $issuanceService): void
    {
        if (! Auth::user()?->hasRole('admin')) {
            abort(403, 'Unauthorized: Only administrators may regenerate certificates.');
        }

        $certificate = Certificate::findOrFail($certificateId);
        $issuanceService->regenerateCertificate($certificate);

        session()->flash('success', 'Certificate '.$certificate->certificate_number.' regenerated.');
    }

    public function promptReissue(string $certificateId): void
    {
        if (! Auth::user()?->hasRole('admin')) {
            abort(403, 'Unauthorized: Only administrators may reissue certificates.');
        }

        $this->reissuingCertificateId = $certificateId;
        $this->reissueReason = '';
        $this->showReissueModal = true;
    }

    public function cancelReissue(): void
    {
        $this->reissuingCertificateId = null;
        $this->reissueReason = '';
        $this->showReissueModal = false;
    }

    public function confirmReissue(CertificateIssuanceService $issuanceService): void
    {
        if (! Auth::user()?->hasRole('admin')) {
            abort(403, 'Unauthorized: Only administrators may reissue certificates.');
        }

        $this->validate([
            'reissueReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $certificate = Certificate::findOrFail($this->reissuingCertificateId);
        $newCert = $issuanceService->reissueCertificate($certificate, Auth::user(), $this->reissueReason);

        $this->cancelReissue();
        session()->flash('success', "Certificate {$certificate->certificate_number} reissued as {$newCert->certificate_number} (v{$newCert->version}).");
    }

    public function retryPdf(string $certificateId, CertificateIssuanceService $issuanceService): void
    {
        $certificate = Certificate::findOrFail($certificateId);
        $issuanceService->retryPdfGeneration($certificate, Auth::user());

        session()->flash('success', "PDF generation queued for certificate {$certificate->certificate_number}.");
    }

    public function viewAuditHistory(string $certificateId): void
    {
        $certificate = Certificate::with(['auditEvents.actor'])->findOrFail($certificateId);
        $this->auditingCertificateId = $certificateId;
        $this->auditEventsList = $certificate->auditEvents
            ->sortByDesc('created_at')
            ->map(fn ($e) => [
                'event_type' => $e->event_type,
                'actor' => $e->actor?->name ?? 'System',
                'reason' => $e->reason,
                'created_at' => $e->created_at?->format('M d, Y H:i:s'),
            ])
            ->values()
            ->all();
        $this->showAuditHistoryModal = true;
    }

    public function closeAuditHistory(): void
    {
        $this->auditingCertificateId = null;
        $this->auditEventsList = [];
        $this->showAuditHistoryModal = false;
    }

    public function openIssueModal(): void
    {
        if (! Auth::user()?->hasRole('admin')) {
            abort(403, 'Unauthorized: Only administrators may issue certificates.');
        }

        $this->issueEnrollmentId = '';
        $this->issueTemplate = $this->defaultTemplate;
        $this->showIssueModal = true;
    }

    public function issueCertificateForEnrollment(CertificateIssuanceService $issuanceService): void
    {
        if (! Auth::user()?->hasRole('admin')) {
            abort(403, 'Unauthorized: Only administrators may issue certificates.');
        }

        $this->validate([
            'issueEnrollmentId' => ['required', 'string', 'exists:enrollments,id'],
            'issueTemplate' => ['required', 'string', 'in:classic,modern,minimalist'],
        ]);

        $enrollment = Enrollment::with(['studentProfile.user', 'course'])->findOrFail($this->issueEnrollmentId);

        try {
            $certificate = $issuanceService->issueCertificate($enrollment, $this->issueTemplate);
            $this->showIssueModal = false;
            $this->issueEnrollmentId = '';
            $this->dispatch('toast', type: 'success', message: "Certificate {$certificate->certificate_number} issued successfully.");
        } catch (\Throwable $e) {
            $this->addError('issue_general', $e->getMessage());
        }
    }

    public function render(TenantContext $tenantContext)
    {
        $user = Auth::user();
        $academy = $tenantContext->getAcademy();

        $eligibleEnrollments = collect();
        if ($user?->hasRole('admin') && $academy) {
            $eligibleEnrollments = Enrollment::query()
                ->where('status', 'completed')
                ->whereDoesntHave('certificate')
                ->whereHas('course', fn ($q) => $q->where('academy_id', $academy->id))
                ->with(['studentProfile.user', 'course'])
                ->get();
        }

        $query = Certificate::query()
            ->with(['user', 'course', 'enrollment', 'supersedes', 'supersededBy']);

        // Trainer role restriction: trainers only see certificates for their assigned courses
        if ($user && ($user->role === 'trainer' || $user->hasRole('trainer')) && ! ($user->role === 'admin' || $user->hasRole('admin'))) {
            $query->whereHas('course.trainers', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }

        if ($academy) {
            $query->whereHas('course', function ($q) use ($academy) {
                $q->where('academy_id', $academy->id);
            });
        }

        if ($this->search !== '') {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                $q->where('certificate_number', 'like', "%{$term}%")
                    ->orWhere('verification_code', 'like', "%{$term}%")
                    ->orWhere('verification_token_hash', hash('sha256', $term))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                    ->orWhereHas('course', fn ($c) => $c->where('title', 'like', "%{$term}%"));
            });
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        $certificates = $query->latest('issued_at')->paginate(15);

        return view('livewire.admin.certificates-index', [
            'certificates' => $certificates,
            'eligibleEnrollments' => $eligibleEnrollments,
            'academy' => $academy,
            'isAdmin' => $user ? ($user->role === 'admin' || $user->hasRole('admin')) : false,
        ]);
    }
}
