<?php

namespace App\Http\Controllers;

use App\Domain\Projects\Models\ProjectSubmission;
use App\Domain\Projects\Models\ProjectSubmissionAttachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectAttachmentController extends Controller
{
    /**
     * Download a private practical project attachment with strict ownership/evaluator authorization.
     */
    public function download(Request $request, ProjectSubmission $submission, ProjectSubmissionAttachment $attachment): StreamedResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->is_active) {
            abort(403, 'Unauthorized.');
        }

        // 1. Verify attachment belongs to this submission
        if ((string) $attachment->submission_id !== (string) $submission->id) {
            abort(404, 'Attachment not found for this project submission.');
        }

        // 2. Authorize user access
        $isAuthorized = false;

        if ($user->hasRole('admin')) {
            $isAuthorized = true;
        } elseif ($user->hasRole('trainer')) {
            $course = $submission->project?->course;
            $isAuthorized = $course && $course->trainers()->where('users.id', $user->getKey())->exists();
        } elseif ($user->hasRole('student')) {
            $isAuthorized = (string) $submission->user_id === (string) $user->getKey();
        }

        if (! $isAuthorized) {
            abort(403, 'You are not authorized to download this project attachment.');
        }

        // 3. Resolve file from private disk
        $disk = $attachment->storage_disk ?: 'private';
        if (! Storage::disk($disk)->exists($attachment->storage_path)) {
            abort(404, 'Attachment file not found on private storage.');
        }

        $fileName = str_replace(['"', "\r", "\n"], '', $attachment->file_name ?: 'attachment');
        $mimeType = $attachment->mime_type ?: 'application/octet-stream';
        $fileSize = Storage::disk($disk)->size($attachment->storage_path);

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Content-Length' => (string) $fileSize,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-transform, no-store, must-revalidate',
        ];

        return response()->stream(function () use ($disk, $attachment) {
            $stream = Storage::disk($disk)->readStream($attachment->storage_path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, $headers);
    }
}
