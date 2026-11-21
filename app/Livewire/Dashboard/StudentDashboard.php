<?php

namespace App\Livewire\Dashboard;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Courses\Models\Course;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Services\CourseNavigationService;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Student Dashboard')]
class StudentDashboard extends Component
{
    public string $filterTab = 'all'; // all | in_progress | completed

    public string $search = '';

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['all', 'in_progress', 'completed'], true)) {
            $this->filterTab = $tab;
        }
    }

    public function render(TenantContext $tenantContext, CourseNavigationService $navigationService)
    {
        $user = Auth::user();
        $studentProfile = $user?->studentProfile;
        $academy = $tenantContext->getAcademy() ?? Academy::current();

        $rawEnrollments = $studentProfile
            ? $studentProfile->enrollments()
                ->with(['course.category', 'course.trainers', 'course.modules.lessons'])
                ->latest('last_active_at')
                ->latest('updated_at')
                ->get()
            : collect();

        $totalEnrollmentsCount = $rawEnrollments->count();
        $activeCoursesCount = $rawEnrollments->where('status', 'active')->count();
        $completedCoursesCount = $rawEnrollments->where('status', 'completed')->count();

        // Calculate completed lessons across all enrolled courses
        $completedLessonsCount = $user
            ? LessonProgress::query()
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->count()
            : 0;

        $allEnrolledLessonsCount = $rawEnrollments->pluck('course')
            ->filter()
            ->flatMap(fn ($c) => $c->modules)
            ->flatMap(fn ($m) => $m->lessons)
            ->count();

        // Compute detailed progress and resume lesson for each enrollment
        $enrichedEnrollments = $rawEnrollments->map(function (Enrollment $enrollment) use ($user, $navigationService) {
            $course = $enrollment->course;
            $totalLessons = $course ? $course->modules->flatMap->lessons->count() : 0;

            $completedInCourse = LessonProgress::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('status', 'completed')
                ->count();

            $progressPercent = $totalLessons > 0 ? (int) round(($completedInCourse / $totalLessons) * 100) : 0;
            if ($enrollment->status === 'completed') {
                $progressPercent = 100;
            }

            $resumeLesson = null;
            if ($course && $user) {
                $resumeLesson = $navigationService->resolveResumeLesson($course, $user);
            }

            return [
                'enrollment' => $enrollment,
                'course' => $course,
                'status' => strtolower((string) ($enrollment->status ?? 'pending')),
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedInCourse,
                'progress_percent' => $progressPercent,
                'resume_lesson' => $resumeLesson,
            ];
        });

        // 1. Identify primary "Continue Learning" course
        $continueLearning = null;
        $activeEnrollments = $enrichedEnrollments->filter(fn ($item) => $item['status'] === 'active' && $item['course'] !== null);

        if ($activeEnrollments->isNotEmpty()) {
            // Prefer enrollment with most recent last_active_at or first active
            $primaryItem = $activeEnrollments->sortByDesc(fn ($item) => $item['enrollment']->last_active_at)->first();
            if ($primaryItem) {
                $continueLearning = $primaryItem;
            }
        }

        // 2. Filter enrollments by search and tab
        $filteredCourses = $enrichedEnrollments->filter(function ($item) {
            if ($this->filterTab === 'in_progress' && $item['status'] !== 'active') {
                return false;
            }
            if ($this->filterTab === 'completed' && $item['status'] !== 'completed') {
                return false;
            }
            if ($this->search !== '') {
                $term = strtolower(trim($this->search));
                $title = strtolower($item['course']?->title ?? '');
                $cat = strtolower($item['course']?->category?->name ?? '');

                return str_contains($title, $term) || str_contains($cat, $term);
            }

            return true;
        });

        // 3. Upcoming Assignments
        $enrolledCourseIds = $rawEnrollments->pluck('course_id')->filter()->all();
        $upcomingAssignments = collect();
        if (! empty($enrolledCourseIds) && $user) {
            $upcomingAssignments = Assignment::query()
                ->whereIn('course_id', $enrolledCourseIds)
                ->where('status', 'published')
                ->where(function ($q) {
                    $q->whereNull('due_date')->orWhere('due_date', '>=', now()->subDays(1));
                })
                ->with(['course'])
                ->orderBy('due_date')
                ->take(3)
                ->get()
                ->map(function (Assignment $assignment) use ($user) {
                    $submission = AssignmentSubmission::query()
                        ->where('assignment_id', $assignment->id)
                        ->where('user_id', $user->id)
                        ->latest('version_number')
                        ->first();

                    return [
                        'assignment' => $assignment,
                        'is_submitted' => $submission !== null,
                        'status' => $submission ? $submission->status : 'pending',
                    ];
                });
        }

        // 4. Recently Earned Certificates
        $recentCertificates = $user
            ? Certificate::query()
                ->where('user_id', $user->id)
                ->where('status', 'Issued')
                ->with('course')
                ->latest('issued_at')
                ->take(2)
                ->get()
            : collect();

        // 5. Recent Activity
        $recentActivity = $user
            ? LessonProgress::query()
                ->where('user_id', $user->id)
                ->with(['lesson.module.course'])
                ->latest('last_accessed_at')
                ->take(4)
                ->get()
            : collect();

        return view('livewire.dashboard.student-dashboard', [
            'userName' => $user?->name ?? 'Student',
            'academyName' => $academy?->name ?? 'SEO Academy',
            'academyTagline' => $academy?->setting('tagline') ?? 'Digital Marketing',
            'proficiencyLabel' => $studentProfile?->proficiency_label ?? 'Not Assessed',
            'proficiencyBadgeColor' => $studentProfile?->proficiency_badge_color ?? 'gray',
            'totalEnrollmentsCount' => $totalEnrollmentsCount,
            'activeCoursesCount' => $activeCoursesCount,
            'completedCoursesCount' => $completedCoursesCount,
            'completedLessonsCount' => $completedLessonsCount,
            'allEnrolledLessonsCount' => $allEnrolledLessonsCount,
            'continueLearning' => $continueLearning,
            'filteredCourses' => $filteredCourses,
            'upcomingAssignments' => $upcomingAssignments,
            'recentCertificates' => $recentCertificates,
            'recentActivity' => $recentActivity,
            'hasAnyEnrollments' => $totalEnrollmentsCount > 0,
        ]);
    }
}
