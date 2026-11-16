{{--
    Breadcrumbs Navigation
    @param array $items Array of ['label' => string, 'url' => string|null]
--}}
@props([
    'items' => [],
])

@if (count($items) > 0)
    <nav class="flex items-center text-sm text-text-muted overflow-x-auto whitespace-nowrap" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1.5">
            @foreach ($items as $index => $item)
                <li class="inline-flex items-center">
                    @if ($index > 0)
                        <svg class="w-4 h-4 mx-1 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    @endif

                    @if (!empty($item['url']) && !$loop->last)
                        <a
                            href="{{ $item['url'] }}"
                            class="hover:text-text-primary transition-colors font-medium"
                        >
                            {{ $item['label'] }}
                        </a>
                    @else
                        <span class="{{ $loop->last ? 'text-text-primary font-semibold' : '' }}">
                            {{ $item['label'] }}
                        </span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
