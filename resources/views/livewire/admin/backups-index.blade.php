<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-text-primary">Backups &amp; Disaster Recovery</h1>
            <p class="text-sm text-text-muted mt-1">
                Point-in-time database snapshots, private media archives, full backup sets, checksum integrity verification, and safe restoration workflows.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                wire:click="createFullBackupSet"
                wire:loading.attr="disabled"
                wire:target="createFullBackupSet"
                class="btn btn-primary text-xs flex items-center gap-1.5"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                </svg>
                <span wire:loading.remove wire:target="createFullBackupSet">Create Full Backup Set</span>
                <span wire:loading wire:target="createFullBackupSet">Creating Set...</span>
            </button>

            <button
                type="button"
                wire:click="createDatabaseBackup"
                wire:loading.attr="disabled"
                wire:target="createDatabaseBackup"
                class="btn btn-secondary text-xs flex items-center gap-1.5"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                </svg>
                <span wire:loading.remove wire:target="createDatabaseBackup">Backup DB Only</span>
                <span wire:loading wire:target="createDatabaseBackup">Backing up...</span>
            </button>

            <button
                type="button"
                wire:click="createFilesBackup"
                wire:loading.attr="disabled"
                wire:target="createFilesBackup"
                class="btn btn-secondary text-xs flex items-center gap-1.5"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                </svg>
                <span wire:loading.remove wire:target="createFilesBackup">Backup Files Only</span>
                <span wire:loading wire:target="createFilesBackup">Archiving...</span>
            </button>

            <button
                type="button"
                wire:click="applyRetention"
                wire:loading.attr="disabled"
                wire:target="applyRetention"
                wire:confirm="This will prune expired backups while preserving recent backups and the last known-good backup. Proceed?"
                class="btn btn-secondary text-xs flex items-center gap-1.5"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                <span wire:loading.remove wire:target="applyRetention">Apply Retention (Keep 5)</span>
                <span wire:loading wire:target="applyRetention">Pruning...</span>
            </button>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-sm text-emerald-800 dark:text-emerald-200">
            ✓ {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-sm text-rose-800 dark:text-rose-200 font-medium">
            ✕ {{ session('error') }}
        </div>
    @endif

    {{-- Restore Confirmation Modal / Card --}}
    @if ($restoreCandidate)
        <div class="p-6 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border-2 border-amber-300 dark:border-amber-800 space-y-4">
            <div class="flex items-start gap-3">
                <div class="p-2 rounded-xl bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-amber-900 dark:text-amber-100">
                        Confirm Disaster Recovery Restoration
                    </h3>
                    <p class="text-xs text-amber-800 dark:text-amber-200 mt-1 leading-relaxed">
                        Restoring <strong>{{ $restoreCandidate->filename }}</strong> will replace your active {{ $restoreCandidate->type }} with the contents of this backup. A safety backup of your current state will automatically be created before proceeding.
                    </p>
                    @if (! empty($restoreCandidate->metadata['backup_set_id']))
                        <div class="mt-2 text-xs font-semibold text-amber-950 dark:text-amber-200">
                            Linked Backup Set: <span class="font-mono bg-amber-200/60 dark:bg-amber-900/60 px-1.5 py-0.5 rounded">{{ $restoreCandidate->metadata['backup_set_id'] }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="pt-2 flex flex-col sm:flex-row items-center gap-3">
                <input
                    type="text"
                    wire:model="restoreConfirmationText"
                    placeholder="Type RESTORE to confirm"
                    class="input text-xs sm:w-64 border-amber-300 dark:border-amber-700"
                >

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="executeRestore"
                        wire:loading.attr="disabled"
                        class="btn bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold px-4 py-2 rounded-lg"
                    >
                        <span wire:loading.remove wire:target="executeRestore">Confirm &amp; Restore</span>
                        <span wire:loading wire:target="executeRestore">Restoring...</span>
                    </button>

                    @if (! empty($restoreCandidate->metadata['backup_set_id']))
                        <button
                            type="button"
                            wire:click="restoreBackupSet('{{ $restoreCandidate->metadata['backup_set_id'] }}')"
                            wire:confirm="This will restore BOTH the database snapshot and private media files associated with set [{{ $restoreCandidate->metadata['backup_set_id'] }}]. Proceed?"
                            class="btn bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold px-4 py-2 rounded-lg"
                        >
                            Restore Complete Set (DB + Files)
                        </button>
                    @endif

                    <button
                        type="button"
                        wire:click="cancelRestore"
                        class="btn btn-secondary text-xs"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Backups Table --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-text-secondary">
                <thead class="bg-surface-elevated/70 text-text-muted font-semibold border-b border-border-default uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="px-4 py-3">Archive Filename / Set</th>
                        <th class="px-4 py-3">Type / Driver</th>
                        <th class="px-4 py-3">Size</th>
                        <th class="px-4 py-3">Created</th>
                        <th class="px-4 py-3">Integrity Checksum (SHA-256)</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-default">
                    @forelse ($backups as $backup)
                        <tr wire:key="backup-{{ $backup->id }}" class="hover:bg-surface-elevated/40 transition-colors">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-text-primary font-mono truncate max-w-xs" title="{{ $backup->filename }}">
                                    {{ $backup->filename }}
                                </div>
                                <div class="text-[10px] text-text-muted mt-0.5 flex items-center gap-2">
                                    <span>Disk: {{ $backup->disk }}</span>
                                    @if (! empty($backup->metadata['backup_set_id']))
                                        <span class="inline-flex items-center px-1.5 py-0.2 rounded font-mono bg-surface-elevated text-text-secondary border border-border-default">
                                            Set: {{ $backup->metadata['backup_set_id'] }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider {{ $backup->type === 'database' ? 'bg-primary-100 text-primary-800 dark:bg-primary-950/60 dark:text-primary-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' }}">
                                    {{ $backup->type }} ({{ $backup->database_driver }})
                                </span>
                            </td>
                            <td class="px-4 py-3 font-semibold text-text-primary">
                                {{ $fileService->formatBytes($backup->size_bytes) }}
                            </td>
                            <td class="px-4 py-3 text-text-muted text-[11px] whitespace-nowrap">
                                {{ $backup->created_at?->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-4 py-3 font-mono text-[10px] text-text-muted truncate max-w-[150px]" title="{{ $backup->checksum }}">
                                {{ substr($backup->checksum ?? 'N/A', 0, 16) }}...
                            </td>
                            <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                <a
                                    href="{{ route('admin.backups.download', $backup) }}"
                                    class="text-xs font-semibold text-primary-600 hover:underline"
                                    title="Download archive securely"
                                >
                                    Download
                                </a>

                                <button
                                    type="button"
                                    wire:click="validateBackup('{{ $backup->id }}')"
                                    class="text-xs font-semibold text-emerald-600 hover:underline"
                                    title="Test archive checksum & structure"
                                >
                                    Validate
                                </button>

                                <button
                                    type="button"
                                    wire:click="confirmRestore('{{ $backup->id }}')"
                                    class="text-xs font-semibold text-amber-600 hover:underline"
                                    title="Initiate safe restoration"
                                >
                                    Restore
                                </button>

                                <button
                                    type="button"
                                    wire:click="deleteBackup('{{ $backup->id }}')"
                                    wire:confirm="Are you sure you want to delete this backup file?"
                                    class="text-xs font-semibold text-rose-600 hover:underline"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-text-muted">
                                No backup archives found. Click "Create Full Backup Set", "Backup DB Only", or "Backup Files Only" above to create your first point-in-time snapshot.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($backups->hasPages())
            <div class="p-4 border-t border-border-default">
                {{ $backups->links() }}
            </div>
        @endif
    </div>
</div>
