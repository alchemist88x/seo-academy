{{--
    Form Select Dropdown
    @param string $name Field name
    @param string|null $label Label text
    @param array $options Associative array [value => label] or list of values
    @param string|null $placeholder Optional empty first option
    @param string|null $selected Currently selected value
    @param string|null $error Explicit error message
    @param bool $required Whether field is required
--}}
@props([
    'name' => '',
    'label' => null,
    'options' => [],
    'placeholder' => null,
    'selected' => null,
    'error' => null,
    'required' => false,
    'id' => null,
])

@php
    $selectId = $id ?? ($name ?: uniqid('select_'));
    $resolvedError = $error ?? ($name && isset($errors) ? $errors->first($name) : null);
    $currentValue = old($name, $selected);
@endphp

<div class="w-full">
    @if ($label)
        <label for="{{ $selectId }}" class="form-label">
            {{ $label }}
            @if ($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <select
        id="{{ $selectId }}"
        name="{{ $name }}"
        @if($required) required @endif
        {{ $attributes->merge([
            'class' => 'form-input form-select ' . ($resolvedError ? 'error' : ''),
        ]) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $key => $optionLabel)
            @php
                $optValue = is_int($key) && !array_is_list($options) ? $key : (is_int($key) ? $optionLabel : $key);
            @endphp
            <option value="{{ $optValue }}" @selected((string) $currentValue === (string) $optValue)>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>

    @if ($resolvedError)
        <p class="form-error">{{ $resolvedError }}</p>
    @endif
</div>
