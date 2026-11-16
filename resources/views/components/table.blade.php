{{--
    Responsive Data Table
    @slot header Table header <th> cells
    @slot body Table body <tr> rows (or empty state)
--}}
@props([
    'striped' => false,
])

<div {{ $attributes->merge(['class' => 'table-wrapper']) }}>
    <div class="overflow-x-auto">
        <table>
            @isset($header)
                <thead>
                    <tr>
                        {{ $header }}
                    </tr>
                </thead>
            @endisset

            <tbody class="{{ $striped ? 'divide-y divide-border-muted' : '' }}">
                {{ $body ?? $slot }}
            </tbody>
        </table>
    </div>
</div>
