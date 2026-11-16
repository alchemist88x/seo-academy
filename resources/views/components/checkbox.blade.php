{{--
    Form Checkbox
    @param string $name Field name
    @param string|null $label Label text
    @param string|null $description Optional helper text under label
    @param bool $checked Default checked state
    @param string|null $error Explicit error message
--}}
@props([
    'name' => '',
    'label' => null,
    'description' => null,
    'checked' => false,
    'error' => null,
    'id' => null,
])

@php
    $checkboxId = $id ?? ($name ?: uniqid('checkbox_'));
    $resolvedError = $error ?? ($name && isset($errors) ? $errors->first($name) : null);
@endphp

<div>
    <div class="flex items-start gap-2.5">
        <div class="flex items-center h-5">
            <input
                id="{{ $checkboxId }}"
                name="{{ $name }}"
                type="checkbox"
                @checked(old($name, $checked))
                {{ $attributes->merge([
                    'class' => 'h-4 w-4 rounded border-border-default text-primary-600 focus:ring-primary-500 bg-surface-card',
                ]) }}
            />
        </div>

        @if ($label || $description)
            <div class="text-sm leading-5">
                @if ($label)
                    <label for="{{ $checkboxId }}" class="font-medium text-text-primary cursor-pointer">
                        {{ $label }}
                    </label>
                @endif
                @if ($description)
                    <p class="text-xs text-text-muted mt-0.5">{{ $description }}</p>
                @endif
            </div>
        @endif
    </div>

    @if ($resolvedError)
        <p class="form-error">{{ $resolvedError }}</p>
    @endif
</div>
