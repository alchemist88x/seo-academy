{{--
    Table Cell
    @param string $align 'left' | 'center' | 'right'
--}}
@props([
    'align' => 'left',
])

@php
    $alignClass = match ($align) {
        'center' => 'text-center',
        'right' => 'text-right',
        default => 'text-left',
    };
@endphp

<td {{ $attributes->merge(['class' => $alignClass]) }}>
    {{ $slot }}
</td>
