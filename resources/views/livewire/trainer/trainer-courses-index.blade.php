@php
    use App\Domain\Courses\Models\Course;
@endphp

<div class="space-y-6">
    <x-page-header
        title="My Assigned Courses"
        :subtitle="'Courses assigned to you in ' . $academy->name . '.'"
    />

    {{-- Filters --}}
    <x-card>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div class="md:col-span-2">
                <x-input
                    name="search"
                    label="Search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search assigned courses..."
                />
            </div>

            <x-select
                name="status"
                label="Status"
                wire:model.live="status"
                placeholder="All Statuses"
                :options="array_combine(Course::STATUSES, Course::STATUSES)"
            />

            <x-select
                name="difficulty"
                label="Difficulty"
                wire:model.live="difficulty"
                placeholder="All Levels"
                :options="array_combine(Course::DIFFICULTIES, Course::DIFFICULTIES)"
            />
        </div>

        @if ($search !== '' || $status !== '' || $difficulty !== '')
            <div class="mt-3 flex justify-end">
                <button
                    type="button"
                    wire:click="resetFilters"
                    class="text-xs font-medium text-primary-600 hover:underline"
                >
                    Clear filters
                </button>
            </div>
        @endif
    </x-card>

    {{-- Courses Table --}}
    <x-card :padding="false">
        @if ($courses->isEmpty())
            <x-empty-state
                title="No assigned courses"
                message="You are not currently assigned to any courses matching these filters."
            />
        @else
            <x-table :striped="true">
                <x-slot:header>
                    <th>Course</th>
                    <th>Category</th>
                    <th>Difficulty</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </x-slot:header>

                <x-slot:body>
                    @foreach ($courses as $course)
                        <tr wire:key="trainer-course-{{ $course->id }}">
                            <td>
                                <div class="font-medium text-text-primary">{{ $course->title }}</div>
                                <div class="text-xs text-text-muted">/{{ $course->slug }}</div>
                            </td>
                            <td class="text-sm text-text-secondary">
                                {{ $course->category?->name ?? 'Uncategorized' }}
                            </td>
                            <td class="text-sm text-text-secondary">
                                {{ $course->difficulty }}
                            </td>
                            <td>
                                @php
                                    $statusColor = match ($course->status) {
                                        'Published', 'Active' => 'green',
                                        'Draft' => 'yellow',
                                        'Unpublished' => 'gray',
                                        'Archived' => 'red',
                                        default => 'gray',
                                    };
                                @endphp
                                <x-badge :color="$statusColor">{{ $course->status }}</x-badge>
                            </td>
                            <td class="text-right">
                                <div class="inline-flex items-center justify-end gap-2">
                                    @can('preview', $course)
                                        <a href="{{ route('admin.courses.preview', $course) }}" class="btn btn-secondary btn-sm">
                                            Preview
                                        </a>
                                    @endcan

                                    @can('update', $course)
                                        <a href="{{ route('trainer.courses.structure', $course) }}" class="btn btn-secondary btn-sm">
                                            Structure
                                        </a>

                                        <x-button variant="primary" size="sm" wire:click="openEditModal('{{ $course->id }}')">
                                            Edit
                                        </x-button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-table>

            @if ($courses->hasPages())
                <div class="px-5 py-3 border-t border-border-default">
                    {{ $courses->links() }}
                </div>
            @endif
        @endif
    </x-card>

    {{-- Edit Course Modal --}}
    <x-modal name="trainer-course-modal" title="Edit Assigned Course" maxWidth="lg">
        <form wire:submit="saveCourse" class="space-y-4">
            <x-input
                name="title"
                label="Course Title"
                wire:model="title"
                required
            />

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-select
                    name="category_id"
                    label="Category"
                    wire:model="category_id"
                    placeholder="Uncategorized"
                    :options="$categories->pluck('name', 'id')->all()"
                />

                <x-select
                    name="courseDifficulty"
                    label="Difficulty"
                    wire:model="courseDifficulty"
                    :options="array_combine(Course::DIFFICULTIES, Course::DIFFICULTIES)"
                />

                <x-input
                    name="duration_months"
                    label="Duration (Months)"
                    type="number"
                    min="0"
                    max="120"
                    wire:model="duration_months"
                />
            </div>

            <div>
                <label class="form-label" for="trainer-course-thumbnail">Thumbnail Image</label>
                <div class="flex items-center gap-4">
                    @if ($thumbnail)
                        <img src="{{ $thumbnail->temporaryUrl() }}" alt="Preview" class="h-16 w-24 rounded object-cover border border-border-default" />
                    @elseif ($existingThumbnailUrl)
                        <img src="{{ $existingThumbnailUrl }}" alt="Current thumbnail" class="h-16 w-24 rounded object-cover border border-border-default" />
                    @endif
                    <input
                        id="trainer-course-thumbnail"
                        type="file"
                        accept="image/*"
                        wire:model="thumbnail"
                        class="block w-full text-sm text-text-secondary file:mr-4 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100"
                    />
                </div>
                @error('thumbnail')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="trainer-course-description" class="form-label">Description</label>
                <textarea
                    id="trainer-course-description"
                    rows="4"
                    wire:model="description"
                    class="form-input @error('description') error @enderror"
                ></textarea>
                @error('description')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-3 border-t border-border-default flex justify-end gap-2">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'trainer-course-modal')">
                    Cancel
                </x-button>
                <x-button variant="primary" type="submit" wire:loading.attr="disabled">
                    Save Changes
                </x-button>
            </div>
        </form>
    </x-modal>
</div>
