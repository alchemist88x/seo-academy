<div class="space-y-6">
    <x-page-header
        title="Certificates & Credentials"
        subtitle="Manage issued academic certificates, customize templates, and handle credential verifications."
    />

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
                    <option value="classic">Classic (Double Border & Gold Accent)</option>
                    <option value="modern">Modern Minimalist (Clean Indigo & Dark Header)</option>
                    <option value="minimalist">Clean & Minimal (Emerald Accent)</option>
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
                                    wire:click="viewAuditHistory('{{ $cert->id }}')"
                                    class="text-xs text-slate-500 hover:underline"
                                >
                                    Audit
                                </button>
                                @if ($isAdmin)
                                    @if (! $cert->isRevoked() && ! $cert->isSuperseded())
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
</div>

