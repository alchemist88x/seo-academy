{{--
    Form Textarea
    @param string $name Field name
    @param string|null $label Label text
    @param int $rows Number of rows
    @param string|null $error Explicit error message
    @param bool $required Whether field is required
--}}
@props([
    'name' => '',
    'label' => null,
    'rows' => 4,
    'error' => null,
    'required' => false,
    'id' => null,
])

@php
    $textareaId = $id ?? ($name ?: uniqid('textarea_'));
    $resolvedError = $error ?? ($name && isset($errors) ? $errors->first($name) : null);
@endphp

<div class="w-full">
    @if ($label)
        <label for="{{ $textareaId }}" class="form-label">
            {{ $label }}
            @if ($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <textarea
        id="{{ $textareaId }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if($required) required @endif
        {{ $attributes->merge([
            'class' => 'form-input form-textarea ' . ($resolvedError ? 'error' : ''),
        ]) }}
    >{{ $slot }}</textarea>

    @if ($resolvedError)
        <p class="form-error">{{ $resolvedError }}</p>
    @endif
</div>
