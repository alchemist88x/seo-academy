{{--
    Alert Banner (Dismissible)
    @param string $type 'success' | 'error' | 'warning' | 'info'
    @param string|null $title Optional bold title
    @param bool $dismissible Allow user to close alert
--}}
@props([
    'type' => 'info',
    'title' => null,
    'dismissible' => true,
])

@php
    $styles = match ($type) {
        'success' => 'bg-emerald-50 border-emerald-200 text-emerald-900 dark:bg-emerald-950/40 dark:border-emerald-800/60 dark:text-emerald-200',
        'error' => 'bg-red-50 border-red-200 text-red-900 dark:bg-red-950/40 dark:border-red-800/60 dark:text-red-200',
        'warning' => 'bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-950/40 dark:border-amber-800/60 dark:text-amber-200',
        default => 'bg-blue-50 border-blue-200 text-blue-900 dark:bg-blue-950/40 dark:border-blue-800/60 dark:text-blue-200',
    };

    $iconColor = match ($type) {
        'success' => 'text-emerald-600 dark:text-emerald-400',
        'error' => 'text-red-600 dark:text-red-400',
        'warning' => 'text-amber-600 dark:text-amber-400',
        default => 'text-blue-600 dark:text-blue-400',
    };
@endphp

<div
    x-data="{ visible: true }"
    x-show="visible"
    x-transition
    role="alert"
    {{ $attributes->merge(['class' => "rounded-lg border p-4 flex items-start gap-3 {$styles}"]) }}
>
    <div class="shrink-0 mt-0.5 {{ $iconColor }}">
        @if ($type === 'success')
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        @elseif ($type === 'error')
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        @elseif ($type === 'warning')
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        @else
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        @endif
    </div>

    <div class="flex-1 text-sm">
        @if ($title)
            <p class="font-semibold mb-0.5">{{ $title }}</p>
        @endif
        <div>{{ $slot }}</div>
    </div>

    @if ($dismissible)
        <button
            type="button"
            @click="visible = false"
            class="shrink-0 opacity-70 hover:opacity-100 transition-opacity"
            aria-label="Dismiss alert"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    @endif
</div>
