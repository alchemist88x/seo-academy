<?php

namespace App\Domain\Projects\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Notifications\Services\NotificationDispatcherService;
use App\Domain\Platform\Services\AuditLogService;
use App\Domain\Projects\Models\PracticalProject;
use App\Domain\Projects\Models\PracticalProjectStudent;
use App\Domain\Projects\Models\ProjectEvaluationCriterion;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PracticalProjectService
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected NotificationDispatcherService $notificationService
    ) {}

    /**
     * Create a practical project with validation, criteria, and optional course/student assignments.
     *
     * @param array{
     *     title: string,
     *     slug?: ?string,
     *     category: string,
     *     difficulty?: string,
     *     course_id?: ?string,
     *     description?: ?string,
     *     objectives?: array<int, string>,
     *     expected_deliverables?: array<int, string>,
     *     instructions?: ?string,
     *     start_date?: ?string,
     *     due_date?: ?string,
     *     max_marks?: float|int,
     *     passing_marks?: float|int,
     *     max_revisions?: int,
     *     reference_materials?: array<int, array{title: string, url?: string, note?: string}>,
     *     status?: string,
     *     criteria?: array<int, array{title: string, description?: ?string, max_marks: float|int, weight?: float|int, passing_threshold?: ?float, required_deliverables?: array<int, string>}>
     * } $data
     */
    public function createProject(Academy $academy, User $creator, array $data): PracticalProject
    {
        $this->validateProjectData($data);

        return DB::transaction(function () use ($academy, $creator, $data) {
            $project = PracticalProject::create([
                'id' => (string) Str::uuid(),
                'academy_id' => $academy->getKey(),
                'course_id' => $data['course_id'] ?? null,
                'title' => trim($data['title']),
                'slug' => ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['title']).'-'.Str::lower(Str::random(6)),
                'category' => $data['category'],
                'difficulty' => $data['difficulty'] ?? PracticalProject::DIFFICULTY_BEGINNER,
                'description' => $data['description'] ?? null,
                'objectives' => $data['objectives'] ?? [],
                'expected_deliverables' => $data['expected_deliverables'] ?? [],
                'instructions' => $data['instructions'] ?? null,
                'start_date' => ! empty($data['start_date']) ? Carbon::parse($data['start_date']) : null,
                'due_date' => ! empty($data['due_date']) ? Carbon::parse($data['due_date']) : null,
                'max_marks' => $data['max_marks'] ?? 100.00,
                'passing_marks' => $data['passing_marks'] ?? 50.00,
                'max_revisions' => $data['max_revisions'] ?? 3,
                'reference_materials' => $data['reference_materials'] ?? [],
                'status' => $data['status'] ?? PracticalProject::STATUS_DRAFT,
                'created_by' => $creator->getKey(),
                'updated_by' => $creator->getKey(),
            ]);

            if (! empty($data['criteria'])) {
                $this->syncCriteria($project, $data['criteria']);
            }

            $this->auditLogService->record(
                $academy,
                $creator,
                'practical_project_created',
                PracticalProject::class,
                $project->id,
                [
                    'title' => $project->title,
                    'category' => $project->category,
                    'difficulty' => $project->difficulty,
                    'course_id' => $project->course_id,
                    'status' => $project->status,
                ]
            );

            return $project;
        });
    }

    /**
     * Update project metadata and rubric criteria.
     */
    public function updateProject(PracticalProject $project, User $editor, array $data): PracticalProject
    {
        $this->validateProjectData($data, isUpdate: true);

        return DB::transaction(function () use ($project, $editor, $data) {
            $project->fill([
                'course_id' => array_key_exists('course_id', $data) ? $data['course_id'] : $project->course_id,
                'title' => ! empty($data['title']) ? trim($data['title']) : $project->title,
                'category' => $data['category'] ?? $project->category,
                'difficulty' => $data['difficulty'] ?? $project->difficulty,
                'description' => array_key_exists('description', $data) ? $data['description'] : $project->description,
                'objectives' => array_key_exists('objectives', $data) ? $data['objectives'] : $project->objectives,
                'expected_deliverables' => array_key_exists('expected_deliverables', $data) ? $data['expected_deliverables'] : $project->expected_deliverables,
                'instructions' => array_key_exists('instructions', $data) ? $data['instructions'] : $project->instructions,
                'start_date' => array_key_exists('start_date', $data) ? (! empty($data['start_date']) ? Carbon::parse($data['start_date']) : null) : $project->start_date,
                'due_date' => array_key_exists('due_date', $data) ? (! empty($data['due_date']) ? Carbon::parse($data['due_date']) : null) : $project->due_date,
                'max_marks' => array_key_exists('max_marks', $data) ? $data['max_marks'] : $project->max_marks,
                'passing_marks' => array_key_exists('passing_marks', $data) ? $data['passing_marks'] : $project->passing_marks,
                'max_revisions' => array_key_exists('max_revisions', $data) ? $data['max_revisions'] : $project->max_revisions,
                'reference_materials' => array_key_exists('reference_materials', $data) ? $data['reference_materials'] : $project->reference_materials,
                'status' => $data['status'] ?? $project->status,
                'updated_by' => $editor->getKey(),
            ]);

            $project->save();

            if (isset($data['criteria']) && is_array($data['criteria'])) {
                $this->syncCriteria($project, $data['criteria']);
            }

            $academy = $project->academy ?? Academy::current();
            if ($academy) {
                $this->auditLogService->record(
                    $academy,
                    $editor,
                    'practical_project_updated',
                    PracticalProject::class,
                    $project->id,
                    ['title' => $project->title, 'status' => $project->status]
                );
            }

            return $project;
        });
    }

    /**
     * Publish project and notify eligible students.
     */
    public function publishProject(PracticalProject $project, User $actor): PracticalProject
    {
        $project->update([
            'status' => PracticalProject::STATUS_PUBLISHED,
            'updated_by' => $actor->getKey(),
        ]);

        $academy = $project->academy ?? Academy::current();
        if ($academy) {
            $this->auditLogService->record(
                $academy,
                $actor,
                'practical_project_published',
                PracticalProject::class,
                $project->id,
                ['title' => $project->title]
            );
        }

        // Notify enrolled or individually assigned students
        $this->notifyAssignedStudentsOnPublish($project, $actor);

        return $project;
    }

    /**
     * Archive project.
     */
    public function archiveProject(PracticalProject $project, User $actor): PracticalProject
    {
        $project->update([
            'status' => PracticalProject::STATUS_ARCHIVED,
            'updated_by' => $actor->getKey(),
        ]);

        $academy = $project->academy ?? Academy::current();
        if ($academy) {
            $this->auditLogService->record(
                $academy,
                $actor,
                'practical_project_archived',
                PracticalProject::class,
                $project->id,
                ['title' => $project->title]
            );
        }

        return $project;
    }

    /**
     * Synchronize evaluation criteria for a project.
     */
    public function syncCriteria(PracticalProject $project, array $criteria): void
    {
        DB::transaction(function () use ($project, $criteria) {
            $project->criteria()->delete();

            $order = 1;
            foreach ($criteria as $item) {
                if (empty($item['title'])) {
                    continue;
                }

                ProjectEvaluationCriterion::create([
                    'id' => (string) Str::uuid(),
                    'project_id' => $project->id,
                    'title' => trim($item['title']),
                    'description' => $item['description'] ?? null,
                    'max_marks' => $item['max_marks'] ?? 10.00,
                    'weight' => $item['weight'] ?? 10.00,
                    'passing_threshold' => $item['passing_threshold'] ?? null,
                    'required_deliverables' => $item['required_deliverables'] ?? [],
                    'display_order' => $order++,
                ]);
            }
        });
    }

    /**
     * Assign project to a specific student.
     */
    public function assignToStudent(PracticalProject $project, StudentProfile $studentProfile, ?Carbon $dueDate = null, ?User $actor = null): PracticalProjectStudent
    {
        $assignment = PracticalProjectStudent::updateOrCreate(
            [
                'project_id' => $project->id,
                'student_profile_id' => $studentProfile->id,
            ],
            [
                'assigned_at' => Carbon::now(),
                'due_date_override' => $dueDate,
                'status' => PracticalProjectStudent::STATUS_ASSIGNED,
            ]
        );

        $studentUser = $studentProfile->user;
        if ($studentUser) {
            $this->notificationService->send(
                recipient: $studentUser,
                category: 'academic',
                title: 'New SEO Project Assigned',
                message: sprintf('You have been assigned the practical SEO project: "%s".', $project->title),
                link: route('student.projects.show', $project->id),
                actor: $actor,
                dedupKey: "project_assign_{$project->id}_{$studentProfile->id}",
                dedupWindowSeconds: 300
            );
        }

        return $assignment;
    }

    /**
     * Remove individual student assignment.
     */
    public function removeStudentAssignment(PracticalProject $project, StudentProfile $studentProfile): void
    {
        PracticalProjectStudent::query()
            ->where('project_id', $project->id)
            ->where('student_profile_id', $studentProfile->id)
            ->delete();
    }

    /**
     * Query projects accessible to a student:
     * 1. Assigned individually to the student via practical_project_students.
     * 2. Or published and attached to a course where the student has active enrollment.
     * 3. Or published and standalone (course_id is NULL) in the academy.
     */
    public function getAvailableProjectsForStudent(StudentProfile $studentProfile): Builder
    {
        $enrolledCourseIds = Enrollment::query()
            ->where('student_profile_id', $studentProfile->id)
            ->allowsCourseAccess()
            ->pluck('course_id')
            ->filter()
            ->values()
            ->all();

        return PracticalProject::query()
            ->where('academy_id', $studentProfile->academy_id)
            ->where('status', PracticalProject::STATUS_PUBLISHED)
            ->where(function (Builder $q) use ($studentProfile, $enrolledCourseIds) {
                // Individually assigned
                $q->whereHas('assignedStudents', function (Builder $sub) use ($studentProfile) {
                    $sub->where('practical_project_students.student_profile_id', $studentProfile->id);
                });

                // Enrolled course
                if (! empty($enrolledCourseIds)) {
                    $q->orWhereIn('course_id', $enrolledCourseIds);
                }

                // Global standalone published projects
                $q->orWhereNull('course_id');
            });
    }

    /**
     * Query projects accessible to a trainer:
     * - Only projects attached to courses the trainer is assigned to.
     */
    public function getProjectsForTrainer(User $trainer): Builder
    {
        $assignedCourseIds = Course::query()
            ->whereHas('trainers', function (Builder $q) use ($trainer) {
                $q->where('users.id', $trainer->getKey());
            })
            ->pluck('id')
            ->all();

        return PracticalProject::query()
            ->whereIn('course_id', $assignedCourseIds);
    }

    /**
     * Internal validator.
     */
    protected function validateProjectData(array $data, bool $isUpdate = false): void
    {
        if (! $isUpdate && empty($data['title'])) {
            throw new InvalidArgumentException('Project title is required.');
        }

        if (! empty($data['category']) && ! array_key_exists($data['category'], PracticalProject::CATEGORIES)) {
            throw new InvalidArgumentException(sprintf('Invalid project category "%s".', $data['category']));
        }

        if (! empty($data['difficulty']) && ! array_key_exists($data['difficulty'], PracticalProject::DIFFICULTIES)) {
            throw new InvalidArgumentException(sprintf('Invalid difficulty "%s".', $data['difficulty']));
        }

        if (! empty($data['max_marks']) && (float) $data['max_marks'] <= 0) {
            throw new InvalidArgumentException('Maximum marks must be greater than zero.');
        }

        if (! empty($data['passing_marks']) && ! empty($data['max_marks']) && (float) $data['passing_marks'] > (float) $data['max_marks']) {
            throw new InvalidArgumentException('Passing marks cannot exceed maximum marks.');
        }
    }

    protected function notifyAssignedStudentsOnPublish(PracticalProject $project, User $actor): void
    {
        $studentUserIds = collect();

        // 1. Individually assigned
        $individualUserIds = $project->assignedStudents()->with('user')->get()->pluck('user.id')->filter();
        $studentUserIds = $studentUserIds->merge($individualUserIds);

        // 2. Course-wide enrolled students
        if ($project->course_id) {
            $enrolledUserIds = Enrollment::query()
                ->where('course_id', $project->course_id)
                ->allowsCourseAccess()
                ->with('studentProfile.user')
                ->get()
                ->pluck('studentProfile.user.id')
                ->filter();

            $studentUserIds = $studentUserIds->merge($enrolledUserIds);
        }

        $studentUsers = User::query()->whereIn('id', $studentUserIds->unique())->get();

        foreach ($studentUsers as $student) {
            $this->notificationService->send(
                recipient: $student,
                category: 'academic',
                title: 'New SEO Project Available',
                message: sprintf('Practical project "%s" is now available for completion.', $project->title),
                link: route('student.projects.show', $project->id),
                actor: $actor,
                dedupKey: "project_pub_{$project->id}_{$student->id}",
                dedupWindowSeconds: 300
            );
        }
    }
}
