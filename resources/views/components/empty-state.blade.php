{{--
    Empty State Placeholder
    @param string $title Heading text
    @param string $message Descriptive message
    @param string|null $actionLabel Optional CTA button label
    @param string|null $actionUrl Optional CTA button URL
--}}
@props([
    'title' => 'No records found',
    'message' => 'There are no items to display right now.',
    'actionLabel' => null,
    'actionUrl' => null,
])

<div {{ $attributes->merge(['class' => 'py-12 px-6 text-center flex flex-col items-center justify-center']) }}>
    <div class="h-14 w-14 rounded-2xl bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 flex items-center justify-center mb-4">
        @isset($icon)
            {{ $icon }}
        @else
            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
            </svg>
        @endisset
    </div>

    <h3 class="text-sm font-semibold text-text-primary">{{ $title }}</h3>
    <p class="mt-1 text-sm text-text-muted max-w-sm">{{ $message }}</p>

    @if ($actionLabel && $actionUrl)
        <a href="{{ $actionUrl }}" class="mt-4 btn btn-primary btn-sm">
            {{ $actionLabel }}
        </a>
    @elseif (isset($action))
        <div class="mt-4">
            {{ $action }}
        </div>
    @endif
</div>
