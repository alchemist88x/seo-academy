<?php

namespace App\Domain\Courses\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\CourseCategory;
use App\Domain\Platform\Concerns\RecordsAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourseCategoryService
{
    use RecordsAuditLog;

    public function listForAcademy(Academy $academy, bool $onlyActive = false): Collection
    {
        $query = CourseCategory::query()
            ->forAcademy($academy)
            ->withCount('courses')
            ->orderBy('display_order')
            ->orderBy('name');

        if ($onlyActive) {
            $query->active();
        }

        return $query->get();
    }

    public function create(Academy $academy, array $data, User $actor): CourseCategory
    {
        $slugInput = trim((string) ($data['slug'] ?? ''));
        $data['slug'] = $slugInput !== '' ? Str::slug($slugInput) : Str::slug((string) ($data['name'] ?? ''));

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('course_categories', 'slug')->where('academy_id', $academy->getKey()),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($academy, $validated, $actor): CourseCategory {
            $category = CourseCategory::create([
                'academy_id' => $academy->getKey(),
                'name' => trim($validated['name']),
                'slug' => $validated['slug'],
                'description' => $validated['description'] ?? null,
                'display_order' => (int) ($validated['display_order'] ?? 0),
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);

            $this->recordAudit(
                $academy,
                $actor,
                'course_category.created',
                CourseCategory::class,
                $category->getKey(),
                [
                    'name' => $category->name,
                    'slug' => $category->slug,
                ],
            );

            return $category;
        });
    }

    public function update(CourseCategory $category, array $data, User $actor): CourseCategory
    {
        if (array_key_exists('slug', $data) || array_key_exists('name', $data)) {
            $slugInput = trim((string) ($data['slug'] ?? ''));
            if ($slugInput !== '') {
                $data['slug'] = Str::slug($slugInput);
            } elseif (array_key_exists('slug', $data)) {
                $data['slug'] = Str::slug((string) ($data['name'] ?? $category->name));
            }
        }

        $validated = Validator::make($data, [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('course_categories', 'slug')
                    ->where('academy_id', $category->academy_id)
                    ->ignore($category->getKey()),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($category, $validated, $actor): CourseCategory {
            $updates = [];

            if (array_key_exists('name', $validated)) {
                $updates['name'] = trim($validated['name']);
            }
            if (array_key_exists('slug', $validated)) {
                $updates['slug'] = $validated['slug'];
            }
            if (array_key_exists('description', $validated)) {
                $updates['description'] = $validated['description'];
            }
            if (array_key_exists('display_order', $validated)) {
                $updates['display_order'] = (int) ($validated['display_order'] ?? 0);
            }
            if (array_key_exists('is_active', $validated)) {
                $updates['is_active'] = (bool) $validated['is_active'];
            }

            if ($updates !== []) {
                $category->update($updates);
            }

            $this->recordAudit(
                $category->academy,
                $actor,
                'course_category.updated',
                CourseCategory::class,
                $category->getKey(),
                ['updated_fields' => array_keys($updates)],
            );

            return $category->refresh();
        });
    }

    public function delete(CourseCategory $category, User $actor): void
    {
        DB::transaction(function () use ($category, $actor): void {
            $academy = $category->academy;
            $categoryId = $category->getKey();
            $name = $category->name;

            $category->delete();

            $this->recordAudit(
                $academy,
                $actor,
                'course_category.deleted',
                CourseCategory::class,
                $categoryId,
                ['name' => $name],
            );
        });
    }
}
