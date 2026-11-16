@php
    use App\Domain\Courses\Models\Course;
    use App\Domain\Courses\Models\CourseCategory;
@endphp

<div class="space-y-6">
    <x-page-header
        title="Courses"
        :subtitle="'Manage course catalogue, categories, and trainers for ' . $academy->name . '.'"
    >
        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-2">
                @can('viewAny', [CourseCategory::class, $academy])
                    <x-button variant="secondary" wire:click="openCategoriesModal">
                        Manage Categories
                    </x-button>
                @endcan

                @can('create', [Course::class, $academy])
                    <x-button variant="primary" wire:click="openCreateModal">
                        + Create Course
                    </x-button>
                @endcan
            </div>
        </x-slot:actions>
    </x-page-header>

    {{-- Filters --}}
    <x-card>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
            <div class="sm:col-span-2 lg:col-span-2">
                <x-input
                    name="search"
                    label="Search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by title or description..."
                />
            </div>

            <x-select
                name="categoryId"
                label="Category"
                wire:model.live="categoryId"
                placeholder="All Categories"
                :options="$categories->pluck('name', 'id')->all()"
            />

            <x-select
                name="difficulty"
                label="Difficulty"
                wire:model.live="difficulty"
                placeholder="All Levels"
                :options="array_combine(Course::DIFFICULTIES, Course::DIFFICULTIES)"
            />

            <x-select
                name="status"
                label="Status"
                wire:model.live="status"
                placeholder="All Statuses"
                :options="array_combine(Course::STATUSES, Course::STATUSES)"
            />

            <x-select
                name="visibility"
                label="Visibility"
                wire:model.live="visibility"
                placeholder="All Visibilities"
                :options="[
                    'public' => 'Public',
                    'academy_only' => 'Academy Only',
                    'private' => 'Private',
                ]"
            />
        </div>

        <div class="mt-4 pt-3 border-t border-border-default flex flex-wrap items-center justify-between gap-3">
            <div class="w-48">
                <x-select
                    name="sort"
                    label="Sort By"
                    wire:model.live="sort"
                    :options="[
                        'newest' => 'Newest First',
                        'title' => 'Title (A-Z)',
                        'price_asc' => 'Price: Low to High',
                        'price_desc' => 'Price: High to Low',
                    ]"
                />
            </div>

            @if ($search !== '' || $categoryId !== '' || $difficulty !== '' || $status !== '' || $visibility !== '' || $sort !== 'newest')
                <button
                    type="button"
                    wire:click="resetFilters"
                    class="text-xs font-medium text-primary-600 hover:underline"
                >
                    Clear filters
                </button>
            @endif
        </div>
    </x-card>

    {{-- Courses Table --}}
    <x-card :padding="false">
        @if ($courses->isEmpty())
            <x-empty-state
                title="No courses found"
                message="No courses match your current search or filter criteria."
            >
                @can('create', [Course::class, $academy])
                    <x-slot:action>
                        <x-button variant="primary" size="sm" wire:click="openCreateModal">
                            Create First Course
                        </x-button>
                    </x-slot:action>
                @endcan
            </x-empty-state>
        @else
            <x-table :striped="true">
                <x-slot:header>
                    <th>Course</th>
                    <th>Category</th>
                    <th>Level &amp; Price</th>
                    <th>Status</th>
                    <th>Visibility</th>
                    <th class="text-right">Actions</th>
                </x-slot:header>

                <x-slot:body>
                    @foreach ($courses as $course)
                        <tr wire:key="course-{{ $course->id }}">
                            <td>
                                <div class="flex items-center gap-3">
                                    @if ($course->thumbnailUrl())
                                        <img
                                            src="{{ $course->thumbnailUrl() }}"
                                            alt="{{ $course->title }}"
                                            class="h-11 w-16 rounded object-cover border border-border-default shrink-0"
                                        />
                                    @else
                                        <div class="h-11 w-16 rounded bg-border-muted flex items-center justify-center text-xs font-semibold text-text-muted shrink-0">
                                            SEO
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <div class="font-medium text-text-primary truncate">{{ $course->title }}</div>
                                        <div class="text-xs text-text-muted truncate">/{{ $course->slug }}</div>
                                        @if ($course->trainers->isNotEmpty())
                                            <div class="text-xs text-text-muted truncate">
                                                Trainers: {{ $course->trainers->pluck('name')->implode(', ') }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="text-sm text-text-secondary">
                                {{ $course->category?->name ?? 'Uncategorized' }}
                            </td>
                            <td class="text-sm">
                                <div class="text-text-primary font-medium">
                                    {{ $course->is_free ? 'Free' : $course->currency . ' ' . number_format((float) $course->price, 2) }}
                                </div>
                                <div class="text-xs text-text-muted">{{ $course->difficulty }}</div>
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
                            <td>
                                @php
                                    $visColor = match ($course->visibility) {
                                        'public' => 'blue',
                                        'academy_only' => 'yellow',
                                        default => 'gray',
                                    };
                                    $visLabel = match ($course->visibility) {
                                        'public' => 'Public',
                                        'academy_only' => 'Academy Only',
                                        default => 'Private',
                                    };
                                @endphp
                                <x-badge :color="$visColor">{{ $visLabel }}</x-badge>
                            </td>
                            <td class="text-right">
                                <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                    @can('preview', $course)
                                        <a
                                            href="{{ route('admin.courses.preview', $course) }}"
                                            class="btn btn-secondary btn-sm"
                                        >
                                            Preview
                                        </a>
                                    @endcan

                                    @can('update', $course)
                                        <a
                                            href="{{ route('admin.courses.structure', $course) }}"
                                            class="btn btn-secondary btn-sm"
                                        >
                                            Structure
                                        </a>

                                        <a
                                            href="{{ route('admin.courses.gradebook', $course) }}"
                                            class="btn btn-secondary btn-sm"
                                            title="View Course Gradebook"
                                        >
                                            Gradebook
                                        </a>

                                        <x-button variant="secondary" size="sm" wire:click="openEditModal('{{ $course->id }}')">
                                            Edit
                                        </x-button>
                                    @endcan

                                    @can('publish', $course)
                                        @if ($course->status !== 'Published')
                                            <x-button
                                                variant="primary"
                                                size="sm"
                                                wire:click="transitionStatus('{{ $course->id }}', 'Published')"
                                                wire:loading.attr="disabled"
                                            >
                                                Publish
                                            </x-button>
                                        @else
                                            <x-button
                                                variant="secondary"
                                                size="sm"
                                                wire:click="transitionStatus('{{ $course->id }}', 'Unpublished')"
                                                wire:loading.attr="disabled"
                                            >
                                                Unpublish
                                            </x-button>
                                        @endif
                                    @endcan

                                    @can('archive', $course)
                                        @if ($course->status !== 'Archived')
                                            <x-button
                                                variant="secondary"
                                                size="sm"
                                                wire:click="transitionStatus('{{ $course->id }}', 'Archived')"
                                                wire:loading.attr="disabled"
                                            >
                                                Archive
                                            </x-button>
                                        @endif
                                    @endcan

                                    @can('delete', $course)
                                        <x-button
                                            variant="danger"
                                            size="sm"
                                            wire:click="openDeleteModal('{{ $course->id }}')"
                                        >
                                            Delete
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

    {{-- Create / Edit Course Modal --}}
    <x-modal name="course-form-modal" :title="$editingCourseId ? 'Edit Course' : 'Create Course'" maxWidth="xl">
        <form wire:submit="saveCourse" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input
                    name="title"
                    label="Course Title"
                    wire:model.live.debounce.250ms="title"
                    required
                />

                <x-input
                    name="slug"
                    label="Slug"
                    wire:model="slug"
                    placeholder="auto-generated-from-title"
                />

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

                <x-select
                    name="courseVisibility"
                    label="Visibility"
                    wire:model="courseVisibility"
                    :options="[
                        'public' => 'Public (Guest & Student)',
                        'academy_only' => 'Academy Only (Authenticated Members)',
                        'private' => 'Private (Hidden from Catalogue)',
                    ]"
                />

                <x-select
                    name="courseStatus"
                    label="Status"
                    wire:model="courseStatus"
                    :options="array_combine(Course::STATUSES, Course::STATUSES)"
                />
            </div>

            {{-- Pricing & Limits --}}
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                <div class="flex items-center gap-2 pb-2">
                    <input
                        id="course-is-free"
                        type="checkbox"
                        wire:model.live="is_free"
                        class="rounded border-border-default text-primary-600 focus:ring-primary-500"
                    />
                    <label for="course-is-free" class="text-sm font-medium text-text-primary">Free Course</label>
                </div>

                <x-input
                    name="price"
                    label="Price"
                    type="number"
                    step="0.01"
                    min="0"
                    wire:model="price"
                    :disabled="$is_free"
                />

                <x-input
                    name="currency"
                    label="Currency"
                    maxlength="3"
                    wire:model="currency"
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

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                <x-input
                    name="enrollment_limit"
                    label="Enrollment Limit (Optional)"
                    type="number"
                    min="1"
                    wire:model="enrollment_limit"
                    placeholder="Unlimited"
                />

                <div class="flex items-center gap-2 pt-5">
                    <input
                        id="course-is-sequential"
                        type="checkbox"
                        wire:model="is_sequential"
                        class="rounded border-border-default text-primary-600 focus:ring-primary-500"
                    />
                    <label for="course-is-sequential" class="text-sm font-medium text-text-primary">Sequential Module Progression</label>
                </div>
            </div>

            {{-- Thumbnail Upload --}}
            <div>
                <label class="form-label" for="course-thumbnail">Thumbnail Image</label>
                <div class="flex items-center gap-4">
                    @if ($thumbnail)
                        <img src="{{ $thumbnail->temporaryUrl() }}" alt="Preview" class="h-16 w-24 rounded object-cover border border-border-default" />
                    @elseif ($existingThumbnailUrl)
                        <img src="{{ $existingThumbnailUrl }}" alt="Current thumbnail" class="h-16 w-24 rounded object-cover border border-border-default" />
                    @endif
                    <input
                        id="course-thumbnail"
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

            {{-- Trainers & Prerequisites --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label" for="course-trainers">Assigned Trainers</label>
                    <select
                        id="course-trainers"
                        multiple
                        wire:model="trainer_ids"
                        class="form-input min-h-[100px]"
                    >
                        @foreach ($availableTrainers as $trainer)
                            <option value="{{ $trainer->id }}">{{ $trainer->name }} ({{ $trainer->email }})</option>
                        @endforeach
                    </select>
                    @error('trainer_ids')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="form-label" for="course-prerequisites">Prerequisites</label>
                    <select
                        id="course-prerequisites"
                        multiple
                        wire:model="prerequisite_ids"
                        class="form-input min-h-[100px]"
                    >
                        @foreach ($availablePrerequisites as $prereq)
                            <option value="{{ $prereq->id }}">{{ $prereq->title }}</option>
                        @endforeach
                    </select>
                    @error('prerequisite_ids')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Description --}}
            <div>
                <label for="course-description" class="form-label">Description</label>
                <textarea
                    id="course-description"
                    rows="4"
                    wire:model="description"
                    class="form-input @error('description') error @enderror"
                    placeholder="Course overview, learning outcomes, and syllabus summary..."
                ></textarea>
                @error('description')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-3 border-t border-border-default flex justify-end gap-2">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'course-form-modal')">
                    Cancel
                </x-button>
                <x-button variant="primary" type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveCourse">
                        {{ $editingCourseId ? 'Save Changes' : 'Create Course' }}
                    </span>
                    <span wire:loading wire:target="saveCourse">Saving...</span>
                </x-button>
            </div>
        </form>
    </x-modal>

    {{-- Course Categories Modal --}}
    <x-modal name="course-categories-modal" title="Manage Course Categories" maxWidth="xl">
        <div class="space-y-5">
            <form wire:submit="saveCategory" class="p-4 rounded-lg bg-surface-bg border border-border-default space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <x-input
                        name="categoryName"
                        label="Category Name"
                        wire:model="categoryName"
                        required
                    />
                    <x-input
                        name="categorySlug"
                        label="Slug (Optional)"
                        wire:model="categorySlug"
                        placeholder="auto-generated"
                    />
                    <x-input
                        name="categoryOrder"
                        label="Display Order"
                        type="number"
                        min="0"
                        wire:model="categoryOrder"
                    />
                </div>

                <x-input
                    name="categoryDescription"
                    label="Description (Optional)"
                    wire:model="categoryDescription"
                />

                <div class="flex items-center justify-between pt-1">
                    <label class="inline-flex items-center gap-2 text-sm text-text-primary">
                        <input
                            type="checkbox"
                            wire:model="categoryActive"
                            class="rounded border-border-default text-primary-600 focus:ring-primary-500"
                        />
                        Active
                    </label>

                    <div class="flex items-center gap-2">
                        @if ($editingCategoryId)
                            <x-button variant="secondary" size="sm" type="button" wire:click="resetCategoryForm">
                                Cancel Edit
                            </x-button>
                        @endif
                        <x-button variant="primary" size="sm" type="submit" wire:loading.attr="disabled">
                            {{ $editingCategoryId ? 'Update Category' : 'Add Category' }}
                        </x-button>
                    </div>
                </div>
            </form>

            @if ($categories->isEmpty())
                <p class="text-sm text-text-muted text-center py-4">No categories created yet.</p>
            @else
                <div class="divide-y divide-border-default border border-border-default rounded-lg overflow-hidden">
                    @foreach ($categories as $category)
                        <div wire:key="cat-{{ $category->id }}" class="px-4 py-3 flex items-center justify-between gap-3 bg-surface-card">
                            <div>
                                <div class="font-medium text-text-primary flex items-center gap-2">
                                    <span>{{ $category->name }}</span>
                                    <x-badge :color="$category->is_active ? 'green' : 'gray'">
                                        {{ $category->is_active ? 'Active' : 'Inactive' }}
                                    </x-badge>
                                </div>
                                <div class="text-xs text-text-muted">
                                    /{{ $category->slug }} &bull; {{ $category->courses_count }} {{ Str::plural('course', $category->courses_count) }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @can('update', $category)
                                    <x-button variant="secondary" size="sm" wire:click="editCategory('{{ $category->id }}')">
                                        Edit
                                    </x-button>
                                @endcan
                                @can('delete', $category)
                                    <x-button variant="danger" size="sm" wire:click="deleteCategory('{{ $category->id }}')">
                                        Delete
                                    </x-button>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-modal>

    {{-- Safe Delete Course Modal --}}
    <x-modal name="course-delete-modal" title="Delete Course" maxWidth="md">
        <div class="space-y-4">
            <div class="p-4 rounded-xl bg-danger-50 dark:bg-danger-950/30 border border-danger-200 dark:border-danger-800 text-danger-700 dark:text-danger-300 text-xs space-y-2">
                <p class="font-bold">Are you sure you want to permanently delete this course?</p>
                <p class="font-mono text-sm text-text-primary">{{ $deletingCourseTitle }}</p>
                @if ($deletingCourseEnrollmentsCount > 0)
                    <p class="font-semibold text-rose-600 dark:text-rose-400">
                        Warning: This course currently has {{ $deletingCourseEnrollmentsCount }} active or completed student enrollment(s). Deletion is blocked to protect student history. Please archive the course instead.
                    </p>
                @else
                    <p class="text-text-secondary">
                        This action will delete the course, its curriculum structure, lessons, and assignments. This action cannot be undone.
                    </p>
                @endif
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-border-default">
                <x-button variant="secondary" size="sm" type="button" @click="open = false">
                    Cancel
                </x-button>
                <x-button
                    variant="danger"
                    size="sm"
                    type="button"
                    wire:click="deleteCourse"
                    :disabled="$deletingCourseEnrollmentsCount > 0"
                    wire:loading.attr="disabled"
                >
                    Confirm Delete
                </x-button>
            </div>
        </div>
    </x-modal>
</div>
