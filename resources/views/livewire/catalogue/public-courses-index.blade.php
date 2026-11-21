@php
    use App\Domain\Courses\Models\Course;
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    {{-- Promotional Banner for Courses --}}
    <x-marketing.banner-carousel placement="courses_promo" />

    <x-page-header
        title="Course Catalogue"
        :subtitle="$academy ? 'Explore public courses from ' . $academy->name . '.' : 'Explore published SEO Academy courses.'"
    />

    {{-- Filters --}}
    <x-card>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
            <x-input
                name="search"
                label="Search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search courses..."
            />

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

        @if ($search !== '' || $categoryId !== '' || $difficulty !== '' || $sort !== 'newest')
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

    {{-- Catalogue Grid --}}
    @if ($courses->isEmpty())
        <x-card>
            <x-empty-state
                title="No courses available"
                message="No published public courses match your search criteria."
            />
        </x-card>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($courses as $course)
                <x-card wire:key="public-course-{{ $course->id }}" class="flex flex-col justify-between">
                    <div class="space-y-3">
                        @if ($course->thumbnailUrl())
                            <img
                                src="{{ $course->thumbnailUrl() }}"
                                alt="{{ $course->title }}"
                                class="w-full h-44 object-cover rounded-lg border border-border-default"
                            />
                        @else
                            <div class="w-full h-44 rounded-lg bg-border-muted flex items-center justify-center text-sm font-semibold text-text-muted">
                                {{ $course->category?->name ?? 'SEO Course' }}
                            </div>
                        @endif

                        <div class="flex items-center justify-between gap-2">
                            <x-badge color="blue">{{ $course->difficulty }}</x-badge>
                            <span class="text-xs text-text-muted">{{ $course->category?->name ?? 'General' }}</span>
                        </div>

                        <h3 class="text-lg font-semibold text-text-primary">
                            <a href="{{ route('courses.public.show', $course->slug) }}" class="hover:text-primary-600 transition-colors">
                                {{ $course->title }}
                            </a>
                        </h3>

                        @if ($course->description)
                            <p class="text-sm text-text-secondary line-clamp-3">
                                {{ \Illuminate\Support\Str::limit($course->description, 140) }}
                            </p>
                        @endif
                    </div>

                    <div class="pt-4 mt-4 border-t border-border-default flex items-center justify-between">
                        <span class="text-base font-bold text-text-primary">
                            {{ $course->is_free ? 'Free' : format_currency((float) $course->price, $course->currency) }}
                        </span>

                        <a href="{{ route('courses.public.show', $course->slug) }}" class="btn btn-primary btn-sm">
                            View Course
                        </a>
                    </div>
                </x-card>
            @endforeach
        </div>

        @if ($courses->hasPages())
            <div class="pt-2">
                {{ $courses->links() }}
            </div>
        @endif
    @endif
</div>
