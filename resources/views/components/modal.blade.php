{{--
    Alpine.js Modal Dialog
    - Open via: $dispatch('open-modal', 'modal-name')
    - Close via: $dispatch('close-modal', 'modal-name') or Escape key or backdrop click
    @param string $name Unique modal identifier
    @param string|null $title Optional modal header title
    @param string|null $description Optional modal header description/subtitle
    @param string $maxWidth 'sm' | 'md' | 'lg' | 'xl' | '2xl'
--}}
@props([
    'name' => 'default-modal',
    'title' => null,
    'description' => null,
    'maxWidth' => 'md',
])

@php
    $widthClass = match ($maxWidth) {
        'sm' => 'max-w-sm',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-2xl',
        '2xl' => 'max-w-4xl',
        default => 'max-w-md',
    };
@endphp

<div
    x-data="{
        open: false,
        init() {
            this.$watch('open', value => {
                if (value) {
                    document.body.classList.add('overflow-hidden');
                    this.$nextTick(() => {
                        if (this.$refs.modalBody) {
                            this.$refs.modalBody.scrollTop = 0;
                        }
                    });
                } else {
                    document.body.classList.remove('overflow-hidden');
                }
            });
        }
    }"
    x-on:open-modal.window="let d = Array.isArray($event.detail) ? $event.detail[0] : (typeof $event.detail === 'object' && $event.detail !== null ? ($event.detail.name || $event.detail[0] || $event.detail) : $event.detail); if (d === '{{ $name }}') open = true"
    x-on:close-modal.window="let d = Array.isArray($event.detail) ? $event.detail[0] : (typeof $event.detail === 'object' && $event.detail !== null ? ($event.detail.name || $event.detail[0] || $event.detail) : $event.detail); if (d === '{{ $name }}') open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-transition.opacity
    class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4 bg-black/60 backdrop-blur-sm"
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
        class="modal-panel {{ $widthClass }} border border-border-default flex flex-col max-h-[calc(100dvh-1rem)] sm:max-h-[calc(100dvh-2rem)] overflow-hidden"
    >
        @if ($title || isset($header))
            <div class="shrink-0 px-5 py-4 border-b border-border-default bg-surface-card flex items-center justify-between gap-4 sticky top-0 z-10">
                @isset($header)
                    {{ $header }}
                @else
                    <div>
                        <h3 class="text-base font-semibold text-text-primary">{{ $title }}</h3>
                        @if ($description)
                            <p class="text-xs text-text-muted mt-0.5">{{ $description }}</p>
                        @endif
                    </div>
                @endisset

                <button
                    type="button"
                    @click="open = false"
                    class="p-1 rounded-md text-text-muted hover:text-text-primary hover:bg-surface-elevated transition-colors cursor-pointer"
                    aria-label="Close modal"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        <div x-ref="modalBody" class="modal-body flex-1 overflow-y-auto overscroll-contain min-h-0 p-5 text-sm text-text-secondary">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="shrink-0 px-5 py-3.5 bg-surface-card border-t border-border-default flex items-center justify-end gap-2.5 sticky bottom-0 z-10">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
