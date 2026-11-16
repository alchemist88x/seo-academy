<?php

namespace App\Domain\Courses\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Lessons\Models\LessonMaterial;
use App\Domain\Platform\Concerns\RecordsAuditLog;
use App\Domain\Platform\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CourseStructureService
{
    use RecordsAuditLog;

    private const ALLOWED_RESOURCE_MIMES = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'application/pdf' => 'pdf',
    ];

    private const MAX_VIDEO_BYTES = 536870912; // 512 MB

    private const MAX_PDF_BYTES = 52428800; // 50 MB

    public function createModule(Course $course, array $data, User $actor): Module
    {
        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', Rule::in(['Draft', 'Active', 'Archived'])],
            'completion_rule' => ['nullable', 'string', Rule::in(Module::COMPLETION_RULES)],
            'required_lessons_count' => ['nullable', 'integer', 'min:1'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ])->validate();

        return DB::transaction(function () use ($course, $validated, $actor): Module {
            $nextOrder = ((int) $course->modules()->max('display_order')) + 1;

            $module = Module::create([
                'academy_id' => $course->academy_id,
                'course_id' => $course->getKey(),
                'title' => trim($validated['title']),
                'description' => $validated['description'] ?? null,
                'display_order' => $validated['display_order'] ?? $nextOrder,
                'status' => $validated['status'] ?? 'Active',
                'completion_rule' => $validated['completion_rule'] ?? 'all_lessons',
                'required_lessons_count' => ($validated['completion_rule'] ?? 'all_lessons') === 'required_count'
                    ? ($validated['required_lessons_count'] ?? 1)
                    : null,
            ]);

            $this->recordAudit(
                $course->academy,
                $actor,
                'module.created',
                Module::class,
                $module->getKey(),
                [
                    'course_id' => $course->getKey(),
                    'title' => $module->title,
                ],
            );

            return $module;
        });
    }

    public function updateModule(Module $module, array $data, User $actor): Module
    {
        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', Rule::in(['Draft', 'Active', 'Archived'])],
            'completion_rule' => ['nullable', 'string', Rule::in(Module::COMPLETION_RULES)],
            'required_lessons_count' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        return DB::transaction(function () use ($module, $validated, $actor): Module {
            $completionRule = $validated['completion_rule'] ?? $module->completion_rule ?? 'all_lessons';

            $module->update([
                'title' => trim($validated['title']),
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? $module->status,
                'completion_rule' => $completionRule,
                'required_lessons_count' => $completionRule === 'required_count'
                    ? ($validated['required_lessons_count'] ?? $module->required_lessons_count ?? 1)
                    : null,
            ]);

            $academy = $module->academy ?? $module->course->academy;
            $this->recordAudit(
                $academy,
                $actor,
                'module.updated',
                Module::class,
                $module->getKey(),
                [
                    'course_id' => $module->course_id,
                    'title' => $module->title,
                ],
            );

            return $module->fresh();
        });
    }

    public function deleteModule(Module $module, User $actor, bool $forceConfirm = false): void
    {
        if ($module->hasLearnerProgress() && ! $forceConfirm) {
            throw ValidationException::withMessages([
                'module' => 'This module has existing learner progress. Confirm deletion to proceed.',
            ]);
        }

        DB::transaction(function () use ($module, $actor, $forceConfirm): void {
            $academy = $module->academy ?? $module->course->academy;
            $moduleId = $module->getKey();
            $courseId = $module->course_id;
            $title = $module->title;

            $module->delete();

            $this->recordAudit(
                $academy,
                $actor,
                'module.deleted',
                Module::class,
                $moduleId,
                [
                    'course_id' => $courseId,
                    'title' => $title,
                    'force_confirm' => $forceConfirm,
                ],
            );
        });
    }

    public function reorderModules(Course $course, array $orderedIds, User $actor): void
    {
        $orderedIds = array_values(array_unique(array_filter(
            array_map(static fn ($id): string => (string) $id, $orderedIds)
        )));

        if ($orderedIds === []) {
            return;
        }

        $validCount = Module::query()
            ->where('course_id', $course->getKey())
            ->whereIn('id', $orderedIds)
            ->count();

        if ($validCount !== count($orderedIds)) {
            throw ValidationException::withMessages([
                'ordered_ids' => 'All modules must belong to this course.',
            ]);
        }

        DB::transaction(function () use ($course, $orderedIds, $actor): void {
            foreach ($orderedIds as $index => $moduleId) {
                Module::query()
                    ->where('course_id', $course->getKey())
                    ->where('id', $moduleId)
                    ->update(['display_order' => $index + 1]);
            }

            $this->recordAudit(
                $course->academy,
                $actor,
                'module.reordered',
                Course::class,
                $course->getKey(),
                ['ordered_ids' => $orderedIds],
            );
        });
    }

    public function createLesson(
        Module $module,
        array $data,
        User $actor,
        ?UploadedFile $resourceFile = null,
    ): Lesson {
        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::in(Lesson::TYPES)],
            'description' => ['nullable', 'string', 'max:10000'],
            'content' => ['nullable', 'string'],
            'resource_provider' => ['nullable', 'string', Rule::in(Lesson::PROVIDERS)],
            'resource_reference' => ['nullable', 'string', 'max:500'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', Rule::in(['Draft', 'Active', 'Archived'])],
            'visibility' => ['nullable', 'string', Rule::in(Lesson::VISIBILITIES)],
            'is_free_preview' => ['nullable', 'boolean'],
            'completion_threshold_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'prerequisite_ids' => ['nullable', 'array'],
            'prerequisite_ids.*' => ['uuid'],
        ])->validate();

        return DB::transaction(function () use ($module, $validated, $actor, $resourceFile): Lesson {
            $academy = $module->academy ?? $module->course->academy;
            $nextOrder = ((int) $module->lessons()->max('display_order')) + 1;
            $visibility = $validated['visibility'] ?? 'enrolled';

            $lesson = Lesson::create([
                'academy_id' => $academy->getKey(),
                'module_id' => $module->getKey(),
                'title' => trim($validated['title']),
                'type' => $validated['type'] ?? 'video',
                'description' => $validated['description'] ?? null,
                'content' => $validated['content'] ?? null,
                'resource_provider' => $validated['resource_provider'] ?? null,
                'resource_reference' => $validated['resource_reference'] ?? null,
                'display_order' => $validated['display_order'] ?? $nextOrder,
                'duration_seconds' => (int) ($validated['duration_seconds'] ?? 0),
                'status' => $validated['status'] ?? 'Active',
                'visibility' => $visibility,
                'is_free_preview' => (bool) ($validated['is_free_preview'] ?? ($visibility === 'preview')),
                'completion_threshold_percent' => (int) ($validated['completion_threshold_percent'] ?? 80),
            ]);

            if ($resourceFile !== null) {
                $media = $this->storePrivateResource($academy, $lesson, $resourceFile, 'lesson_resource', $actor);
                $lesson->update([
                    'media_id' => $media->getKey(),
                    'resource_provider' => 'private_media',
                ]);
            }

            if (array_key_exists('prerequisite_ids', $validated)) {
                $this->syncLessonPrerequisites($lesson, $validated['prerequisite_ids'] ?? [], $actor);
            }

            $this->recordAudit(
                $academy,
                $actor,
                'lesson.created',
                Lesson::class,
                $lesson->getKey(),
                [
                    'module_id' => $module->getKey(),
                    'course_id' => $module->course_id,
                    'title' => $lesson->title,
                    'type' => $lesson->type,
                ],
            );

            return $lesson->fresh();
        });
    }

    public function updateLesson(
        Lesson $lesson,
        array $data,
        User $actor,
        ?UploadedFile $resourceFile = null,
    ): Lesson {
        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::in(Lesson::TYPES)],
            'description' => ['nullable', 'string', 'max:10000'],
            'content' => ['nullable', 'string'],
            'resource_provider' => ['nullable', 'string', Rule::in(Lesson::PROVIDERS)],
            'resource_reference' => ['nullable', 'string', 'max:500'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', Rule::in(['Draft', 'Active', 'Archived'])],
            'visibility' => ['nullable', 'string', Rule::in(Lesson::VISIBILITIES)],
            'is_free_preview' => ['nullable', 'boolean'],
            'completion_threshold_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'prerequisite_ids' => ['nullable', 'array'],
            'prerequisite_ids.*' => ['uuid'],
        ])->validate();

        return DB::transaction(function () use ($lesson, $validated, $actor, $resourceFile): Lesson {
            $academy = $lesson->academy ?? $lesson->module->academy ?? $lesson->module->course->academy;
            $visibility = $validated['visibility'] ?? $lesson->visibility;

            $lesson->update([
                'title' => trim($validated['title']),
                'type' => $validated['type'] ?? $lesson->type,
                'description' => $validated['description'] ?? null,
                'content' => $validated['content'] ?? null,
                'resource_provider' => $validated['resource_provider'] ?? $lesson->resource_provider,
                'resource_reference' => $validated['resource_reference'] ?? $lesson->resource_reference,
                'duration_seconds' => (int) ($validated['duration_seconds'] ?? $lesson->duration_seconds),
                'status' => $validated['status'] ?? $lesson->status,
                'visibility' => $visibility,
                'is_free_preview' => array_key_exists('is_free_preview', $validated)
                    ? (bool) $validated['is_free_preview']
                    : ($visibility === 'preview' ? true : $lesson->is_free_preview),
                'completion_threshold_percent' => (int) ($validated['completion_threshold_percent'] ?? $lesson->completion_threshold_percent),
            ]);

            if ($resourceFile !== null) {
                $media = $this->storePrivateResource($academy, $lesson, $resourceFile, 'lesson_resource', $actor);
                $lesson->update([
                    'media_id' => $media->getKey(),
                    'resource_provider' => 'private_media',
                ]);
            }

            if (array_key_exists('prerequisite_ids', $validated)) {
                $this->syncLessonPrerequisites($lesson, $validated['prerequisite_ids'] ?? [], $actor);
            }

            $this->recordAudit(
                $academy,
                $actor,
                'lesson.updated',
                Lesson::class,
                $lesson->getKey(),
                [
                    'module_id' => $lesson->module_id,
                    'title' => $lesson->title,
                    'type' => $lesson->type,
                ],
            );

            return $lesson->fresh();
        });
    }

    public function deleteLesson(Lesson $lesson, User $actor): void
    {
        DB::transaction(function () use ($lesson, $actor): void {
            $academy = $lesson->academy ?? $lesson->module->academy ?? $lesson->module->course->academy;
            $lessonId = $lesson->getKey();
            $moduleId = $lesson->module_id;
            $title = $lesson->title;

            $lesson->delete();

            $this->recordAudit(
                $academy,
                $actor,
                'lesson.deleted',
                Lesson::class,
                $lessonId,
                [
                    'module_id' => $moduleId,
                    'title' => $title,
                ],
            );
        });
    }

    public function reorderLessons(Module $module, array $orderedIds, User $actor): void
    {
        $orderedIds = array_values(array_unique(array_filter(
            array_map(static fn ($id): string => (string) $id, $orderedIds)
        )));

        if ($orderedIds === []) {
            return;
        }

        $validCount = Lesson::query()
            ->where('module_id', $module->getKey())
            ->whereIn('id', $orderedIds)
            ->count();

        if ($validCount !== count($orderedIds)) {
            throw ValidationException::withMessages([
                'ordered_ids' => 'All lessons must belong to this module.',
            ]);
        }

        DB::transaction(function () use ($module, $orderedIds, $actor): void {
            foreach ($orderedIds as $index => $lessonId) {
                Lesson::query()
                    ->where('module_id', $module->getKey())
                    ->where('id', $lessonId)
                    ->update(['display_order' => $index + 1]);
            }

            $academy = $module->academy ?? $module->course->academy;
            $this->recordAudit(
                $academy,
                $actor,
                'lesson.reordered',
                Module::class,
                $module->getKey(),
                ['ordered_ids' => $orderedIds],
            );
        });
    }

    public function syncLessonPrerequisites(Lesson $lesson, array $prerequisiteIds, User $actor): void
    {
        $prerequisiteIds = array_values(array_unique(array_filter(
            array_map(static fn ($id): string => (string) $id, $prerequisiteIds)
        )));

        if (in_array($lesson->getKey(), $prerequisiteIds, true)) {
            throw ValidationException::withMessages([
                'prerequisite_ids' => 'A lesson cannot be a prerequisite of itself.',
            ]);
        }

        $course = $lesson->module->course;
        $academy = $lesson->academy ?? $course->academy;

        if ($prerequisiteIds !== []) {
            $validCount = Lesson::query()
                ->whereIn('id', $prerequisiteIds)
                ->whereHas('module', function ($query) use ($course): void {
                    $query->where('course_id', $course->getKey());
                })
                ->count();

            if ($validCount !== count($prerequisiteIds)) {
                throw ValidationException::withMessages([
                    'prerequisite_ids' => 'All lesson prerequisites must belong to the same course.',
                ]);
            }

            $this->assertNoCircularLessonPrerequisites($lesson, $course, $prerequisiteIds);
        }

        DB::transaction(function () use ($lesson, $academy, $prerequisiteIds, $actor): void {
            DB::table('lesson_prerequisites')
                ->where('lesson_id', $lesson->getKey())
                ->delete();

            $now = now();
            $rows = [];
            foreach ($prerequisiteIds as $prerequisiteId) {
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'academy_id' => $academy->getKey(),
                    'lesson_id' => $lesson->getKey(),
                    'prerequisite_lesson_id' => $prerequisiteId,
                    'created_at' => $now,
                ];
            }

            if ($rows !== []) {
                DB::table('lesson_prerequisites')->insert($rows);
            }

            $this->recordAudit(
                $academy,
                $actor,
                'lesson.prerequisites_synced',
                Lesson::class,
                $lesson->getKey(),
                ['prerequisite_ids' => $prerequisiteIds],
            );
        });
    }

    public function storePrivateResource(
        Academy $academy,
        Lesson $lesson,
        UploadedFile $file,
        string $collection,
        User $actor,
        ?string $modelType = null,
        ?string $modelId = null,
    ): Media {
        $mime = strtolower(trim((string) ($file->getMimeType() ?: $file->getClientMimeType())));
        if (! array_key_exists($mime, self::ALLOWED_RESOURCE_MIMES)) {
            throw ValidationException::withMessages([
                'file' => 'Unsupported file type. Allowed types: MP4, WebM, PDF.',
            ]);
        }

        $sizeBytes = (int) ($file->getSize() ?: 0);
        $maxBytes = $mime === 'application/pdf' ? self::MAX_PDF_BYTES : self::MAX_VIDEO_BYTES;
        if ($sizeBytes > $maxBytes) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file exceeds the maximum allowed size.',
            ]);
        }

        $extension = self::ALLOWED_RESOURCE_MIMES[$mime];
        $directory = "academies/{$academy->getKey()}/lessons/{$lesson->getKey()}";
        $storedFilename = bin2hex(random_bytes(16)).'.'.$extension;
        $storedPath = $file->storeAs($directory, $storedFilename, 'private_media');

        if ($storedPath === false) {
            throw ValidationException::withMessages([
                'file' => 'Failed to store the uploaded file.',
            ]);
        }

        $media = Media::create([
            'model_type' => $modelType ?? Lesson::class,
            'model_id' => $modelId ?? $lesson->getKey(),
            'collection' => $collection,
            'disk' => 'private_media',
            'path' => $storedPath,
            'filename' => $file->getClientOriginalName() ?: $storedFilename,
            'mime_type' => $mime,
            'size_bytes' => $sizeBytes,
            'metadata' => [
                'academy_id' => $academy->getKey(),
                'lesson_id' => $lesson->getKey(),
                'uploaded_by' => $actor->getKey(),
            ],
            'created_at' => now(),
        ]);

        $this->recordAudit(
            $academy,
            $actor,
            'lesson.resource_uploaded',
            Media::class,
            $media->getKey(),
            [
                'lesson_id' => $lesson->getKey(),
                'collection' => $collection,
                'mime_type' => $mime,
                'size_bytes' => $sizeBytes,
            ],
        );

        return $media;
    }

    public function attachLessonMaterial(
        Lesson $lesson,
        string $title,
        ?string $description,
        UploadedFile $file,
        User $actor,
    ): LessonMaterial {
        $validated = Validator::make([
            'title' => $title,
            'description' => $description,
        ], [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        return DB::transaction(function () use ($lesson, $validated, $file, $actor): LessonMaterial {
            $academy = $lesson->academy ?? $lesson->module->academy ?? $lesson->module->course->academy;
            $nextOrder = ((int) $lesson->materials()->max('display_order')) + 1;

            $material = LessonMaterial::create([
                'academy_id' => $academy->getKey(),
                'lesson_id' => $lesson->getKey(),
                'title' => trim($validated['title']),
                'description' => $validated['description'] ?? null,
                'file_path' => '',
                'provider' => 'private_media',
                'display_order' => $nextOrder,
                'created_at' => now(),
            ]);

            $media = $this->storePrivateResource(
                $academy,
                $lesson,
                $file,
                'lesson_material',
                $actor,
                LessonMaterial::class,
                $material->getKey(),
            );

            $material->update([
                'media_id' => $media->getKey(),
                'file_path' => $media->path,
                'file_size_bytes' => $media->size_bytes,
                'mime_type' => $media->mime_type,
                'provider' => 'private_media',
            ]);

            $this->recordAudit(
                $academy,
                $actor,
                'lesson_material.attached',
                LessonMaterial::class,
                $material->getKey(),
                [
                    'lesson_id' => $lesson->getKey(),
                    'media_id' => $media->getKey(),
                    'title' => $material->title,
                ],
            );

            return $material->fresh();
        });
    }

    public function deleteLessonMaterial(LessonMaterial $material, User $actor): void
    {
        DB::transaction(function () use ($material, $actor): void {
            $lesson = $material->lesson;
            $academy = $material->academy ?? $lesson->academy ?? $lesson->module->academy ?? $lesson->module->course->academy;
            $materialId = $material->getKey();
            $lessonId = $material->lesson_id;
            $media = $material->media;

            if ($media !== null) {
                if ($media->disk === 'private_media' && $media->path && Storage::disk('private_media')->exists($media->path)) {
                    Storage::disk('private_media')->delete($media->path);
                }
                $media->delete();
            }

            $material->delete();

            $this->recordAudit(
                $academy,
                $actor,
                'lesson_material.deleted',
                LessonMaterial::class,
                $materialId,
                ['lesson_id' => $lessonId],
            );
        });
    }

    private function assertNoCircularLessonPrerequisites(Lesson $lesson, Course $course, array $prerequisiteIds): void
    {
        $courseLessonIds = Lesson::query()
            ->whereHas('module', function ($query) use ($course): void {
                $query->where('course_id', $course->getKey());
            })
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        $edges = DB::table('lesson_prerequisites')
            ->whereIn('lesson_id', $courseLessonIds)
            ->get(['lesson_id', 'prerequisite_lesson_id'])
            ->groupBy('lesson_id')
            ->map(static fn ($items): array => $items->pluck('prerequisite_lesson_id')->all())
            ->all();

        $visited = [];
        $queue = $prerequisiteIds;

        while ($queue !== []) {
            $current = array_shift($queue);
            if ($current === $lesson->getKey()) {
                throw ValidationException::withMessages([
                    'prerequisite_ids' => 'Circular lesson prerequisite dependency detected.',
                ]);
            }

            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            foreach ($edges[$current] ?? [] as $next) {
                if (! isset($visited[$next])) {
                    $queue[] = $next;
                }
            }
        }
    }
}
