<?php

namespace App\Domain\Students\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Platform\Concerns\RecordsAuditLog;
use App\Domain\Students\Models\ProficiencyLevel;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProficiencyLevelService
{
    use RecordsAuditLog;

    public function ensureDefaultLevels(Academy $academy): void
    {
        $count = ProficiencyLevel::where('academy_id', $academy->getKey())->count();
        if ($count > 0) {
            return;
        }

        $defaults = [
            [
                'name' => 'Beginner',
                'slug' => 'beginner',
                'description' => 'Foundational concepts and essential introductory knowledge.',
                'display_order' => 1,
                'color' => 'blue',
            ],
            [
                'name' => 'Intermediate',
                'slug' => 'intermediate',
                'description' => 'Practical competency, standard workflow execution, and hands-on skill.',
                'display_order' => 2,
                'color' => 'amber',
            ],
            [
                'name' => 'Expert',
                'slug' => 'expert',
                'description' => 'Advanced strategic mastery, technical depth, and industry leadership.',
                'display_order' => 3,
                'color' => 'purple',
            ],
        ];

        foreach ($defaults as $row) {
            ProficiencyLevel::firstOrCreate(
                [
                    'academy_id' => $academy->getKey(),
                    'slug' => $row['slug'],
                ],
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'display_order' => $row['display_order'],
                    'color' => $row['color'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * @return Collection<int, ProficiencyLevel>
     */
    public function listForAcademy(Academy $academy, bool $activeOnly = false): Collection
    {
        $this->ensureDefaultLevels($academy);

        $query = ProficiencyLevel::query()
            ->where('academy_id', $academy->getKey())
            ->withCount('studentProfiles')
            ->ordered();

        if ($activeOnly) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Get associative array of active levels: [slug => name]
     *
     * @return array<string, string>
     */
    public function getActiveOptions(Academy $academy): array
    {
        return $this->listForAcademy($academy, activeOnly: true)
            ->pluck('name', 'slug')
            ->toArray();
    }

    public function create(Academy $academy, array $data, ?User $actor = null): ProficiencyLevel
    {
        $this->ensureDefaultLevels($academy);

        if (empty($data['slug']) && ! empty($data['name'])) {
            $data['slug'] = Str::slug($data['name'], '_');
        } elseif (! empty($data['slug'])) {
            $data['slug'] = Str::slug($data['slug'], '_');
        }

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:100',
                Rule::unique('proficiency_levels')->where(fn ($q) => $q->where('academy_id', $academy->getKey())),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'color' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ])->validate();

        $displayOrder = $validated['display_order'] ?? null;
        if ($displayOrder === null) {
            $maxOrder = ProficiencyLevel::where('academy_id', $academy->getKey())->max('display_order') ?? 0;
            $displayOrder = $maxOrder + 1;
        }

        $level = ProficiencyLevel::create([
            'academy_id' => $academy->getKey(),
            'name' => trim($validated['name']),
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'display_order' => $displayOrder,
            'color' => $validated['color'] ?? 'blue',
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->recordAudit(
            $academy,
            $actor,
            'proficiency_level.created',
            ProficiencyLevel::class,
            $level->getKey(),
            [
                'name' => $level->name,
                'slug' => $level->slug,
            ]
        );

        return $level;
    }

    public function update(ProficiencyLevel $level, array $data, ?User $actor = null): ProficiencyLevel
    {
        if (isset($data['name']) && ! isset($data['slug'])) {
            // Keep existing slug unless explicitly provided
            $data['slug'] = $level->slug;
        } elseif (! empty($data['slug'])) {
            $data['slug'] = Str::slug($data['slug'], '_');
        }

        $validated = Validator::make($data, [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('proficiency_levels')
                    ->where(fn ($q) => $q->where('academy_id', $level->academy_id))
                    ->ignore($level->getKey()),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'color' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ])->validate();

        $oldSlug = $level->slug;
        $level->update($validated);

        // If slug or name changed, update synchronized fields on linked student profiles
        if (isset($validated['slug']) && $validated['slug'] !== $oldSlug) {
            StudentProfile::where('proficiency_level_id', $level->getKey())
                ->update([
                    'proficiency' => $level->slug,
                    'level' => $level->name,
                ]);
        } elseif (isset($validated['name'])) {
            StudentProfile::where('proficiency_level_id', $level->getKey())
                ->update(['level' => $level->name]);
        }

        $this->recordAudit(
            $level->academy,
            $actor,
            'proficiency_level.updated',
            ProficiencyLevel::class,
            $level->getKey(),
            [
                'name' => $level->name,
                'slug' => $level->slug,
                'is_active' => $level->is_active,
            ]
        );

        return $level->fresh();
    }

    public function delete(ProficiencyLevel $level, ?User $actor = null): bool
    {
        $assignedCount = $level->studentProfiles()->count();
        if ($assignedCount > 0) {
            throw ValidationException::withMessages([
                'error' => "Cannot delete proficiency level '{$level->name}' because {$assignedCount} student(s) are currently assigned to it. Deactivate it instead.",
            ]);
        }

        $academy = $level->academy;
        $levelName = $level->name;
        $levelId = $level->getKey();

        $deleted = $level->delete();

        if ($deleted) {
            $this->recordAudit(
                $academy,
                $actor,
                'proficiency_level.deleted',
                ProficiencyLevel::class,
                $levelId,
                ['name' => $levelName]
            );
        }

        return (bool) $deleted;
    }

    public function toggleActive(ProficiencyLevel $level, ?User $actor = null): ProficiencyLevel
    {
        $level->update(['is_active' => ! $level->is_active]);

        $this->recordAudit(
            $level->academy,
            $actor,
            'proficiency_level.toggled_active',
            ProficiencyLevel::class,
            $level->getKey(),
            ['is_active' => $level->is_active]
        );

        return $level->fresh();
    }

    /**
     * @param  array<int, string>  $orderedIds
     */
    public function reorder(Academy $academy, array $orderedIds, ?User $actor = null): void
    {
        DB::transaction(function () use ($academy, $orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                ProficiencyLevel::where('academy_id', $academy->getKey())
                    ->where('id', $id)
                    ->update(['display_order' => $index + 1]);
            }
        });

        $this->recordAudit(
            $academy,
            $actor,
            'proficiency_level.reordered',
            ProficiencyLevel::class,
            $academy->getKey(),
            ['count' => count($orderedIds)]
        );
    }
}
