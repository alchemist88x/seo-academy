<?php

namespace App\Domain\Video\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Lessons\Models\LessonMaterial;
use App\Domain\LiveClasses\Models\ClassMaterial;
use App\Domain\LiveClasses\Models\ClassVideo;
use App\Domain\LiveClasses\Models\LiveClass;
use App\Domain\Platform\Models\Media;
use App\Domain\Quizzes\Models\Quiz;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateMediaService
{
    private const CHUNK_SIZE = 8192;

    /**
     * MIME types permitted for inline browser rendering.
     *
     * @var array<int, string>
     */
    private const SAFE_INLINE_MIMES = [
        'video/mp4',
        'video/webm',
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'text/plain',
    ];

    /**
     * Authorize the user and stream the requested private media file.
     */
    public function stream(Request $request, Media $media): StreamedResponse|Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $this->authorize($user, $media)) {
            abort(403, 'You are not authorized to access this media.');
        }

        $fullPath = $this->resolveFilePath($media);
        $fileSize = filesize($fullPath);

        if ($fileSize === false) {
            abort(404, 'Media file could not be read.');
        }

        $rawMime = strtolower(trim((string) ($media->mime_type ?: (mime_content_type($fullPath) ?: 'application/octet-stream'))));
        $baseMime = trim(explode(';', $rawMime)[0]);
        $isSafeInline = in_array($baseMime, self::SAFE_INLINE_MIMES, true);

        $contentType = $isSafeInline ? $baseMime : 'application/octet-stream';
        $disposition = $isSafeInline ? 'inline' : 'attachment';
        $safeFilename = str_replace(['"', "\r", "\n"], '', $media->filename ?: basename($fullPath));

        $baseHeaders = [
            'Content-Type' => $contentType,
            'Accept-Ranges' => 'bytes',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => "{$disposition}; filename=\"{$safeFilename}\"",
        ];

        $rangeHeader = $request->headers->get('Range');

        if ($rangeHeader !== null && $fileSize > 0) {
            return $this->streamByteRange($fullPath, $fileSize, $rangeHeader, $baseHeaders);
        }

        return response()->stream(function () use ($fullPath): void {
            $handle = fopen($fullPath, 'rb');

            if ($handle === false) {
                return;
            }

            try {
                while (! feof($handle)) {
                    echo fread($handle, self::CHUNK_SIZE);
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } finally {
                fclose($handle);
            }
        }, 200, array_merge($baseHeaders, [
            'Content-Length' => (string) $fileSize,
        ]));
    }

    /**
     * Determine whether the user is authorized to view the given media item.
     */
    public function authorize(User $user, Media $media): bool
    {
        if (! $user->is_active) {
            return false;
        }

        $model = $media->model;

        if ($model instanceof User) {
            return (string) $model->id === (string) $user->id;
        }

        [$academyId, $courseId] = $this->resolveOwnership($media, $model);

        if ($courseId !== null) {
            if ($academyId === null || ! $user->belongsToAcademy($academyId)) {
                return false;
            }

            if ($user->hasAnyRole(['admin', 'trainer'])) {
                return true;
            }

            if ($this->allowsFreePreview($model)) {
                return true;
            }

            return $user->studentProfile?->enrollments()
                ->allowsCourseAccess()
                ->where('course_id', $courseId)
                ->exists() ?? false;
        }

        if ($academyId !== null) {
            return $user->belongsToAcademy($academyId);
        }

        return false;
    }

    /**
     * Resolve the absolute filesystem path for a Media record on the private_media disk.
     */
    public function resolveFilePath(Media $media): string
    {
        $diskName = $media->disk ?: 'private_media';

        if ($diskName !== 'private_media') {
            abort(404, 'Media is not stored on the private_media disk.');
        }

        $relativePath = ltrim(str_replace('\\', '/', (string) $media->path), '/');

        if ($relativePath === '' || str_contains($relativePath, '..')) {
            abort(404, 'Invalid media path.');
        }

        $disk = Storage::disk('private_media');

        if (! $disk->exists($relativePath)) {
            abort(404, 'Media file not found.');
        }

        $rootRealPath = realpath($disk->path(''));
        $fileRealPath = realpath($disk->path($relativePath));

        if ($rootRealPath === false || $fileRealPath === false) {
            abort(404, 'Media file not found.');
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', $rootRealPath), '/').'/';
        $normalizedFile = str_replace('\\', '/', $fileRealPath);

        if (! str_starts_with($normalizedFile, $normalizedRoot)) {
            abort(404, 'Invalid media path.');
        }

        return $fileRealPath;
    }

    private function allowsFreePreview(mixed $model): bool
    {
        $lesson = null;

        if ($model instanceof Lesson) {
            $lesson = $model;
        } elseif ($model instanceof LessonMaterial) {
            $lesson = $model->lesson;
        }

        if ($lesson === null) {
            return false;
        }

        $course = $lesson->module?->course;

        if ($course === null) {
            return false;
        }

        return $course->isPublished()
            && in_array($course->visibility, ['public', 'academy_only'], true)
            && $lesson->isFreePreview();
    }

    /**
     * Resolve [academy_id, course_id] from the media's parent model or metadata.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveOwnership(Media $media, mixed $model): array
    {
        $academyId = null;
        $courseId = null;

        if ($model instanceof Academy) {
            $academyId = (string) $model->id;
        } elseif ($model instanceof Course) {
            $academyId = (string) $model->academy_id;
            $courseId = (string) $model->id;
        } elseif ($model instanceof Module) {
            $course = $model->course;
            $academyId = $course?->academy_id ? (string) $course->academy_id : null;
            $courseId = $model->course_id ? (string) $model->course_id : null;
        } elseif ($model instanceof Lesson || $model instanceof LiveClass || $model instanceof Quiz || $model instanceof Assignment) {
            $module = $model->module;
            $course = $module?->course;
            $academyId = $course?->academy_id ? (string) $course->academy_id : null;
            $courseId = $module?->course_id ? (string) $module->course_id : null;
        } elseif ($model instanceof LessonMaterial) {
            $module = $model->lesson?->module;
            $course = $module?->course;
            $academyId = $course?->academy_id ? (string) $course->academy_id : null;
            $courseId = $module?->course_id ? (string) $module->course_id : null;
        } elseif ($model instanceof ClassMaterial || $model instanceof ClassVideo) {
            $module = $model->liveClass?->module;
            $course = $module?->course;
            $academyId = $course?->academy_id ? (string) $course->academy_id : null;
            $courseId = $module?->course_id ? (string) $module->course_id : null;
        } elseif (is_object($model) && isset($model->academy_id)) {
            $academyId = (string) $model->academy_id;
        }

        if ($academyId === null && is_array($media->metadata) && ! empty($media->metadata['academy_id'])) {
            $academyId = (string) $media->metadata['academy_id'];
        }

        return [$academyId, $courseId];
    }

    /**
     * Stream an HTTP 206 Partial Content byte-range response.
     *
     * @param  array<string, string>  $baseHeaders
     */
    private function streamByteRange(
        string $fullPath,
        int $fileSize,
        string $rangeHeader,
        array $baseHeaders,
    ): StreamedResponse|Response {
        if (! preg_match('/^bytes=(\d*)-(\d*)$/i', trim($rangeHeader), $matches)) {
            return response('', 416, [
                'Content-Range' => "bytes */{$fileSize}",
            ]);
        }

        $startStr = $matches[1];
        $endStr = $matches[2];

        if ($startStr === '' && $endStr === '') {
            return response('', 416, [
                'Content-Range' => "bytes */{$fileSize}",
            ]);
        }

        if ($startStr === '') {
            $suffixLength = (int) $endStr;
            if ($suffixLength <= 0) {
                return response('', 416, [
                    'Content-Range' => "bytes */{$fileSize}",
                ]);
            }
            $start = max(0, $fileSize - $suffixLength);
            $end = $fileSize - 1;
        } else {
            $start = (int) $startStr;
            $end = $endStr !== '' ? min((int) $endStr, $fileSize - 1) : $fileSize - 1;
        }

        if ($start < 0 || $start >= $fileSize || $start > $end) {
            return response('', 416, [
                'Content-Range' => "bytes */{$fileSize}",
            ]);
        }

        $length = $end - $start + 1;

        return response()->stream(function () use ($fullPath, $start, $length): void {
            $handle = fopen($fullPath, 'rb');

            if ($handle === false) {
                return;
            }

            try {
                fseek($handle, $start);
                $remaining = $length;

                while ($remaining > 0 && ! feof($handle)) {
                    $readLength = min(self::CHUNK_SIZE, $remaining);
                    $buffer = fread($handle, $readLength);

                    if ($buffer === false || $buffer === '') {
                        break;
                    }

                    echo $buffer;
                    $remaining -= strlen($buffer);

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } finally {
                fclose($handle);
            }
        }, 206, array_merge($baseHeaders, [
            'Content-Length' => (string) $length,
            'Content-Range' => "bytes {$start}-{$end}/{$fileSize}",
        ]));
    }
}
