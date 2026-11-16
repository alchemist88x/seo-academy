{{--
    Fixed-Position Toast Container (Alpine.js)
    - Reads items from $store.toasts.items
    - Supports success, error, warning, info
--}}
@props([])

<div
    x-data
    class="fixed bottom-4 right-4 z-50 flex flex-col gap-2.5 pointer-events-none"
    aria-live="polite"
>
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-2"
            class="toast pointer-events-auto"
        >
            {{-- Status Indicator Dot --}}
            <div
                class="w-2.5 h-2.5 rounded-full mt-1.5 shrink-0"
                :class="{
                    'bg-emerald-500': toast.type === 'success',
                    'bg-red-500': toast.type === 'error',
                    'bg-amber-500': toast.type === 'warning',
                    'bg-blue-500': toast.type === 'info' || !toast.type
                }"
            ></div>

            <div class="flex-1 min-w-0">
                <p x-show="toast.title" x-text="toast.title" class="text-sm font-semibold text-text-primary"></p>
                <p x-show="toast.message" x-text="toast.message" class="text-xs text-text-secondary mt-0.5"></p>
            </div>

            <button
                type="button"
                @click="$store.toasts.remove(toast.id)"
                class="text-text-muted hover:text-text-primary shrink-0"
                aria-label="Close notification"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </template>
</div>
