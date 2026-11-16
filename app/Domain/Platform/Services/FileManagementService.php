<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FileManagementService
{
    /**
     * Strictly blocked extensions that could allow server-side code execution.
     *
     * @var array<int, string>
     */
    public const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8',
        'sh', 'bash', 'zsh', 'exe', 'bat', 'cmd', 'com', 'cgi', 'pl',
        'py', 'rb', 'jar', 'vbs', 'scr', 'msi', 'jsp', 'asp', 'aspx',
    ];

    /**
     * Strictly blocked MIME types.
     *
     * @var array<int, string>
     */
    public const BLOCKED_MIMES = [
        'text/x-php',
        'application/x-php',
        'application/x-httpd-php',
        'application/x-sh',
        'application/x-executable',
        'application/x-msdownload',
        'application/x-msdos-program',
    ];

    /**
     * Default maximum permitted file size in bytes (100 MB).
     */
    public const MAX_FILE_SIZE_BYTES = 104857600;

    /**
     * Validate and securely store an uploaded file, creating a Media record.
     */
    public function validateAndStore(
        UploadedFile $file,
        Model $model,
        string $collection = 'default',
        string $disk = 'private_media',
        ?User $uploader = null,
    ): Media {
        // 1. Validate file error status
        if (! $file->isValid()) {
            throw new InvalidArgumentException('Uploaded file is corrupted or incomplete.');
        }

        // 2. Validate maximum file size
        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            throw new InvalidArgumentException('File exceeds maximum upload size of 100MB.');
        }

        // 3. Validate file extension
        $rawExt = strtolower($file->getClientOriginalExtension());
        if (empty($rawExt) || in_array($rawExt, self::BLOCKED_EXTENSIONS, true)) {
            throw new InvalidArgumentException("File extension [{$rawExt}] is not permitted for upload.");
        }

        // 4. Validate file MIME type
        $clientMime = strtolower($file->getClientMimeType());
        $actualMime = strtolower(mime_content_type($file->getPathname()) ?: 'application/octet-stream');

        if (in_array($actualMime, self::BLOCKED_MIMES, true) || in_array($clientMime, self::BLOCKED_MIMES, true)) {
            throw new InvalidArgumentException('File MIME type indicates executable code and is strictly prohibited.');
        }

        // 5. Generate secure, non-guessable storage filename
        $safeStorageName = (string) Str::uuid().'.'.$rawExt;
        $targetDirectory = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $collection);
        $storagePath = $targetDirectory.'/'.$safeStorageName;

        // 6. Put file onto configured disk
        $stream = fopen($file->getRealPath(), 'r');
        $stored = Storage::disk($disk)->put($storagePath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        if (! $stored) {
            throw new InvalidArgumentException('Failed to persist file to disk.');
        }

        // 7. Create Media record with metadata
        return Media::create([
            'model_type' => get_class($model),
            'model_id' => (string) $model->getKey(),
            'collection' => $collection,
            'disk' => $disk,
            'path' => $storagePath,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $actualMime,
            'size_bytes' => $file->getSize(),
            'metadata' => [
                'uploader_id' => $uploader?->getKey(),
                'uploader_name' => $uploader?->name,
                'client_mime' => $clientMime,
                'extension' => $rawExt,
                'stored_filename' => $safeStorageName,
            ],
            'created_at' => now(),
        ]);
    }

    /**
     * Safely delete a media record and its underlying physical file.
     */
    public function deleteMedia(Media $media): bool
    {
        if (Storage::disk($media->disk)->exists($media->path)) {
            Storage::disk($media->disk)->delete($media->path);
        }

        return (bool) $media->delete();
    }

    /**
     * Calculate comprehensive storage metrics.
     *
     * @return array<string, mixed>
     */
    public function getStorageUsage(): array
    {
        $totalBytes = (int) Media::query()->sum('size_bytes');
        $totalFiles = Media::query()->count();

        $byCollection = Media::query()
            ->selectRaw('collection, count(*) as file_count, sum(size_bytes) as total_bytes')
            ->groupBy('collection')
            ->get()
            ->map(fn ($row) => [
                'collection' => $row->collection,
                'file_count' => (int) $row->file_count,
                'total_bytes' => (int) $row->total_bytes,
                'formatted_size' => $this->formatBytes((int) $row->total_bytes),
            ]);

        $byDisk = Media::query()
            ->selectRaw('disk, count(*) as file_count, sum(size_bytes) as total_bytes')
            ->groupBy('disk')
            ->get()
            ->map(fn ($row) => [
                'disk' => $row->disk,
                'file_count' => (int) $row->file_count,
                'total_bytes' => (int) $row->total_bytes,
                'formatted_size' => $this->formatBytes((int) $row->total_bytes),
            ]);

        $freeSpace = null;
        $totalDiskSpace = null;
        $storageRoot = storage_path('app/private');

        if (function_exists('disk_free_space') && @file_exists($storageRoot)) {
            $freeSpace = @disk_free_space($storageRoot);
            $totalDiskSpace = @disk_total_space($storageRoot);
        }

        return [
            'total_managed_bytes' => $totalBytes,
            'total_managed_formatted' => $this->formatBytes($totalBytes),
            'total_files' => $totalFiles,
            'by_collection' => $byCollection,
            'by_disk' => $byDisk,
            'disk_free_bytes' => $freeSpace !== false ? $freeSpace : null,
            'disk_free_formatted' => $freeSpace !== false && $freeSpace !== null ? $this->formatBytes($freeSpace) : 'N/A',
            'disk_total_bytes' => $totalDiskSpace !== false ? $totalDiskSpace : null,
            'disk_total_formatted' => $totalDiskSpace !== false && $totalDiskSpace !== null ? $this->formatBytes($totalDiskSpace) : 'N/A',
        ];
    }

    /**
     * Format bytes into human-readable metric string.
     */
    public function formatBytes(int|float $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / pow(1024, $power);

        return round($value, $precision).' '.$units[$power];
    }
}
