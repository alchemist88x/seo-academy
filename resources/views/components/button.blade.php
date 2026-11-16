{{--
    Button Component
    @param string $variant 'primary' | 'secondary' | 'danger' | 'ghost'
    @param string $size 'sm' | 'md' | 'lg'
    @param string $type 'button' | 'submit' | 'reset'
    @param bool $loading Show inline loading spinner
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'loading' => false,
])

@php
    $variantClass = match ($variant) {
        'secondary' => 'btn-secondary',
        'danger' => 'btn-danger',
        'ghost' => 'btn-ghost',
        default => 'btn-primary',
    };

    $sizeClass = match ($size) {
        'sm' => 'btn-sm',
        'lg' => 'btn-lg',
        default => 'btn-md',
    };
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => "btn {$variantClass} {$sizeClass}"]) }}
>
    @if ($loading)
        <x-loading-spinner size="sm" />
    @endif
    {{ $slot }}
</button>
