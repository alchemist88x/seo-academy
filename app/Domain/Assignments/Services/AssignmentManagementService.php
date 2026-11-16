<?php

namespace App\Domain\Assignments\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentRubricCriterion;
use App\Domain\Courses\Models\Course;
use App\Domain\Platform\Services\AuditLogService;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AssignmentManagementService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Create a new assignment for an academy and course.
     */
    public function createAssignment(array $data, User $actor, Academy $academy): Assignment
    {
        $courseId = $data['course_id'] ?? null;
        if (! $courseId) {
            throw new InvalidArgumentException('A valid course_id is required.');
        }

        $course = Course::query()->forAcademy($academy)->findOrFail($courseId);

        // Check trainer course assignment if actor is trainer
        if ($actor->hasRole('trainer') && ! $actor->hasRole('admin')) {
            $isAssigned = $course->trainers()->where('users.id', $actor->getKey())->exists();
            if (! $isAssigned) {
                throw new DomainException('You are not assigned to teach this course.');
            }
        }

        return DB::transaction(function () use ($data, $actor, $academy, $course) {
            $maxMarks = (float) ($data['max_marks'] ?? 100.00);
            $passingMarks = (float) ($data['passing_marks'] ?? 50.00);

            if ($passingMarks > $maxMarks) {
                throw new InvalidArgumentException('Passing marks cannot exceed maximum marks.');
            }

            $assignment = Assignment::create([
                'id' => (string) Str::uuid(),
                'academy_id' => $academy->id,
                'course_id' => $course->id,
                'module_id' => $data['module_id'] ?? null,
                'lesson_id' => $data['lesson_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'submission_guidelines' => $data['submission_guidelines'] ?? null,
                'assignment_type' => $data['assignment_type'] ?? Assignment::TYPE_FILE_AND_TEXT,
                'max_marks' => $maxMarks,
                'passing_marks' => $passingMarks,
                'due_date' => $data['due_date'] ?? null,
                'available_from' => $data['available_from'] ?? null,
                'available_to' => $data['available_to'] ?? null,
                'late_submission_policy' => $data['late_submission_policy'] ?? Assignment::POLICY_ALLOWED_NO_PENALTY,
                'late_penalty_percent' => (float) ($data['late_penalty_percent'] ?? 0.00),
                'late_hard_deadline' => $data['late_hard_deadline'] ?? null,
                'max_attempts' => isset($data['max_attempts']) && $data['max_attempts'] !== '' ? (int) $data['max_attempts'] : null,
                'allow_resubmission' => (bool) ($data['allow_resubmission'] ?? true),
                'allow_multiple_files' => (bool) ($data['allow_multiple_files'] ?? true),
                'max_file_count' => (int) ($data['max_file_count'] ?? 5),
                'max_file_size_mb' => (int) ($data['max_file_size_mb'] ?? 25),
                'allowed_file_types' => $data['allowed_file_types'] ?? ['pdf', 'docx', 'xlsx', 'pptx', 'zip', 'csv', 'txt', 'png', 'jpg', 'webp'],
                'rubric_mode' => $data['rubric_mode'] ?? 'none',
                'is_mandatory' => (bool) ($data['is_mandatory'] ?? false),
                'status' => $data['status'] ?? Assignment::STATUS_DRAFT,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            if (! empty($data['rubric_criteria']) && is_array($data['rubric_criteria'])) {
                $this->syncRubricCriteria($assignment, $data['rubric_criteria']);
            }

            $this->auditLogService->record(
                $academy,
                $actor,
                'assignment_created',
                Assignment::class,
                $assignment->id,
                ['title' => $assignment->title, 'course_id' => $course->id]
            );

            return $assignment;
        });
    }

    /**
     * Update an existing assignment.
     */
    public function updateAssignment(Assignment $assignment, array $data, User $actor): Assignment
    {
        $academy = $assignment->academy ?? Academy::current();

        if ($actor->hasRole('trainer') && ! $actor->hasRole('admin')) {
            $isAssigned = $assignment->course?->trainers()->where('users.id', $actor->getKey())->exists();
            if (! $isAssigned) {
                throw new DomainException('You are not assigned to teach this course.');
            }
        }

        return DB::transaction(function () use ($assignment, $data, $actor, $academy) {
            $maxMarks = isset($data['max_marks']) ? (float) $data['max_marks'] : (float) $assignment->max_marks;
            $passingMarks = isset($data['passing_marks']) ? (float) $data['passing_marks'] : (float) $assignment->passing_marks;

            if ($passingMarks > $maxMarks) {
                throw new InvalidArgumentException('Passing marks cannot exceed maximum marks.');
            }

            $assignment->fill([
                'module_id' => array_key_exists('module_id', $data) ? $data['module_id'] : $assignment->module_id,
                'lesson_id' => array_key_exists('lesson_id', $data) ? $data['lesson_id'] : $assignment->lesson_id,
                'title' => $data['title'] ?? $assignment->title,
                'description' => array_key_exists('description', $data) ? $data['description'] : $assignment->description,
                'instructions' => array_key_exists('instructions', $data) ? $data['instructions'] : $assignment->instructions,
                'submission_guidelines' => array_key_exists('submission_guidelines', $data) ? $data['submission_guidelines'] : $assignment->submission_guidelines,
                'assignment_type' => $data['assignment_type'] ?? $assignment->assignment_type,
                'max_marks' => $maxMarks,
                'passing_marks' => $passingMarks,
                'due_date' => array_key_exists('due_date', $data) ? $data['due_date'] : $assignment->due_date,
                'available_from' => array_key_exists('available_from', $data) ? $data['available_from'] : $assignment->available_from,
                'available_to' => array_key_exists('available_to', $data) ? $data['available_to'] : $assignment->available_to,
                'late_submission_policy' => $data['late_submission_policy'] ?? $assignment->late_submission_policy,
                'late_penalty_percent' => isset($data['late_penalty_percent']) ? (float) $data['late_penalty_percent'] : $assignment->late_penalty_percent,
                'late_hard_deadline' => array_key_exists('late_hard_deadline', $data) ? $data['late_hard_deadline'] : $assignment->late_hard_deadline,
                'max_attempts' => array_key_exists('max_attempts', $data) ? ($data['max_attempts'] !== '' && $data['max_attempts'] !== null ? (int) $data['max_attempts'] : null) : $assignment->max_attempts,
                'allow_resubmission' => isset($data['allow_resubmission']) ? (bool) $data['allow_resubmission'] : $assignment->allow_resubmission,
                'allow_multiple_files' => isset($data['allow_multiple_files']) ? (bool) $data['allow_multiple_files'] : $assignment->allow_multiple_files,
                'max_file_count' => isset($data['max_file_count']) ? (int) $data['max_file_count'] : $assignment->max_file_count,
                'max_file_size_mb' => isset($data['max_file_size_mb']) ? (int) $data['max_file_size_mb'] : $assignment->max_file_size_mb,
                'allowed_file_types' => $data['allowed_file_types'] ?? $assignment->allowed_file_types,
                'rubric_mode' => $data['rubric_mode'] ?? $assignment->rubric_mode,
                'is_mandatory' => isset($data['is_mandatory']) ? (bool) $data['is_mandatory'] : $assignment->is_mandatory,
                'status' => $data['status'] ?? $assignment->status,
                'updated_by' => $actor->id,
            ]);

            $assignment->save();

            if (isset($data['rubric_criteria']) && is_array($data['rubric_criteria'])) {
                $this->syncRubricCriteria($assignment, $data['rubric_criteria']);
            }

            if ($academy) {
                $this->auditLogService->record(
                    $academy,
                    $actor,
                    'assignment_updated',
                    Assignment::class,
                    $assignment->id,
                    ['title' => $assignment->title, 'status' => $assignment->status]
                );
            }

            return $assignment;
        });
    }

    /**
     * Publish an assignment.
     */
    public function publishAssignment(Assignment $assignment, User $actor): void
    {
        $academy = $assignment->academy ?? Academy::current();

        $assignment->status = Assignment::STATUS_PUBLISHED;
        $assignment->updated_by = $actor->id;
        $assignment->save();

        if ($academy) {
            $this->auditLogService->record(
                $academy,
                $actor,
                'assignment_published',
                Assignment::class,
                $assignment->id,
                ['title' => $assignment->title]
            );
        }
    }

    /**
     * Archive an assignment.
     */
    public function archiveAssignment(Assignment $assignment, User $actor): void
    {
        $academy = $assignment->academy ?? Academy::current();

        $assignment->status = Assignment::STATUS_ARCHIVED;
        $assignment->updated_by = $actor->id;
        $assignment->save();

        if ($academy) {
            $this->auditLogService->record(
                $academy,
                $actor,
                'assignment_archived',
                Assignment::class,
                $assignment->id,
                ['title' => $assignment->title]
            );
        }
    }

    /**
     * Synchronize structured rubric criteria for an assignment.
     */
    public function syncRubricCriteria(Assignment $assignment, array $criteriaData): void
    {
        $assignment->rubricCriteria()->delete();

        $order = 1;
        $totalRubricMarks = 0.0;

        foreach ($criteriaData as $item) {
            if (empty($item['title'])) {
                continue;
            }

            $maxMarks = (float) ($item['max_marks'] ?? 0.0);
            $totalRubricMarks += $maxMarks;

            AssignmentRubricCriterion::create([
                'id' => (string) Str::uuid(),
                'assignment_id' => $assignment->id,
                'title' => $item['title'],
                'description' => $item['description'] ?? null,
                'max_marks' => $maxMarks,
                'display_order' => $item['display_order'] ?? $order++,
                'levels' => $item['levels'] ?? null,
            ]);
        }

        // Validate that total rubric marks matches max_marks if criteria were configured
        if ($order > 1 && abs($totalRubricMarks - (float) $assignment->max_marks) > 0.001) {
            throw new InvalidArgumentException(sprintf(
                'Total rubric criteria marks (%.2f) must match assignment maximum marks (%.2f).',
                $totalRubricMarks,
                (float) $assignment->max_marks
            ));
        }
    }

    /**
     * Validate whether the rubric criteria total matches the assignment max marks.
     */
    public function validateRubricMarks(Assignment $assignment): bool
    {
        $total = (float) $assignment->rubricCriteria()->sum('max_marks');

        return abs($total - (float) $assignment->max_marks) < 0.001;
    }
}
