<?php

namespace App\Http\Controllers;

use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Assignments\Models\AssignmentSubmissionAttachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentAttachmentController extends Controller
{
    /**
     * Download a private assignment attachment with strict role and ownership authorization.
     */
    public function download(Request $request, AssignmentSubmission $submission, AssignmentSubmissionAttachment $attachment): StreamedResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->is_active) {
            abort(403, 'Unauthorized.');
        }

        // 1. Verify attachment belongs to this submission
        if ((string) $attachment->submission_id !== (string) $submission->id) {
            abort(404, 'Attachment not found for this submission.');
        }

        // 2. Authorize user access
        $isAuthorized = false;

        if ($user->hasRole('admin')) {
            $isAuthorized = true;
        } elseif ($user->hasRole('trainer')) {
            $course = $submission->assignment?->course;
            $isAuthorized = $course && $course->trainers()->where('users.id', $user->getKey())->exists();
        } elseif ($user->hasRole('student')) {
            $isAuthorized = (string) $submission->user_id === (string) $user->getKey();
        }

        if (! $isAuthorized) {
            abort(403, 'You are not authorized to download this assignment attachment.');
        }

        // 3. Resolve file from private disk
        $disk = $attachment->storage_disk ?: 'private';
        if (! Storage::disk($disk)->exists($attachment->storage_path)) {
            abort(404, 'File not found on private storage.');
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
