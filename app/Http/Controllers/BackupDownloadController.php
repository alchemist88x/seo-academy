<?php

namespace App\Http\Controllers;

use App\Domain\Platform\Models\BackupRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupDownloadController extends Controller
{
    public function download(Request $request, BackupRecord $backup): BinaryFileResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('admin')) {
            abort(403, 'Unauthorized. Administrator access required.');
        }

        $safeName = basename($backup->filename);
        if ($safeName !== $backup->filename || str_contains($backup->filename, '..')) {
            abort(400, 'Invalid backup filename.');
        }

        $fullPath = storage_path("app/private/backups/{$backup->filename}");
        if (! File::exists($fullPath)) {
            abort(404, 'Backup archive does not exist on disk.');
        }

        return response()->download($fullPath, $backup->filename, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }
}
