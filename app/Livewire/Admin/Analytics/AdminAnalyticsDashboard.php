<?php

namespace App\Livewire\Admin\Analytics;

use App\Domain\Analytics\Services\LearningAnalyticsService;
use App\Domain\Courses\Models\Course;
use App\Domain\Tenants\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Learning Analytics & Insights')]
class AdminAnalyticsDashboard extends Component
{
    #[Url]
    public int|string $timeRange = 30;

    #[Url]
    public ?string $startDate = null;

    #[Url]
    public ?string $endDate = null;

    #[Url]
    public ?string $selectedCourseId = null;

    public bool $showAccessibleTables = false;

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasRole('admin')) {
            abort(403, 'Unauthorized. Administrator access required.');
        }

        if (empty($this->startDate) || empty($this->endDate)) {
            $this->applyTimeRangePreset((int) $this->timeRange);
        }
    }

    public function updatedTimeRange(int|string $value): void
    {
        if ($value !== 'custom') {
            $this->applyTimeRangePreset((int) $value);
        }
    }

    protected function applyTimeRangePreset(int $days): void
    {
        $this->endDate = Carbon::now()->toDateString();
        $this->startDate = Carbon::now()->subDays(max(1, $days - 1))->toDateString();
    }

    public function toggleAccessibleTables(): void
    {
        $this->showAccessibleTables = ! $this->showAccessibleTables;
    }

    public function render(TenantContext $tenantContext, LearningAnalyticsService $analyticsService)
    {
        $user = Auth::user();
        $academy = $tenantContext->getAcademy()
            ?? $user->academies()->where('academies.is_active', true)->first();

        if ($academy === null) {
            abort(404, 'No active academy found.');
        }

        $start = $this->startDate ? Carbon::parse($this->startDate) : null;
        $end = $this->endDate ? Carbon::parse($this->endDate) : null;
        $days = ($start && $end) ? max(1, (int) $start->diffInDays($end) + 1) : 30;

        $courses = Course::forAcademy($academy)->orderBy('title')->get();

        $overview = $analyticsService->getAcademyOverviewMetrics($academy, $start, $end);
        $enrollmentTrends = $analyticsService->getEnrollmentTrends($academy, $days, $this->selectedCourseId);
        $registrationTrends = $analyticsService->getRegistrationTrends($academy, $days);
        $dailyActiveStudents = $analyticsService->getDailyActiveStudents($academy, $days, $this->selectedCourseId);
        $lessonsCompletedTrends = $analyticsService->getLessonsCompletedTrends($academy, $days, $this->selectedCourseId);
        $statusDistribution = $analyticsService->getEnrollmentStatusDistribution($academy, $this->selectedCourseId);
        $courseRankings = $analyticsService->getCourseEngagementRanking($academy);
        $academicMetrics = $analyticsService->getAcademicPerformanceMetrics($academy, $this->selectedCourseId);
        $insights = $analyticsService->getOperationalInsights($academy);
        $avgProgress = $analyticsService->getAverageCourseProgress($academy, $this->selectedCourseId);

        return view('livewire.admin.analytics.admin-analytics-dashboard', [
            'academy' => $academy,
            'courses' => $courses,
            'overview' => $overview,
            'enrollmentTrends' => $enrollmentTrends,
            'registrationTrends' => $registrationTrends,
            'dailyActiveStudents' => $dailyActiveStudents,
            'lessonsCompletedTrends' => $lessonsCompletedTrends,
            'statusDistribution' => $statusDistribution,
            'courseRankings' => $courseRankings,
            'academicMetrics' => $academicMetrics,
            'insights' => $insights,
            'avgProgress' => $avgProgress,
        ]);
    }
}
