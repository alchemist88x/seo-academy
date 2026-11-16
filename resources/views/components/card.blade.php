{{--
    Card Container
    @param string $title Optional card header title
    @param string $description Optional card header subtitle
    @param bool $padding Whether to pad the card body (default: true)
    @slot header Optional custom header actions
    @slot footer Optional card footer
--}}
@props([
    'title' => null,
    'description' => null,
    'padding' => true,
])

<div {{ $attributes->merge(['class' => 'card overflow-hidden']) }}>
    @if ($title || $description || isset($header))
        <div class="px-5 py-4 border-b border-border-default flex items-center justify-between gap-4">
            <div>
                @if ($title)
                    <h3 class="text-base font-semibold text-text-primary">{{ $title }}</h3>
                @endif
                @if ($description)
                    <p class="text-sm text-text-secondary mt-0.5">{{ $description }}</p>
                @endif
            </div>

            @isset($header)
                <div class="flex items-center gap-2 shrink-0">
                    {{ $header }}
                </div>
            @endisset
        </div>
    @endif

    <div class="{{ $padding ? 'p-5' : '' }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="px-5 py-3.5 bg-border-muted/60 border-t border-border-default flex items-center justify-end gap-3">
            {{ $footer }}
        </div>
    @endisset
</div>
