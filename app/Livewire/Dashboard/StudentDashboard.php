<?php

namespace App\Livewire\Dashboard;

use App\Domain\Learning\Models\LessonProgress;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Student Dashboard')]
class StudentDashboard extends Component
{
    public function render()
    {
        $user = Auth::user();
        $studentProfile = $user?->studentProfile;

        $enrollments = $studentProfile
            ? $studentProfile->enrollments()->with(['course.category', 'course.modules.lessons'])->get()
            : collect();

        $activeCoursesCount = $enrollments->where('status', 'active')->count();
        $completedCoursesCount = $enrollments->where('status', 'completed')->count();
        $totalEnrollmentsCount = $enrollments->count();

        // Calculate completed lessons from LessonProgress
        $completedLessonsCount = $user
            ? LessonProgress::where('user_id', $user->id)
                ->where('status', 'completed')
                ->count()
            : 0;

        // Total lessons across all enrolled courses
        $allEnrolledLessonsCount = $enrollments->pluck('course')
            ->filter()
            ->flatMap(fn ($c) => $c->modules)
            ->flatMap(fn ($m) => $m->lessons)
            ->count();

        $lessonsValue = $allEnrolledLessonsCount > 0
            ? "{$completedLessonsCount} / {$allEnrolledLessonsCount}"
            : (string) $completedLessonsCount;

        $lessonsTrend = $allEnrolledLessonsCount > 0
            ? round(($completedLessonsCount / $allEnrolledLessonsCount) * 100).'% complete'
            : ($completedLessonsCount > 0 ? 'Completed' : 'Get started');

        $stats = [
            [
                'label' => 'Active Courses',
                'value' => (string) $activeCoursesCount,
                'trend' => $totalEnrollmentsCount.' total enrolled',
                'direction' => 'up',
            ],
            [
                'label' => 'Lessons Completed',
                'value' => $lessonsValue,
                'trend' => $lessonsTrend,
                'direction' => 'up',
            ],
            [
                'label' => 'Courses Completed',
                'value' => (string) $completedCoursesCount,
                'trend' => $completedCoursesCount > 0 ? 'Certified' : 'In progress',
                'direction' => $completedCoursesCount > 0 ? 'up' : 'neutral',
            ],
            [
                'label' => 'Total Enrollments',
                'value' => (string) $totalEnrollmentsCount,
                'trend' => 'Academic records',
                'direction' => 'neutral',
            ],
        ];

        $activeEnrollments = $enrollments->where('status', 'active')->take(3);

        return view('livewire.dashboard.student-dashboard', [
            'userName' => $user?->name ?? 'Student',
            'proficiencyLabel' => $studentProfile?->proficiency_label ?? 'Beginner',
            'proficiencyBadgeColor' => $studentProfile?->proficiency_badge_color ?? 'blue',
            'stats' => $stats,
            'activeEnrollments' => $activeEnrollments,
            'hasAnyEnrollments' => $totalEnrollmentsCount > 0,
        ]);
    }
}
