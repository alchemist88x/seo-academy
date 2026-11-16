<?php

namespace App\Livewire\Admin;

use App\Domain\Platform\Models\Media;
use App\Domain\Platform\Services\FileManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('File Management')]
class FileManagementIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $collection = 'all';

    #[Url]
    public string $disk = 'all';

    #[Url]
    public string $sort = 'latest'; // latest, oldest, size_desc, size_asc

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCollection(): void
    {
        $this->resetPage();
    }

    public function updatedDisk(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function deleteFile(string $mediaId, FileManagementService $fileService): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasRole('admin')) {
            abort(403, 'Unauthorized.');
        }

        $media = Media::find($mediaId);
        if ($media) {
            $fileService->deleteMedia($media);
            session()->flash('success', "File [{$media->filename}] has been securely removed.");
        }
    }

    public function render(FileManagementService $fileService): View
    {
        $query = Media::query();

        if (trim($this->search) !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('filename', 'like', $term)
                    ->orWhere('path', 'like', $term)
                    ->orWhere('mime_type', 'like', $term);
            });
        }

        if ($this->collection !== 'all') {
            $query->where('collection', $this->collection);
        }

        if ($this->disk !== 'all') {
            $query->where('disk', $this->disk);
        }

        match ($this->sort) {
            'oldest' => $query->orderBy('created_at', 'asc'),
            'size_desc' => $query->orderBy('size_bytes', 'desc'),
            'size_asc' => $query->orderBy('size_bytes', 'asc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $files = $query->paginate(15);
        $storageStats = $fileService->getStorageUsage();

        $availableCollections = Media::query()->distinct()->pluck('collection')->filter()->values();
        $availableDisks = Media::query()->distinct()->pluck('disk')->filter()->values();

        return view('livewire.admin.file-management-index', [
            'files' => $files,
            'storageStats' => $storageStats,
            'availableCollections' => $availableCollections,
            'availableDisks' => $availableDisks,
            'fileService' => $fileService,
        ]);
    }
}
