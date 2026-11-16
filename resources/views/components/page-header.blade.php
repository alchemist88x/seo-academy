{{--
    Page Header
    @param string $title Main page heading
    @param string|null $subtitle Optional description below title
    @slot actions Right-aligned action buttons
--}}
@props([
    'title' => '',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4']) }}>
    <div>
        <h1 class="text-xl sm:text-2xl font-bold text-text-primary tracking-tight">
            {{ $title }}
        </h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-text-secondary">
                {{ $subtitle }}
            </p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            {{ $actions }}
        </div>
    @endisset
</div>
