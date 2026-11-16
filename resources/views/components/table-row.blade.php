{{--
    Table Row
--}}
@props([])

<tr {{ $attributes->merge(['class' => 'transition-colors']) }}>
    {{ $slot }}
</tr>
