{{--
    Alpine.js Modal Dialog
    - Open via: $dispatch('open-modal', 'modal-name')
    - Close via: $dispatch('close-modal', 'modal-name') or Escape key or backdrop click
    @param string $name Unique modal identifier
    @param string|null $title Optional modal header title
    @param string $maxWidth 'sm' | 'md' | 'lg' | 'xl'
--}}
@props([
    'name' => 'default-modal',
    'title' => null,
    'maxWidth' => 'md',
])

@php
    $widthClass = match ($maxWidth) {
        'sm' => 'max-w-sm',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-2xl',
        default => 'max-w-md',
    };
@endphp

<div
    x-data="{ open: false }"
    x-on:open-modal.window="let d = Array.isArray($event.detail) ? $event.detail[0] : (typeof $event.detail === 'object' && $event.detail !== null ? ($event.detail.name || $event.detail[0] || $event.detail) : $event.detail); if (d === '{{ $name }}') open = true"
    x-on:close-modal.window="let d = Array.isArray($event.detail) ? $event.detail[0] : (typeof $event.detail === 'object' && $event.detail !== null ? ($event.detail.name || $event.detail[0] || $event.detail) : $event.detail); if (d === '{{ $name }}') open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-transition.opacity
    class="modal-backdrop"
    style="display: none;"
    role="dialog"
    aria-modal="true"
>
    <div
        @click.outside="open = false"
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="modal-panel {{ $widthClass }} border border-border-default"
    >
        @if ($title || isset($header))
            <div class="px-5 py-4 border-b border-border-default flex items-center justify-between gap-4">
                @isset($header)
                    {{ $header }}
                @else
                    <h3 class="text-base font-semibold text-text-primary">{{ $title }}</h3>
                @endisset

                <button
                    type="button"
                    @click="open = false"
                    class="p-1 rounded-md text-text-muted hover:text-text-primary hover:bg-border-muted transition-colors"
                    aria-label="Close modal"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        <div class="p-5 text-sm text-text-secondary">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="px-5 py-3.5 bg-border-muted/60 border-t border-border-default flex items-center justify-end gap-2.5">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
