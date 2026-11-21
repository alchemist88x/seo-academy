<div class="space-y-6">
    <x-page-header
        title="Certificates & Credentials"
        subtitle="Manage issued academic certificates, customize templates, and handle credential verifications."
    >
        @if ($isAdmin)
            <x-slot:actions>
                <div class="flex items-center space-x-2">
                    <a href="{{ route('admin.certificates.designer') }}" class="inline-flex items-center px-3.5 py-2 border border-slate-300 dark:border-slate-700 rounded-lg text-sm font-medium text-text-primary bg-surface-secondary hover:bg-surface-elevated transition shadow-xs">
                        <svg class="w-4 h-4 mr-2 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Certificate Designer
                    </a>
                    <x-button variant="primary" wire:click="openIssueModal">
                        + Issue Certificate
                    </x-button>
                </div>
            </x-slot:actions>
        @endif
    </x-page-header>

    {{-- Feedback Alerts --}}
    @if (session()->has('success'))
        <div class="p-4 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-sm text-emerald-800 dark:text-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    {{-- Certificate Template & Signatory Configuration Card --}}
    <x-card title="Certificate Template & Signatory Settings" description="Configure global certificate layout, branding signature, and issuing authority title.">
        <form wire:submit.prevent="saveSettings" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-text-muted mb-1">Default Template</label>
                <select wire:model="defaultTemplate" class="w-full text-sm rounded-lg border-border-default bg-surface-primary text-text-primary">
                    @if($customTemplates->isNotEmpty())
                        <optgroup label="Custom Designer Templates">
                            @foreach($customTemplates as $ct)
                                <option value="{{ $ct->id }}">{{ $ct->name }} (Designer)</option>
                            @endforeach
                        </optgroup>
                    @endif
                    <optgroup label="Standard Templates">
                        <option value="classic">Classic (Double Border & Gold Accent)</option>
                        <option value="modern">Modern Minimalist (Clean Indigo & Dark Header)</option>
                        <option value="minimalist">Clean & Minimal (Emerald Accent)</option>
                    </optgroup>
                </select>
                @error('defaultTemplate') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-text-muted mb-1">Signatory Name</label>
                <input type="text" wire:model="signatoryName" class="w-full text-sm rounded-lg border-border-default bg-surface-primary text-text-primary" placeholder="e.g. Dr. John Doe">
                @error('signatoryName') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-text-muted mb-1">Signatory Title</label>
                <div class="flex gap-2">
                    <input type="text" wire:model="signatoryTitle" class="w-full text-sm rounded-lg border-border-default bg-surface-primary text-text-primary" placeholder="e.g. Academy Director">
                    <button type="submit" class="btn btn-primary btn-sm shrink-0">
                        Save
                    </button>
                </div>
                @error('signatoryTitle') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
            </div>
        </form>
    </x-card>

    {{-- Filter and Search Card --}}
    <x-card>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold uppercase text-text-muted mb-1">Search Certificates</label>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    class="w-full text-sm rounded-lg border-border-default bg-surface-primary text-text-primary"
                    placeholder="Search by student name, course, certificate number or verification code..."
                />
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-text-muted mb-1">Status</label>
                <select wire:model.live="statusFilter" class="w-full text-sm rounded-lg border-border-default bg-surface-primary text-text-primary">
                    <option value="">All Statuses</option>
                    <option value="Issued">Issued</option>
                    <option value="Revoked">Revoked</option>
                    <option value="Reinstated">Reinstated</option>
                    <option value="Under Audit">Under Audit</option>
                    <option value="Superseded">Superseded</option>
                </select>
            </div>
        </div>
    </x-card>

    {{-- Certificates Table --}}
    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-border-default text-xs uppercase text-text-muted">
                        <th class="py-3 px-4">Certificate #</th>
                        <th class="py-3 px-4">Student</th>
                        <th class="py-3 px-4">Course</th>
                        <th class="py-3 px-4">Issue Date</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-default">
                    @forelse ($certificates as $cert)
                        <tr wire:key="cert-row-{{ $cert->id }}" class="hover:bg-surface-secondary/50 transition-colors">
                            <td class="py-3 px-4 font-mono font-medium text-text-primary">
                                {{ $cert->certificate_number }}
                                @if ($cert->template)
                                    <span class="block text-[10px] text-text-muted uppercase">Layout: {{ $cert->template }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-medium text-text-primary">{{ $cert->user?->name ?? 'Unknown Student' }}</div>
                                <div class="text-xs text-text-muted">{{ $cert->user?->email }}</div>
                            </td>
                            <td class="py-3 px-4 text-text-primary">
                                {{ $cert->course?->title ?? 'Course Unavailable' }}
                            </td>
                            <td class="py-3 px-4 text-text-muted text-xs">
                                {{ $cert->issued_at?->format('M d, Y') }}
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    @if ($cert->isUnderAudit())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">
                                            Under Audit
                                        </span>
                                    @elseif ($cert->isRevoked())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-200">
                                            Revoked
                                        </span>
                                    @elseif ($cert->isReinstated())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-sky-100 text-sky-800 dark:bg-sky-900/60 dark:text-sky-200">
                                            Reinstated (v{{ $cert->version }})
                                        </span>
                                    @elseif ($cert->isSuperseded())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-300">
                                            Superseded (v{{ $cert->version }})
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">
                                            Issued (v{{ $cert->version }})
                                        </span>
                                    @endif

                                    {{-- PDF Generation Status --}}
                                    @if ($cert->pdf_generation_status === 'failed')
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300" title="{{ $cert->pdf_generation_error }}">
                                            PDF Failed
                                        </span>
                                    @elseif ($cert->pdf_generation_status === 'generating' || $cert->pdf_generation_status === 'pending')
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                            PDF {{ ucfirst($cert->pdf_generation_status) }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4 text-right space-x-2">
                                <a
                                    href="{{ route('certificates.verify', $cert->verification_code ?: $cert->certificate_number) }}"
                                    target="_blank"
                                    class="text-xs text-primary-600 hover:underline"
                                >
                                    Verify
                                </a>
                                @if (! $cert->isRevoked() && ! $cert->isUnderAudit() && ! $cert->isSuperseded())
                                    <a
                                        href="{{ route('certificates.download', $cert) }}"
                                        class="text-xs text-primary-600 hover:underline"
                                    >
                                        PDF
                                    </a>
                                @endif
                                <button
                                    type="button"
                                    wire:click="viewIssuanceHistory('{{ $cert->id }}')"
                                    class="text-xs text-primary-600 hover:underline"
                                    title="View Issuance Snapshot &amp; History"
                                >
                                    Details
                                </button>
                                <button
                                    type="button"
                                    wire:click="viewAuditHistory('{{ $cert->id }}')"
                                    class="text-xs text-slate-500 hover:underline"
                                >
                                    Audit
                                </button>
                                @if ($isAdmin)
                                    @if ($cert->isRevoked())
                                        <button
                                            type="button"
                                            wire:click="promptReinstate('{{ $cert->id }}')"
                                            class="text-xs font-semibold text-emerald-600 hover:underline"
                                            title="Reinstate this revoked certificate"
                                        >
                                            Reinstate
                                        </button>
                                    @elseif (! $cert->isSuperseded())
                                        <button
                                            type="button"
                                            wire:click="promptRevoke('{{ $cert->id }}')"
                                            class="text-xs text-rose-600 hover:underline"
                                        >
                                            Revoke
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="promptReissue('{{ $cert->id }}')"
                                            class="text-xs text-indigo-600 hover:underline"
                                        >
                                            Reissue
                                        </button>
                                    @endif
                                    @if ($cert->pdf_generation_status === 'failed')
                                        <button
                                            type="button"
                                            wire:click="retryPdf('{{ $cert->id }}')"
                                            class="text-xs text-amber-600 hover:underline font-semibold"
                                        >
                                            Retry PDF
                                        </button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-text-muted text-sm">
                                No certificates match your search criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($certificates->hasPages())
            <div class="pt-4 border-t border-border-default">
                {{ $certificates->links() }}
            </div>
        @endif
    </x-card>

    {{-- Revocation Modal --}}
    @if ($showRevokeModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-surface-primary border border-border-default rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
                <h3 class="text-lg font-bold text-text-primary">Revoke Certificate</h3>
                <p class="text-sm text-text-muted">
                    Revoking this certificate permanently invalidates it. The revocation reason will be displayed on the public verification page.
                </p>

                <div>
                    <label class="block text-xs font-semibold uppercase text-text-muted mb-1">Reason for Revocation</label>
                    <textarea
                        wire:model="revocationReason"
                        rows="3"
                        class="w-full text-sm rounded-lg border-border-default bg-surface-primary text-text-primary"
                        placeholder="e.g. Academic dishonesty, enrollment administrative cancellation, re-enrollment reset"
                    ></textarea>
                    @error('revocationReason') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-border-default">
                    <button type="button" wire:click="cancelRevoke" class="btn btn-secondary btn-sm">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmRevoke" class="btn btn-danger btn-sm">
                        Confirm Revocation
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Reinstatement Modal --}}
    @if ($showReinstateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-surface-primary border border-border-default rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-border-default">
                    <h3 class="text-lg font-bold text-text-primary">Reinstate Certificate</h3>
                    <button type="button" wire:click="cancelReinstate" class="text-text-muted hover:text-text-primary">&times;</button>
                </div>

                @if ($reinstatingCertificate)
                    <div class="p-3 rounded-lg bg-surface-secondary text-xs space-y-1">
                        <div class="flex justify-between">
                            <span class="text-text-muted">Certificate #:</span>
                            <span class="font-mono font-semibold text-text-primary">{{ $reinstatingCertificate->certificate_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-muted">Student:</span>
                            <span class="text-text-primary">{{ $reinstatingCertificate->user?->name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-muted">Course:</span>
                            <span class="text-text-primary">{{ $reinstatingCertificate->course?->title }}</span>
                        </div>
                    </div>
                @endif

                <p class="text-xs text-text-muted leading-relaxed">
                    Reinstating this certificate will restore its status to <strong>Reinstated</strong>. The public verification page will reflect the active, authenticated credential, and an immutable audit event will be recorded.
                </p>

                <div>
                    <label class="block text-xs font-semibold uppercase text-text-muted mb-1">Mandatory Reinstatement Reason *</label>
                    <textarea
                        wire:model="reinstatementReason"
                        rows="3"
                        class="w-full text-sm rounded-lg border-border-default bg-surface-primary text-text-primary"
                        placeholder="e.g. Administrative review complete, mistaken revocation corrected, identity re-verified"
                    ></textarea>
                    @error('reinstatementReason') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-border-default">
                    <button type="button" wire:click="cancelReinstate" class="btn btn-secondary btn-sm">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmReinstate" class="btn btn-primary btn-sm">
                        Confirm Reinstatement
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Issuance History & Details Modal --}}
    @if ($showIssuanceHistoryModal && $viewingCertificate)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-surface-primary border border-border-default rounded-xl shadow-xl max-w-lg w-full p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-default">
                    <h3 class="text-lg font-bold text-text-primary">Certificate Issuance Details</h3>
                    <button type="button" wire:click="closeIssuanceHistory" class="text-text-muted hover:text-text-primary">&times;</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="p-3 rounded-lg bg-surface-secondary space-y-1.5">
                        <div class="flex justify-between">
                            <span class="text-text-muted">Certificate Number:</span>
                            <span class="font-mono font-bold text-text-primary">{{ $viewingCertificate->certificate_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-muted">Status:</span>
                            <span class="font-semibold uppercase tracking-wider {{ $viewingCertificate->isRevoked() ? 'text-rose-500' : 'text-emerald-500' }}">
                                {{ $viewingCertificate->status }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-muted">Version:</span>
                            <span class="font-mono text-text-primary">v{{ $viewingCertificate->version ?? 1 }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-muted">Student:</span>
                            <span class="font-medium text-text-primary">{{ $viewingCertificate->user?->name }} ({{ $viewingCertificate->user?->email }})</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-muted">Course:</span>
                            <span class="text-text-primary font-medium">{{ $viewingCertificate->course?->title }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-muted">Issued Date:</span>
                            <span class="text-text-primary">{{ $viewingCertificate->issued_at?->format('F d, Y') ?? '—' }}</span>
                        </div>
                        @if ($viewingCertificate->revoked_at)
                            <div class="flex justify-between text-rose-500">
                                <span>Revoked Date:</span>
                                <span>{{ $viewingCertificate->revoked_at->format('F d, Y H:i:s') }}</span>
                            </div>
                            <div class="pt-1">
                                <span class="text-text-muted">Revocation Reason:</span>
                                <p class="text-rose-600 dark:text-rose-400 italic mt-0.5">{{ $viewingCertificate->revocation_reason }}</p>
                            </div>
                        @endif
                        @if ($viewingCertificate->reinstated_at)
                            <div class="flex justify-between text-sky-500">
                                <span>Reinstated Date:</span>
                                <span>{{ $viewingCertificate->reinstated_at->format('F d, Y H:i:s') }}</span>
                            </div>
                            <div class="pt-1">
                                <span class="text-text-muted">Reinstatement Reason:</span>
                                <p class="text-sky-600 dark:text-sky-400 italic mt-0.5">{{ $viewingCertificate->reinstatement_reason }}</p>
                            </div>
                        @endif
                    </div>

                    @if (!empty($viewingCertificate->issuance_snapshot))
                        <div class="p-3 rounded-lg bg-surface-secondary border border-border-default/60 space-y-1">
                            <div class="font-semibold text-text-primary mb-1">Issuance Snapshot Data:</div>
                            <div class="text-[11px] text-text-muted space-y-0.5">
                                @if (isset($viewingCertificate->issuance_snapshot['student']))
                                    <div>Student Name on Certificate: <strong class="text-text-secondary">{{ $viewingCertificate->issuance_snapshot['student']['name'] ?? '—' }}</strong></div>
                                @endif
                                @if (isset($viewingCertificate->issuance_snapshot['course']))
                                    <div>Course Title on Certificate: <strong class="text-text-secondary">{{ $viewingCertificate->issuance_snapshot['course']['title'] ?? '—' }}</strong></div>
                                @endif
                                @if (isset($viewingCertificate->issuance_snapshot['grade']))
                                    <div>Final Score / Grade: <strong class="text-text-secondary">{{ $viewingCertificate->issuance_snapshot['grade']['final_score'] ?? '—' }}%</strong></div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end pt-3 border-t border-border-default">
                    <button type="button" wire:click="closeIssuanceHistory" class="btn btn-secondary btn-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Reissuance Modal --}}
    @if ($showReissueModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-surface-primary border border-border-default rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
                <h3 class="text-lg font-bold text-text-primary">Reissue Certificate</h3>
                <p class="text-sm text-text-muted">
                    Reissuance creates a new certificate version, retains the previous version in history, and issues a fresh secure verification code.
                </p>

                <div>
                    <label class="block text-xs font-semibold uppercase text-text-muted mb-1">Reason for Reissuance</label>
                    <textarea
                        wire:model="reissueReason"
                        rows="3"
                        class="w-full text-sm rounded-lg border-border-default bg-surface-primary text-text-primary"
                        placeholder="e.g. Legal name correction, updated academic branding, template refresh"
                    ></textarea>
                    @error('reissueReason') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-border-default">
                    <button type="button" wire:click="cancelReissue" class="btn btn-secondary btn-sm">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmReissue" class="btn btn-primary btn-sm">
                        Confirm Reissue
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Audit History Modal --}}
    @if ($showAuditHistoryModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-surface-primary border border-border-default rounded-xl shadow-xl max-w-lg w-full p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-default">
                    <h3 class="text-lg font-bold text-text-primary">Certificate Audit History</h3>
                    <button type="button" wire:click="closeAuditHistory" class="text-text-muted hover:text-text-primary">&times;</button>
                </div>

                <div class="space-y-3 max-h-80 overflow-y-auto">
                    @forelse ($auditEventsList as $event)
                        <div class="p-3 rounded-lg bg-surface-secondary text-xs space-y-1">
                            <div class="flex justify-between items-center">
                                <span class="font-semibold uppercase tracking-wider text-primary-600">{{ $event['event_type'] }}</span>
                                <span class="text-text-muted">{{ $event['created_at'] }}</span>
                            </div>
                            <div class="text-text-primary">{{ $event['reason'] }}</div>
                            <div class="text-[10px] text-text-muted">By: {{ $event['actor'] }}</div>
                        </div>
                    @empty
                        <div class="text-center text-text-muted text-sm py-4">No audit events recorded.</div>
                    @endforelse
                </div>

                <div class="flex justify-end pt-3 border-t border-border-default">
                    <button type="button" wire:click="closeAuditHistory" class="btn btn-secondary btn-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Manual Issue Certificate Modal --}}
    @if ($showIssueModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-surface-primary border border-border-default rounded-xl shadow-xl max-w-lg w-full p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-default">
                    <h3 class="text-lg font-bold text-text-primary">Issue New Certificate</h3>
                    <button type="button" wire:click="$set('showIssueModal', false)" class="text-text-muted hover:text-text-primary">&times;</button>
                </div>

                <div class="space-y-4">
                    @error('issue_general')
                        <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs font-semibold">
                            {{ $message }}
                        </div>
                    @enderror

                    <div>
                        <label class="block text-xs font-semibold uppercase text-text-muted mb-1">
                            Completed Student Enrollment *
                        </label>
                        @if ($eligibleEnrollments->isEmpty())
                            <p class="text-xs text-text-secondary italic p-3 rounded-lg bg-surface-secondary border border-border-default">
                                No eligible completed enrollments currently awaiting certificates. Students must complete their course and receive a passing grade before a certificate can be issued.
                            </p>
                        @else
                            <select wire:model="issueEnrollmentId" class="w-full text-sm rounded-lg border-border-default bg-surface-primary text-text-primary">
                                <option value="">Select a student enrollment...</option>
                                @foreach ($eligibleEnrollments as $enr)
                                    <option value="{{ $enr->id }}">
                                        {{ $enr->studentProfile?->user?->name ?? 'Student' }} — {{ $enr->course?->title ?? 'Course' }} (Completed {{ $enr->completed_at?->format('M d, Y') ?? 'Recently' }})
                                    </option>
                                @endforeach
                            </select>
                        @endif
                        @error('issueEnrollmentId') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-text-muted mb-1">
                            Certificate Template Design *
                        </label>
                        <select wire:model="issueTemplate" class="w-full text-sm rounded-lg border-border-default bg-surface-primary text-text-primary">
                            @if($customTemplates->isNotEmpty())
                                <optgroup label="Custom Designer Templates">
                                    @foreach($customTemplates as $ct)
                                        <option value="{{ $ct->id }}">{{ $ct->name }} (Designer)</option>
                                    @endforeach
                                </optgroup>
                            @endif
                            <optgroup label="Standard Templates">
                                <option value="classic">Classic (Double Border & Gold Accent)</option>
                                <option value="modern">Modern Minimalist (Clean Indigo & Dark Header)</option>
                                <option value="minimalist">Clean & Minimal (Emerald Accent)</option>
                            </optgroup>
                        </select>
                        @error('issueTemplate') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t border-border-default">
                    <button type="button" wire:click="$set('showIssueModal', false)" class="btn btn-secondary btn-sm">
                        Cancel
                    </button>
                    <button
                        type="button"
                        wire:click="issueCertificateForEnrollment"
                        @disabled($eligibleEnrollments->isEmpty())
                        class="btn btn-primary btn-sm"
                    >
                        Issue Certificate
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

