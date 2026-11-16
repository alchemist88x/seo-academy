{{--
    Form Section
    - Two-column layout: left title/description, right form fields card
    @param string $title Section title
    @param string|null $description Section explanation
    @slot actions Optional footer action bar inside the form card
--}}
@props([
    'title' => '',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'md:grid md:grid-cols-3 md:gap-6']) }}>
    <div class="md:col-span-1">
        <div class="px-1">
            <h3 class="text-base font-semibold text-text-primary">{{ $title }}</h3>
            @if ($description)
                <p class="mt-1 text-sm text-text-secondary">{{ $description }}</p>
            @endif
        </div>
    </div>

    <div class="mt-4 md:mt-0 md:col-span-2">
        <div class="card overflow-hidden">
            <div class="p-5 sm:p-6 space-y-5">
                {{ $slot }}
            </div>

            @isset($actions)
                <div class="px-5 py-3.5 bg-border-muted/60 border-t border-border-default flex items-center justify-end gap-3">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    </div>
</div>
