{{--
    Status Badge
    @param string $color 'green' | 'yellow' | 'red' | 'blue' | 'gray'
--}}
@props([
    'color' => 'gray',
])

@php
    $colorClasses = match ($color) {
        'green' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
        'yellow' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300',
        'red' => 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300',
        'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300',
        default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    };
@endphp

<span {{ $attributes->merge(['class' => "badge {$colorClasses}"]) }}>
    {{ $slot }}
</span>
