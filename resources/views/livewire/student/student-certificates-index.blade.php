<div class="space-y-6">
    <x-page-header
        title="My Certificates"
        subtitle="View and download your official certificates of completion for completed courses."
    />

    @if ($certificates->isEmpty())
        <x-card>
            <x-empty-state
                title="No certificates earned yet"
                message="Complete all lessons and required assessments in your enrolled courses to earn verifiable certificates."
            >
                <x-slot:action>
                    <a href="{{ route('student.courses.index') }}" class="btn btn-primary btn-sm">
                        Go to My Courses
                    </a>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($certificates as $cert)
                @php
                    $isDownloadable = $cert->isIssued() || $cert->isReinstated();
                @endphp
                <x-card wire:key="cert-card-{{ $cert->id }}" class="flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-primary-600">
                                Verified Certificate
                            </span>
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
                                    Superseded
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">
                                    Active
                                </span>
                            @endif
                        </div>

                        <h3 class="text-lg font-bold text-text-primary">
                            {{ $cert->course?->title ?? 'Course' }}
                        </h3>

                        <div class="text-xs text-text-muted space-y-1 pt-2 border-t border-border-default">
                            <div class="flex justify-between">
                                <span>Certificate #:</span>
                                <span class="font-mono font-medium text-text-secondary">{{ $cert->certificate_number }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Issue Date:</span>
                                <span class="font-medium text-text-secondary">{{ $cert->issued_at?->format('M d, Y') }}</span>
                            </div>
                            @if ($cert->version > 1)
                                <div class="flex justify-between">
                                    <span>Version:</span>
                                    <span class="font-medium text-text-secondary">v{{ $cert->version }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- Privacy & Consent Management --}}
                        <div class="pt-2 border-t border-border-default">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-text-muted">Public Name:</span>
                                <button
                                    type="button"
                                    wire:click="toggleConsent('{{ $cert->id }}')"
                                    class="inline-flex items-center gap-1.5 px-2 py-1 rounded text-xs font-medium transition {{ $cert->public_identity_consent ? 'bg-emerald-50 text-emerald-700 border border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-700' : 'bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700' }}"
                                >
                                    @if ($cert->public_identity_consent)
                                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <span>Visible</span>
                                    @else
                                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                                        <span>Masked</span>
                                    @endif
                                </button>
                            </div>
                        </div>

                        @if (! $isDownloadable)
                            <div class="p-2 rounded bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-300">
                                @if ($cert->isUnderAudit())
                                    This certificate is under academic review. Downloads are temporarily paused.
                                @elseif ($cert->isRevoked())
                                    This certificate was revoked and is no longer downloadable as a valid credential.
                                @elseif ($cert->isSuperseded())
                                    This credential version was superseded by a reissued certificate.
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="pt-4 mt-4 border-t border-border-default flex items-center justify-between gap-3">
                        <a
                            href="{{ route('certificates.verify', $cert->verification_code ?: $cert->certificate_number) }}"
                            target="_blank"
                            class="btn btn-secondary btn-sm flex-1 text-center"
                        >
                            Verify Online
                        </a>
                        @if ($isDownloadable)
                            <a
                                href="{{ route('certificates.download', $cert) }}"
                                class="btn btn-primary btn-sm flex-1 text-center"
                            >
                                Download PDF
                            </a>
                        @else
                            <button
                                type="button"
                                disabled
                                class="btn btn-secondary btn-sm flex-1 text-center opacity-50 cursor-not-allowed"
                            >
                                Download PDF
                            </button>
                        @endif
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</div>

