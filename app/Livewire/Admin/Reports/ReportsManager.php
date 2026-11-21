<?php

namespace App\Livewire\Admin\Reports;

use App\Domain\Courses\Models\Course;
use App\Domain\Reports\Services\ReportingService;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Reports & Analytics Exports')]
class ReportsManager extends Component
{
    use WithPagination;

    #[Url]
    public string $reportType = ReportingService::REPORT_COURSE_PERFORMANCE;

    #[Url]
    public ?string $startDate = null;

    #[Url]
    public ?string $endDate = null;

    #[Url]
    public ?string $courseId = null;

    #[Url]
    public ?string $studentId = null;

    #[Url]
    public ?string $status = null;

    public int $perPage = 15;

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user || (! $user->hasRole('admin') && ! $user->hasRole('trainer'))) {
            abort(403, 'Unauthorized.');
        }

        if (empty($this->startDate)) {
            $this->startDate = Carbon::now()->subDays(30)->toDateString();
        }
        if (empty($this->endDate)) {
            $this->endDate = Carbon::now()->toDateString();
        }
    }

    public function updatedReportType(): void
    {
        $this->resetPage();
    }

    public function updatedCourseId(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->courseId = null;
        $this->studentId = null;
        $this->status = null;
        $this->startDate = Carbon::now()->subDays(30)->toDateString();
        $this->endDate = Carbon::now()->toDateString();
        $this->resetPage();
    }

    public function render(TenantContext $tenantContext, ReportingService $reportingService)
    {
        $user = Auth::user();
        $academy = $tenantContext->getAcademy()
            ?? $user->academies()->where('academies.is_active', true)->first();

        if ($academy === null) {
            abort(404, 'Academy not found.');
        }

        $coursesQuery = Course::forAcademy($academy)->orderBy('title');
        if ($user->hasRole('trainer') && ! $user->hasRole('admin')) {
            $coursesQuery->whereHas('trainers', fn ($q) => $q->where('users.id', $user->id));
        }
        $courses = $coursesQuery->get();

        $students = StudentProfile::query()
            ->where('academy_id', $academy->id)
            ->with('user')
            ->take(100)
            ->get();

        $filters = [
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'course_id' => $this->courseId,
            'student_id' => $this->studentId,
            'status' => $this->status,
        ];

        $reportData = $reportingService->getReportData($academy, $this->reportType, $filters, $user);

        // Paginate in-memory array for clean, snappy presentation
        $allRows = $reportData['rows'];
        $totalRows = count($allRows);
        $currentPage = $this->getPage();
        $offset = ($currentPage - 1) * $this->perPage;
        $paginatedRows = array_slice($allRows, $offset, $this->perPage);

        $totalPages = (int) ceil($totalRows / $this->perPage);

        return view('livewire.admin.reports.reports-manager', [
            'reportData' => $reportData,
            'paginatedRows' => $paginatedRows,
            'totalRows' => $totalRows,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'courses' => $courses,
            'students' => $students,
            'exportQuery' => http_build_query([
                'report_type' => $this->reportType,
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'course_id' => $this->courseId,
                'student_id' => $this->studentId,
                'status' => $this->status,
            ]),
        ]);
    }
}
