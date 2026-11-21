<?php

namespace App\Livewire\Admin\Projects;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Projects\Models\PracticalProject;
use App\Domain\Projects\Services\PracticalProjectService;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit Practical SEO Project')]
class AdminProjectEditor extends Component
{
    public ?string $projectId = null;

    public string $title = '';

    public string $slug = '';

    public string $category = PracticalProject::CATEGORY_TECHNICAL_SEO;

    public string $difficulty = PracticalProject::DIFFICULTY_BEGINNER;

    public ?string $courseId = null;

    public string $description = '';

    public string $objectivesText = '';

    public string $deliverablesText = '';

    public string $instructions = '';

    public ?string $startDate = null;

    public ?string $dueDate = null;

    public float $maxMarks = 100.00;

    public float $passingMarks = 50.00;

    public int $maxRevisions = 3;

    public string $status = PracticalProject::STATUS_DRAFT;

    // References list
    public array $references = [];

    public string $newRefTitle = '';

    public string $newRefUrl = '';

    // Rubric criteria list
    public array $criteria = [];

    // Individual student assignment
    public array $assignedStudentProfileIds = [];

    public function mount(TenantContext $tenantContext, ?PracticalProject $project = null): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->hasRole('admin')) {
            abort(403, 'Unauthorized.');
        }

        if ($project && $project->exists) {
            $this->projectId = $project->id;
            $this->title = $project->title;
            $this->slug = $project->slug;
            $this->category = $project->category;
            $this->difficulty = $project->difficulty;
            $this->courseId = $project->course_id;
            $this->description = $project->description ?? '';
            $this->objectivesText = implode("\n", $project->objectives ?? []);
            $this->deliverablesText = implode("\n", $project->expected_deliverables ?? []);
            $this->instructions = $project->instructions ?? '';
            $this->startDate = $project->start_date ? $project->start_date->format('Y-m-d\TH:i') : null;
            $this->dueDate = $project->due_date ? $project->due_date->format('Y-m-d\TH:i') : null;
            $this->maxMarks = (float) $project->max_marks;
            $this->passingMarks = (float) $project->passing_marks;
            $this->maxRevisions = (int) $project->max_revisions;
            $this->status = $project->status;
            $this->references = $project->reference_materials ?? [];

            $this->criteria = $project->criteria->map(fn ($c) => [
                'title' => $c->title,
                'description' => $c->description,
                'max_marks' => (float) $c->max_marks,
                'weight' => (float) $c->weight,
                'passing_threshold' => $c->passing_threshold ? (float) $c->passing_threshold : null,
            ])->toArray();

            $this->assignedStudentProfileIds = $project->assignedStudents()->pluck('student_profiles.id')->all();
        } else {
            // Default rubric criteria
            $this->criteria = [
                ['title' => 'Technical Crawl & Error Diagnostics', 'description' => 'Comprehensive site crawl, identifying 4xx/5xx status codes, indexability issues, and broken internal links.', 'max_marks' => 30.0, 'weight' => 30.0, 'passing_threshold' => 15.0],
                ['title' => 'Core Web Vitals & Page Speed Analysis', 'description' => 'Accurate assessment of LCP, INP, and CLS metrics with actionable remediation steps.', 'max_marks' => 30.0, 'weight' => 30.0, 'passing_threshold' => 15.0],
                ['title' => 'Structured Data & Architecture Audit', 'description' => 'Schema.org JSON-LD implementation review and URL taxonomy evaluation.', 'max_marks' => 20.0, 'weight' => 20.0, 'passing_threshold' => 10.0],
                ['title' => 'Executive Summary & Client Action Plan', 'description' => 'Professional prioritization matrix and clear stakeholder reporting.', 'max_marks' => 20.0, 'weight' => 20.0, 'passing_threshold' => 10.0],
            ];
        }
    }

    public function addCriterion(): void
    {
        $this->criteria[] = [
            'title' => '',
            'description' => '',
            'max_marks' => 25.0,
            'weight' => 25.0,
            'passing_threshold' => 12.5,
        ];
    }

    public function removeCriterion(int $index): void
    {
        unset($this->criteria[$index]);
        $this->criteria = array_values($this->criteria);
    }

    public function addReference(): void
    {
        if (! empty(trim($this->newRefTitle))) {
            $this->references[] = [
                'title' => trim($this->newRefTitle),
                'url' => trim($this->newRefUrl),
            ];
            $this->newRefTitle = '';
            $this->newRefUrl = '';
        }
    }

    public function removeReference(int $index): void
    {
        unset($this->references[$index]);
        $this->references = array_values($this->references);
    }

    public function save(PracticalProjectService $service)
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'difficulty' => 'required|string',
            'maxMarks' => 'required|numeric|min:1',
            'passingMarks' => 'required|numeric|min:0|lte:maxMarks',
            'maxRevisions' => 'required|integer|min:0|max:10',
        ]);

        $academy = Academy::current();
        if (! $academy) {
            abort(500, 'Academy context not set.');
        }

        $objectives = array_values(array_filter(array_map('trim', explode("\n", $this->objectivesText))));
        $deliverables = array_values(array_filter(array_map('trim', explode("\n", $this->deliverablesText))));

        $data = [
            'title' => $this->title,
            'slug' => $this->slug,
            'category' => $this->category,
            'difficulty' => $this->difficulty,
            'course_id' => ! empty($this->courseId) ? $this->courseId : null,
            'description' => $this->description,
            'objectives' => $objectives,
            'expected_deliverables' => $deliverables,
            'instructions' => $this->instructions,
            'start_date' => $this->startDate,
            'due_date' => $this->dueDate,
            'max_marks' => $this->maxMarks,
            'passing_marks' => $this->passingMarks,
            'max_revisions' => $this->maxRevisions,
            'status' => $this->status,
            'reference_materials' => $this->references,
            'criteria' => $this->criteria,
        ];

        if ($this->projectId) {
            $project = PracticalProject::findOrFail($this->projectId);
            $service->updateProject($project, Auth::user(), $data);
            session()->flash('message', "Project \"{$project->title}\" updated successfully.");
        } else {
            $project = $service->createProject($academy, Auth::user(), $data);
            $this->projectId = $project->id;
            session()->flash('message', "Project \"{$project->title}\" created successfully.");
        }

        // Sync individual student assignments
        $currentAssigned = $project->assignedStudents()->pluck('student_profiles.id')->all();
        $toAdd = array_diff($this->assignedStudentProfileIds, $currentAssigned);
        $toRemove = array_diff($currentAssigned, $this->assignedStudentProfileIds);

        foreach ($toAdd as $profileId) {
            $profile = StudentProfile::find($profileId);
            if ($profile) {
                $service->assignToStudent($project, $profile, $project->due_date, Auth::user());
            }
        }

        foreach ($toRemove as $profileId) {
            $profile = StudentProfile::find($profileId);
            if ($profile) {
                $service->removeStudentAssignment($project, $profile);
            }
        }

        return redirect()->route('admin.projects.index');
    }

    public function render()
    {
        $courses = Course::orderBy('title')->get();
        $students = StudentProfile::with('user')->get();

        return view('livewire.admin.projects.admin-project-editor', [
            'courses' => $courses,
            'students' => $students,
            'categories' => PracticalProject::CATEGORIES,
            'difficulties' => PracticalProject::DIFFICULTIES,
        ]);
    }
}
