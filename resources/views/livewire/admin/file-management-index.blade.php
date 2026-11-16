<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-text-primary">File &amp; Storage Management</h1>
            <p class="text-sm text-text-muted mt-1">
                Inspect managed course media, assignments, lesson files, and monitor academy storage capacity.
            </p>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-sm text-emerald-800 dark:text-emerald-200 flex items-center justify-between">
            <span>{{ session('success') }}</span>
        </div>
    @endif

    {{-- Metrics Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card p-5">
            <span class="text-xs font-medium text-text-muted uppercase tracking-wider">Managed Storage</span>
            <div class="text-2xl font-bold text-text-primary mt-1">
                {{ $storageStats['total_managed_formatted'] }}
            </div>
            <span class="text-xs text-text-muted mt-1 block">Total LMS uploaded files</span>
        </div>

        <div class="card p-5">
            <span class="text-xs font-medium text-text-muted uppercase tracking-wider">Total Files</span>
            <div class="text-2xl font-bold text-text-primary mt-1">
                {{ number_format($storageStats['total_files']) }}
            </div>
            <span class="text-xs text-text-muted mt-1 block">Active media items</span>
        </div>

        <div class="card p-5">
            <span class="text-xs font-medium text-text-muted uppercase tracking-wider">Storage Free Space</span>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                {{ $storageStats['disk_free_formatted'] }}
            </div>
            <span class="text-xs text-text-muted mt-1 block">Local storage capacity</span>
        </div>

        <div class="card p-5">
            <span class="text-xs font-medium text-text-muted uppercase tracking-wider">Total Disk Capacity</span>
            <div class="text-2xl font-bold text-text-secondary mt-1">
                {{ $storageStats['disk_total_formatted'] }}
            </div>
            <span class="text-xs text-text-muted mt-1 block">Filesystem volume</span>
        </div>
    </div>

    {{-- Search & Filters --}}
    <div class="card p-4 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-text-muted mb-1">Search</label>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by filename or MIME..."
                    class="input text-xs"
                >
            </div>

            <div>
                <label class="block text-xs font-medium text-text-muted mb-1">Collection</label>
                <select wire:model.live="collection" class="input text-xs">
                    <option value="all">All Collections</option>
                    @foreach ($availableCollections as $col)
                        <option value="{{ $col }}">{{ ucfirst(str_replace('_', ' ', $col)) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-text-muted mb-1">Storage Disk</label>
                <select wire:model.live="disk" class="input text-xs">
                    <option value="all">All Disks</option>
                    @foreach ($availableDisks as $d)
                        <option value="{{ $d }}">{{ $d }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-text-muted mb-1">Sort By</label>
                <select wire:model.live="sort" class="input text-xs">
                    <option value="latest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="size_desc">Largest Size</option>
                    <option value="size_asc">Smallest Size</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Files Table --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-text-secondary">
                <thead class="bg-surface-elevated/70 text-text-muted font-semibold border-b border-border-default uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="px-4 py-3">File / Original Name</th>
                        <th class="px-4 py-3">Collection</th>
                        <th class="px-4 py-3">MIME Type</th>
                        <th class="px-4 py-3">Size</th>
                        <th class="px-4 py-3">Disk</th>
                        <th class="px-4 py-3">Uploaded</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-default">
                    @forelse ($files as $file)
                        <tr wire:key="file-{{ $file->id }}" class="hover:bg-surface-elevated/40 transition-colors">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-text-primary truncate max-w-xs" title="{{ $file->filename }}">
                                    {{ $file->filename }}
                                </div>
                                <div class="text-[10px] text-text-muted font-mono truncate max-w-xs" title="{{ $file->id }}">
                                    ID: {{ $file->id }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider bg-surface-elevated text-text-secondary">
                                    {{ $file->collection }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-[11px] text-text-muted">
                                {{ $file->mime_type ?: 'application/octet-stream' }}
                            </td>
                            <td class="px-4 py-3 font-semibold text-text-primary">
                                {{ $fileService->formatBytes($file->size_bytes) }}
                            </td>
                            <td class="px-4 py-3 font-mono text-[11px]">
                                {{ $file->disk }}
                            </td>
                            <td class="px-4 py-3 text-text-muted text-[11px]">
                                {{ $file->created_at?->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <a
                                    href="{{ route('media.show', $file) }}"
                                    target="_blank"
                                    class="inline-flex items-center text-xs font-semibold text-primary-600 hover:underline"
                                    title="View / Stream via Private Media Controller"
                                >
                                    Stream
                                </a>

                                <button
                                    type="button"
                                    wire:click="deleteFile('{{ $file->id }}')"
                                    wire:confirm="Are you sure you want to permanently remove this media file? This cannot be undone."
                                    class="text-xs font-semibold text-rose-600 hover:underline"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-text-muted">
                                No managed files found matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($files->hasPages())
            <div class="p-4 border-t border-border-default">
                {{ $files->links() }}
            </div>
        @endif
    </div>
</div>

