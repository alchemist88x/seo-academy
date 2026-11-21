<?php

namespace App\Livewire\Admin\Analytics;

use App\Domain\Analytics\Services\CourseAnalyticsService;
use App\Domain\Courses\Models\Course;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Course Analytics')]
class CourseAnalyticsView extends Component
{
    public Course $course;

    public bool $showAccessibleTables = false;

    public function mount(Course $course): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $isAdmin = $user->hasRole('admin');
        $isTrainer = $user->hasRole('trainer');

        if (! $isAdmin && ! $isTrainer) {
            abort(403, 'Unauthorized.');
        }

        // Trainer assignment authorization check
        if ($isTrainer && ! $isAdmin) {
            $isAssigned = $course->trainers()->where('users.id', $user->id)->exists();
            if (! $isAssigned) {
                abort(403, 'You are not assigned as an instructor for this course.');
            }
        }

        $this->course = $course;
    }

    public function toggleAccessibleTables(): void
    {
        $this->showAccessibleTables = ! $this->showAccessibleTables;
    }

    public function render(CourseAnalyticsService $analyticsService)
    {
        $metrics = $analyticsService->getCourseMetrics($this->course);

        return view('livewire.admin.analytics.course-analytics-view', [
            'metrics' => $metrics,
            'course' => $this->course,
        ]);
    }
}
