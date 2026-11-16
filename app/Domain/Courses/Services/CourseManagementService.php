<?php

namespace App\Domain\Courses\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\CourseCategory;
use App\Domain\Platform\Concerns\RecordsAuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CourseManagementService
{
    use RecordsAuditLog;

    public function listForAcademy(
        Academy $academy,
        array $filters = [],
        ?User $assignedTrainer = null,
        int $perPage = 12,
    ): LengthAwarePaginator {
        $query = Course::query()
            ->forAcademy($academy)
            ->with(['category', 'trainers', 'prerequisites']);

        if ($assignedTrainer !== null) {
            $query->assignedToTrainer($assignedTrainer);
        }

        $this->applyCatalogueFilters($query, $filters);

        return $query->paginate($perPage);
    }

    public function listPublicCatalogue(
        ?Academy $academy,
        array $filters = [],
        int $perPage = 12,
    ): LengthAwarePaginator {
        $query = Course::query()
            ->published()
            ->where('visibility', 'public')
            ->with(['category', 'trainers', 'academy']);

        if ($academy !== null) {
            $query->forAcademy($academy);
        }

        $this->applyCatalogueFilters($query, $filters);

        return $query->paginate($perPage);
    }

    public function listStudentCatalogue(
        Academy $academy,
        array $filters = [],
        int $perPage = 12,
    ): LengthAwarePaginator {
        $query = Course::query()
            ->forAcademy($academy)
            ->visibleToStudents()
            ->with(['category', 'trainers']);

        $this->applyCatalogueFilters($query, $filters);

        return $query->paginate($perPage);
    }

    public function create(
        Academy $academy,
        array $data,
        ?UploadedFile $thumbnail,
        User $actor,
    ): Course {
        $slugInput = trim((string) ($data['slug'] ?? ''));
        $data['slug'] = $slugInput !== '' ? Str::slug($slugInput) : Str::slug((string) ($data['title'] ?? ''));

        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('courses', 'slug')->where('academy_id', $academy->getKey()),
            ],
            'category_id' => ['nullable', 'uuid'],
            'description' => ['nullable', 'string', 'max:10000'],
            'difficulty' => ['nullable', 'string', Rule::in(Course::DIFFICULTIES)],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_free' => ['nullable', 'boolean'],
            'visibility' => ['nullable', 'string', Rule::in(Course::VISIBILITIES)],
            'enrollment_limit' => ['nullable', 'integer', 'min:1'],
            'duration_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'is_sequential' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', Rule::in(Course::STATUSES)],
            'trainer_ids' => ['nullable', 'array'],
            'trainer_ids.*' => ['uuid'],
            'prerequisite_ids' => ['nullable', 'array'],
            'prerequisite_ids.*' => ['uuid'],
        ])->validate();

        return DB::transaction(function () use ($academy, $validated, $thumbnail, $actor): Course {
            $categoryId = $validated['category_id'] ?? null;
            if ($categoryId !== null) {
                $this->assertCategoryBelongsToAcademy($academy, $categoryId);
            }

            $thumbnailPath = null;
            if ($thumbnail !== null) {
                $thumbnailPath = $thumbnail->store("academies/{$academy->getKey()}/courses", 'public');
            }

            $isFree = array_key_exists('is_free', $validated)
                ? (bool) $validated['is_free']
                : ((float) ($validated['price'] ?? 0) <= 0);

            $price = $isFree ? 0.00 : round((float) ($validated['price'] ?? 0), 2);
            $status = $validated['status'] ?? 'Draft';

            $course = Course::create([
                'academy_id' => $academy->getKey(),
                'category_id' => $categoryId,
                'title' => trim($validated['title']),
                'slug' => $validated['slug'],
                'description' => $validated['description'] ?? null,
                'difficulty' => $validated['difficulty'] ?? 'Beginner',
                'price' => $price,
                'currency' => strtoupper((string) ($validated['currency'] ?? 'USD')),
                'is_free' => $isFree,
                'visibility' => $validated['visibility'] ?? 'public',
                'enrollment_limit' => $validated['enrollment_limit'] ?? null,
                'thumbnail_path' => $thumbnailPath,
                'duration_months' => (int) ($validated['duration_months'] ?? 0),
                'is_sequential' => (bool) ($validated['is_sequential'] ?? false),
                'status' => $status,
                'published_at' => $status === 'Published' ? now() : null,
            ]);

            if (array_key_exists('trainer_ids', $validated)) {
                $this->syncTrainers($course, $validated['trainer_ids'] ?? [], $actor);
            }

            if (array_key_exists('prerequisite_ids', $validated)) {
                $this->syncPrerequisites($course, $validated['prerequisite_ids'] ?? [], $actor);
            }

            $this->recordAudit(
                $academy,
                $actor,
                'course.created',
                Course::class,
                $course->getKey(),
                [
                    'title' => $course->title,
                    'slug' => $course->slug,
                    'status' => $course->status,
                ],
            );

            return $course->load(['category', 'trainers', 'prerequisites']);
        });
    }

    public function update(
        Course $course,
        array $data,
        ?UploadedFile $thumbnail,
        User $actor,
    ): Course {
        if (array_key_exists('slug', $data)) {
            $slugInput = trim((string) ($data['slug'] ?? ''));
            $data['slug'] = $slugInput !== '' ? Str::slug($slugInput) : Str::slug((string) ($data['title'] ?? $course->title));
        }

        $validated = Validator::make($data, [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('courses', 'slug')
                    ->where('academy_id', $course->academy_id)
                    ->ignore($course->getKey()),
            ],
            'category_id' => ['nullable', 'uuid'],
            'description' => ['nullable', 'string', 'max:10000'],
            'difficulty' => ['nullable', 'string', Rule::in(Course::DIFFICULTIES)],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_free' => ['nullable', 'boolean'],
            'visibility' => ['nullable', 'string', Rule::in(Course::VISIBILITIES)],
            'enrollment_limit' => ['nullable', 'integer', 'min:1'],
            'duration_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'is_sequential' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', Rule::in(Course::STATUSES)],
            'trainer_ids' => ['nullable', 'array'],
            'trainer_ids.*' => ['uuid'],
            'prerequisite_ids' => ['nullable', 'array'],
            'prerequisite_ids.*' => ['uuid'],
        ])->validate();

        return DB::transaction(function () use ($course, $validated, $thumbnail, $actor): Course {
            $academy = $course->academy;

            if (array_key_exists('category_id', $validated) && $validated['category_id'] !== null) {
                $this->assertCategoryBelongsToAcademy($academy, $validated['category_id']);
            }

            $updates = [];

            foreach (['title', 'slug', 'category_id', 'description', 'difficulty', 'visibility', 'enrollment_limit'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $updates[$field] = is_string($validated[$field]) && $field === 'title'
                        ? trim($validated[$field])
                        : $validated[$field];
                }
            }

            if (array_key_exists('currency', $validated)) {
                $updates['currency'] = strtoupper((string) ($validated['currency'] ?? 'USD'));
            }
            if (array_key_exists('duration_months', $validated)) {
                $updates['duration_months'] = (int) ($validated['duration_months'] ?? 0);
            }
            if (array_key_exists('is_sequential', $validated)) {
                $updates['is_sequential'] = (bool) $validated['is_sequential'];
            }

            if (array_key_exists('is_free', $validated) || array_key_exists('price', $validated)) {
                $isFree = array_key_exists('is_free', $validated)
                    ? (bool) $validated['is_free']
                    : ((float) ($validated['price'] ?? $course->price) <= 0);
                $updates['is_free'] = $isFree;
                $updates['price'] = $isFree
                    ? 0.00
                    : round((float) ($validated['price'] ?? $course->price), 2);
            }

            if (array_key_exists('status', $validated) && $validated['status'] !== null) {
                $updates['status'] = $validated['status'];
                if ($validated['status'] === 'Published' && $course->published_at === null) {
                    $updates['published_at'] = now();
                }
            }

            if ($thumbnail !== null) {
                if (! empty($course->thumbnail_path) && Storage::disk('public')->exists($course->thumbnail_path)) {
                    Storage::disk('public')->delete($course->thumbnail_path);
                }

                $updates['thumbnail_path'] = $thumbnail->store("academies/{$course->academy_id}/courses", 'public');
            }

            if ($updates !== []) {
                $course->update($updates);
            }

            if (array_key_exists('trainer_ids', $validated)) {
                $this->syncTrainers($course, $validated['trainer_ids'] ?? [], $actor);
            }

            if (array_key_exists('prerequisite_ids', $validated)) {
                $this->syncPrerequisites($course, $validated['prerequisite_ids'] ?? [], $actor);
            }

            $this->recordAudit(
                $academy,
                $actor,
                'course.updated',
                Course::class,
                $course->getKey(),
                ['updated_fields' => array_keys($updates)],
            );

            return $course->refresh()->load(['category', 'trainers', 'prerequisites']);
        });
    }

    public function transitionStatus(Course $course, string $status, User $actor): Course
    {
        if (! in_array($status, Course::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'Invalid course status.',
            ]);
        }

        return DB::transaction(function () use ($course, $status, $actor): Course {
            $oldStatus = $course->status;
            $updates = ['status' => $status];

            if ($status === 'Published' && $course->published_at === null) {
                $updates['published_at'] = now();
            }

            $course->update($updates);

            $this->recordAudit(
                $course->academy,
                $actor,
                'course.status_changed',
                Course::class,
                $course->getKey(),
                [
                    'from' => $oldStatus,
                    'to' => $status,
                ],
            );

            return $course->refresh();
        });
    }

    public function syncTrainers(Course $course, array $trainerIds, User $actor): void
    {
        $trainerIds = array_values(array_unique(array_filter(
            array_map(static fn ($id): string => (string) $id, $trainerIds)
        )));

        if ($trainerIds !== []) {
            $validCount = User::role('trainer')
                ->where('is_active', true)
                ->whereIn('id', $trainerIds)
                ->count();

            if ($validCount !== count($trainerIds)) {
                throw ValidationException::withMessages([
                    'trainer_ids' => 'All assigned trainers must have an active trainer account.',
                ]);
            }
        }

        DB::transaction(function () use ($course, $trainerIds, $actor): void {
            DB::table('course_trainers')
                ->where('course_id', $course->getKey())
                ->delete();

            $now = now();
            $rows = [];
            foreach ($trainerIds as $index => $userId) {
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'academy_id' => $course->academy_id,
                    'course_id' => $course->getKey(),
                    'user_id' => $userId,
                    'is_primary' => $index === 0,
                    'created_at' => $now,
                ];
            }

            if ($rows !== []) {
                DB::table('course_trainers')->insert($rows);
            }

            $this->recordAudit(
                $course->academy,
                $actor,
                'course.trainers_synced',
                Course::class,
                $course->getKey(),
                ['trainer_ids' => $trainerIds],
            );
        });
    }

    public function syncPrerequisites(Course $course, array $prerequisiteIds, User $actor): void
    {
        $prerequisiteIds = array_values(array_unique(array_filter(
            array_map(static fn ($id): string => (string) $id, $prerequisiteIds)
        )));

        if (in_array($course->getKey(), $prerequisiteIds, true)) {
            throw ValidationException::withMessages([
                'prerequisite_ids' => 'A course cannot be a prerequisite of itself.',
            ]);
        }

        if ($prerequisiteIds !== []) {
            $validCount = Course::query()
                ->where('academy_id', $course->academy_id)
                ->whereIn('id', $prerequisiteIds)
                ->count();

            if ($validCount !== count($prerequisiteIds)) {
                throw ValidationException::withMessages([
                    'prerequisite_ids' => 'All prerequisites must belong to the same academy.',
                ]);
            }

            $this->assertNoCircularPrerequisites($course, $prerequisiteIds);
        }

        DB::transaction(function () use ($course, $prerequisiteIds, $actor): void {
            DB::table('course_prerequisites')
                ->where('course_id', $course->getKey())
                ->delete();

            $now = now();
            $rows = [];
            foreach ($prerequisiteIds as $prerequisiteId) {
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'academy_id' => $course->academy_id,
                    'course_id' => $course->getKey(),
                    'prerequisite_course_id' => $prerequisiteId,
                    'created_at' => $now,
                ];
            }

            if ($rows !== []) {
                DB::table('course_prerequisites')->insert($rows);
            }

            $this->recordAudit(
                $course->academy,
                $actor,
                'course.prerequisites_synced',
                Course::class,
                $course->getKey(),
                ['prerequisite_ids' => $prerequisiteIds],
            );
        });
    }

    private function applyCatalogueFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['difficulty']) && in_array($filters['difficulty'], Course::DIFFICULTIES, true)) {
            $query->where('difficulty', $filters['difficulty']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['visibility']) && in_array($filters['visibility'], Course::VISIBILITIES, true)) {
            $query->where('visibility', $filters['visibility']);
        }

        $sort = (string) ($filters['sort'] ?? 'newest');
        match ($sort) {
            'title' => $query->orderBy('title'),
            'price_asc' => $query->orderBy('price', 'asc')->latest(),
            'price_desc' => $query->orderBy('price', 'desc')->latest(),
            default => $query->latest(),
        };
    }

    private function assertCategoryBelongsToAcademy(Academy $academy, string $categoryId): void
    {
        $exists = CourseCategory::query()
            ->where('academy_id', $academy->getKey())
            ->where('id', $categoryId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'category_id' => 'The selected category does not belong to this academy.',
            ]);
        }
    }

    private function assertNoCircularPrerequisites(Course $course, array $prerequisiteIds): void
    {
        $edges = DB::table('course_prerequisites')
            ->where('academy_id', $course->academy_id)
            ->get(['course_id', 'prerequisite_course_id'])
            ->groupBy('course_id')
            ->map(static fn ($items): array => $items->pluck('prerequisite_course_id')->all())
            ->all();

        $visited = [];
        $queue = $prerequisiteIds;

        while ($queue !== []) {
            $current = array_shift($queue);
            if ($current === $course->getKey()) {
                throw ValidationException::withMessages([
                    'prerequisite_ids' => 'Circular prerequisite dependency detected.',
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
