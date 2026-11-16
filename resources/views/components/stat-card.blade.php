{{--
    KPI / Stat Card
    @param string $label Metric label
    @param string|int $value Primary metric value
    @param string|null $icon Optional SVG or slot icon
    @param string|null $trend Optional trend string (e.g. "+12.5%")
    @param string $trendDirection 'up' | 'down' | 'neutral'
--}}
@props([
    'label' => '',
    'value' => '0',
    'icon' => null,
    'trend' => null,
    'trendDirection' => 'up',
])

@php
    $trendColors = [
        'up' => 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40',
        'down' => 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/40',
        'neutral' => 'text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-800',
    ];
    $trendClass = $trendColors[$trendDirection] ?? $trendColors['neutral'];
@endphp

<div {{ $attributes->merge(['class' => 'stat-card flex items-start justify-between gap-4']) }}>
    <div class="min-w-0">
        <p class="text-xs font-medium uppercase tracking-wider text-text-muted truncate">
            {{ $label }}
        </p>
        <p class="mt-2 text-2xl sm:text-3xl font-bold text-text-primary tracking-tight">
            {{ $value }}
        </p>

        @if ($trend)
            <div class="mt-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $trendClass }}">
                @if ($trendDirection === 'up')
                    <span>&uarr;</span>
                @elseif ($trendDirection === 'down')
                    <span>&darr;</span>
                @endif
                <span>{{ $trend }}</span>
            </div>
        @endif
    </div>

    @if ($icon || isset($iconSlot))
        <div class="h-11 w-11 rounded-xl bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 flex items-center justify-center shrink-0">
            @isset($iconSlot)
                {{ $iconSlot }}
            @else
                {!! $icon !!}
            @endisset
        </div>
    @endif
</div>
