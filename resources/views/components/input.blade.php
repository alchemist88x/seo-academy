{{--
    Form Text / Email / Password Input
    @param string $name Field name
    @param string|null $label Label text
    @param string $type Input type (text, email, password, number, etc.)
    @param string|null $error Explicit error message (falls back to $errors->first($name))
    @param string|null $hint Optional helper text below input
    @param bool $required Whether field is required
--}}
@props([
    'name' => '',
    'label' => null,
    'type' => 'text',
    'error' => null,
    'hint' => null,
    'required' => false,
    'id' => null,
])

@php
    $inputId = $id ?? ($name ?: uniqid('input_'));
    $resolvedError = $error ?? ($name && isset($errors) ? $errors->first($name) : null);
@endphp

<div class="w-full">
    @if ($label)
        <label for="{{ $inputId }}" class="form-label">
            {{ $label }}
            @if ($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <input
        id="{{ $inputId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if($required) required @endif
        {{ $attributes->merge([
            'class' => 'form-input ' . ($resolvedError ? 'error' : ''),
        ]) }}
    />

    @if ($resolvedError)
        <p class="form-error">{{ $resolvedError }}</p>
    @elseif ($hint)
        <p class="mt-1 text-xs text-text-muted">{{ $hint }}</p>
    @endif
</div>
