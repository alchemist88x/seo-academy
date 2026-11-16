<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border-default">
        <div>
            <h1 class="text-2xl font-bold text-text-primary">My Practical Assignments</h1>
            <p class="text-xs text-text-secondary mt-1">View required coursework, submit your practical deliverables, and review trainer evaluations.</p>
        </div>
        <div class="flex items-center gap-2">
            <select wire:model.live="statusFilter" class="input text-xs w-44">
                <option value="all">All Assignments</option>
                <option value="pending">To Do / Revisions</option>
                <option value="submitted">Under Review</option>
                <option value="graded">Graded</option>
            </select>
        </div>
    </div>

    {{-- Assignments List --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($assignmentsData as $item)
            @php
                $assignment = $item['assignment'];
                $sub = $item['submission'];
                $eval = $item['evaluation'];
                $status = $item['status'];
            @endphp
            <x-card class="flex flex-col justify-between hover:border-brand-primary/40 transition-colors">
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[11px] font-medium text-text-secondary truncate max-w-[180px]">
                            {{ $assignment->course?->title }}
                        </span>
                        @if ($assignment->is_mandatory)
                            <span class="badge badge-primary text-[10px]">Mandatory</span>
                        @endif
                    </div>

                    <h3 class="text-base font-bold text-text-primary leading-snug">
                        {{ $assignment->title }}
                    </h3>

                    @if ($assignment->description)
                        <p class="text-xs text-text-secondary line-clamp-2">
                            {{ $assignment->description }}
                        </p>
                    @endif

                    <div class="pt-2 border-t border-border-default/60 space-y-1.5 text-xs text-text-secondary">
                        <div class="flex items-center justify-between">
                            <span>Due Date:</span>
                            <span class="font-medium text-text-primary">
                                {{ $assignment->due_date ? $assignment->due_date->format('M d, Y') : 'No deadline' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Max Marks:</span>
                            <span class="font-medium text-text-primary">
                                {{ number_format((float) $assignment->max_marks, 2) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="pt-4 mt-4 border-t border-border-default flex items-center justify-between">
                    <div>
                        @if ($status === 'graded')
                            <div class="flex items-center gap-1.5">
                                <span class="badge {{ $eval?->is_passed ? 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-600 border border-rose-500/20' }}">
                                    {{ $eval?->is_passed ? 'Passed' : 'Needs Review' }}
                                </span>
                                <span class="text-xs font-bold text-text-primary">
                                    {{ number_format((float) $eval?->points_awarded, 1) }} / {{ number_format((float) $assignment->max_marks, 1) }}
                                </span>
                            </div>
                        @elseif ($status === 'revision_requested')
                            <span class="badge bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                Revision Requested
                            </span>
                        @elseif (in_array($status, ['submitted', 'resubmitted', 'under_review']))
                            <span class="badge bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                                v{{ $sub?->version_number }} Under Review
                            </span>
                        @else
                            <span class="badge bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/20">
                                Not Submitted
                            </span>
                        @endif
                    </div>

                    <a
                        href="{{ route('student.courses.assignments.show', ['course' => $assignment->course_id, 'assignment' => $assignment->id]) }}"
                        class="btn btn-secondary btn-xs inline-flex items-center gap-1"
                    >
                        <span>{{ $sub ? 'View Work' : 'Open' }}</span>
                        <span>&rarr;</span>
                    </a>
                </div>
            </x-card>
        @empty
            <div class="col-span-full py-12 text-center text-text-muted">
                No practical assignments found for your enrolled courses.
            </div>
        @endforelse
    </div>
</div>
