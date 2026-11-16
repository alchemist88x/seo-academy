<?php

namespace App\Livewire\Ui;

use Livewire\Attributes\On;
use Livewire\Component;

class Toast extends Component
{
    /** @var array<int, array{id: string, title: string, message: string, type: string}> */
    public array $queue = [];

    #[On('notify')]
    public function push(string $message, string $title = '', string $type = 'info'): void
    {
        $id = uniqid('toast_', true);

        $this->queue[] = [
            'id' => $id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
        ];

        $this->dispatch('toast', [
            'title' => $title,
            'message' => $message,
            'type' => $type,
        ]);
    }

    public function dismiss(string $id): void
    {
        $this->queue = array_values(array_filter(
            $this->queue,
            fn (array $item): bool => $item['id'] !== $id
        ));
    }

    public function render()
    {
        return view('livewire.ui.toast');
    }
}
