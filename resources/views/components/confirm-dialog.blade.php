{{--
    Confirmation Modal Dialog
    - Built on top of <x-modal>
    @param string $name Unique modal identifier
    @param string $title Confirmation heading
    @param string $message Descriptive warning/prompt
    @param string $confirmLabel Confirm button text
    @param string $cancelLabel Cancel button text
    @param string $confirmVariant 'danger' | 'primary'
--}}
@props([
    'name' => 'confirm-dialog',
    'title' => 'Confirm Action',
    'message' => 'Are you sure you want to proceed? This action cannot be undone.',
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
    'confirmVariant' => 'danger',
])

<x-modal :name="$name" :title="$title" maxWidth="sm">
    <p class="text-sm text-text-secondary">{{ $message }}</p>

    <x-slot:footer>
        <x-button
            variant="secondary"
            size="sm"
            @click="$dispatch('close-modal', '{{ $name }}')"
        >
            {{ $cancelLabel }}
        </x-button>

        @isset($confirm)
            {{ $confirm }}
        @else
            <x-button
                :variant="$confirmVariant"
                size="sm"
                @click="$dispatch('confirmed-{{ $name }}'); $dispatch('close-modal', '{{ $name }}')"
            >
                {{ $confirmLabel }}
            </x-button>
        @endisset
    </x-slot:footer>
</x-modal>
