<?php

namespace App\Http\Controllers;

use App\Domain\Reports\Services\ReportingService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportExportController extends Controller
{
    public function __construct(
        protected ReportingService $reportingService,
        protected TenantContext $tenantContext
    ) {}

    /**
     * Handle CSV download request.
     */
    public function exportCsv(Request $request)
    {
        $user = Auth::user();
        if (! $user || (! $user->hasRole('admin') && ! $user->hasRole('trainer'))) {
            abort(403, 'Unauthorized.');
        }

        $academy = $this->tenantContext->getAcademy()
            ?? $user->academies()->where('academies.is_active', true)->first();

        if ($academy === null) {
            abort(404, 'Active academy not found.');
        }

        $reportType = $request->query('report_type', ReportingService::REPORT_COURSE_PERFORMANCE);
        $filters = $request->only(['start_date', 'end_date', 'course_id', 'student_id', 'trainer_id', 'status']);

        return $this->reportingService->exportCsv($academy, $reportType, $filters, $user);
    }

    /**
     * Handle PDF download request.
     */
    public function exportPdf(Request $request)
    {
        $user = Auth::user();
        if (! $user || (! $user->hasRole('admin') && ! $user->hasRole('trainer'))) {
            abort(403, 'Unauthorized.');
        }

        $academy = $this->tenantContext->getAcademy()
            ?? $user->academies()->where('academies.is_active', true)->first();

        if ($academy === null) {
            abort(404, 'Active academy not found.');
        }

        $reportType = $request->query('report_type', ReportingService::REPORT_COURSE_PERFORMANCE);
        $filters = $request->only(['start_date', 'end_date', 'course_id', 'student_id', 'trainer_id', 'status']);

        return $this->reportingService->exportPdf($academy, $reportType, $filters, $user);
    }
}
