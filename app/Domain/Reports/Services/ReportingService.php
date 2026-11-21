<?php

namespace App\Domain\Reports\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Courses\Models\Course;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Platform\Services\AuditLogService;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Trainers\Models\TrainerProfile;
use App\Models\User;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportingService
{
    public const REPORT_COURSE_PERFORMANCE = 'course_performance';

    public const REPORT_STUDENT_PROGRESS = 'student_progress';

    public const REPORT_ENROLLMENTS = 'enrollments';

    public const REPORT_TRAINER_PERFORMANCE = 'trainer_performance';

    public const REPORT_ASSESSMENT_ASSIGNMENT = 'assessment_assignment';

    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Retrieve tabular report dataset with filters and role-based scoping.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     report_type: string,
     *     title: string,
     *     generated_at: string,
     *     headers: array<int, string>,
     *     rows: array<int, array<int, string|int|float>>,
     *     total_records: int
     * }
     *
     * @throws AuthorizationException
     */
    public function getReportData(Academy $academy, string $reportType, array $filters, User $viewer): array
    {
        $this->authorizeViewer($viewer);

        $isAdmin = $viewer->hasRole('admin');
        $isTrainer = $viewer->hasRole('trainer') && ! $isAdmin;

        $trainerCourseIds = null;
        if ($isTrainer) {
            $trainerCourseIds = Course::query()
                ->whereHas('trainers', fn (Builder $q) => $q->where('users.id', $viewer->id))
                ->pluck('id')
                ->all();
        }

        return match ($reportType) {
            self::REPORT_COURSE_PERFORMANCE => $this->getCoursePerformanceReport($academy, $filters, $trainerCourseIds),
            self::REPORT_STUDENT_PROGRESS => $this->getStudentProgressReport($academy, $filters, $trainerCourseIds),
            self::REPORT_ENROLLMENTS => $this->getEnrollmentsReport($academy, $filters, $trainerCourseIds),
            self::REPORT_TRAINER_PERFORMANCE => $this->getTrainerPerformanceReport($academy, $filters, $viewer),
            self::REPORT_ASSESSMENT_ASSIGNMENT => $this->getAssessmentAssignmentReport($academy, $filters, $trainerCourseIds),
            default => throw new \InvalidArgumentException("Unknown report type: {$reportType}"),
        };
    }

    /**
     * Authorize user to access the reporting system.
     *
     * @throws AuthorizationException
     */
    protected function authorizeViewer(User $viewer): void
    {
        if (! $viewer->hasRole('admin') && ! $viewer->hasRole('trainer')) {
            throw new AuthorizationException('Access denied. Only administrators and trainers may generate reports.');
        }
    }

    /**
     * Course Performance Report.
     */
    protected function getCoursePerformanceReport(Academy $academy, array $filters, ?array $trainerCourseIds): array
    {
        $query = Course::forAcademy($academy)
            ->withCount([
                'enrollments',
                'enrollments as active_count' => function ($q) {
                    $q->whereIn('enrollments.status', [Enrollment::STATUS_ACTIVE, 'active', 'Active']);
                },
                'enrollments as completed_count' => function ($q) {
                    $q->whereIn('enrollments.status', [Enrollment::STATUS_COMPLETED, 'completed', 'Completed']);
                },
                'lessons' => function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('lessons.status')->orWhere('lessons.status', 'Active')->orWhere('lessons.status', 'active');
                    });
                },
            ]);

        if ($trainerCourseIds !== null) {
            $query->whereIn('id', $trainerCourseIds);
        }

        if (! empty($filters['course_id'])) {
            $query->where('id', $filters['course_id']);
        }

        $courses = $query->orderBy('title')->get();
        $rows = [];

        foreach ($courses as $c) {
            $enrolled = $c->enrollments_count;
            $active = $c->active_count;
            $completed = $c->completed_count;
            $compRate = $enrolled > 0 ? round(($completed / $enrolled) * 100, 1).'%' : '0.0%';

            $certs = Certificate::query()
                ->where('course_id', $c->id)
                ->where('status', Certificate::STATUS_ISSUED)
                ->count();

            $rows[] = [
                $c->title,
                $c->status,
                $c->lessons_count,
                $enrolled,
                $active,
                $completed,
                $compRate,
                $certs,
            ];
        }

        return [
            'report_type' => self::REPORT_COURSE_PERFORMANCE,
            'title' => 'Course Performance Report',
            'generated_at' => Carbon::now()->toDateTimeString(),
            'headers' => [
                'Course Title',
                'Status',
                'Total Lessons',
                'Total Enrolled',
                'Active',
                'Completed',
                'Completion Rate',
                'Certificates Issued',
            ],
            'rows' => $rows,
            'total_records' => count($rows),
        ];
    }

    /**
     * Student Progress Report.
     */
    protected function getStudentProgressReport(Academy $academy, array $filters, ?array $trainerCourseIds): array
    {
        $query = Enrollment::query()
            ->where('enrollments.academy_id', $academy->id)
            ->with(['studentProfile.user', 'course']);

        if ($trainerCourseIds !== null) {
            $query->whereIn('enrollments.course_id', $trainerCourseIds);
        }

        if (! empty($filters['course_id'])) {
            $query->where('enrollments.course_id', $filters['course_id']);
        }

        if (! empty($filters['student_id'])) {
            $query->where('enrollments.student_profile_id', $filters['student_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('enrollments.status', strtolower($filters['status']));
        }

        if (! empty($filters['start_date'])) {
            $query->where('enrollments.enrolled_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if (! empty($filters['end_date'])) {
            $query->where('enrollments.enrolled_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        $enrollments = $query->latest('enrollments.enrolled_at')->get();
        $rows = [];

        foreach ($enrollments as $e) {
            $user = $e->studentProfile?->user;
            $course = $e->course;
            $totalLessons = $course ? $course->lessons()->count() : 0;
            $completedLessons = LessonProgress::query()
                ->where('enrollment_id', $e->id)
                ->where('status', 'completed')
                ->count();

            $progress = $totalLessons > 0 ? min(100.0, round(($completedLessons / $totalLessons) * 100, 1)).'%' : '0.0%';
            if ($e->status === Enrollment::STATUS_COMPLETED) {
                $progress = '100.0%';
            }

            $rows[] = [
                $user?->name ?? 'Unknown Student',
                $e->studentProfile?->student_code ?? 'N/A',
                $user?->email ?? 'N/A',
                $course?->title ?? 'N/A',
                ucfirst((string) $e->status),
                $e->enrolled_at?->toDateString() ?? 'N/A',
                $e->completed_at?->toDateString() ?? '-',
                $progress,
                $e->last_active_at?->toDateTimeString() ?? 'Never',
            ];
        }

        return [
            'report_type' => self::REPORT_STUDENT_PROGRESS,
            'title' => 'Student Progress Report',
            'generated_at' => Carbon::now()->toDateTimeString(),
            'headers' => [
                'Student Name',
                'Student Code',
                'Email',
                'Course',
                'Enrollment Status',
                'Enrolled At',
                'Completed At',
                'Progress',
                'Last Active',
            ],
            'rows' => $rows,
            'total_records' => count($rows),
        ];
    }

    /**
     * Enrollments Report.
     */
    protected function getEnrollmentsReport(Academy $academy, array $filters, ?array $trainerCourseIds): array
    {
        $query = Enrollment::query()
            ->where('enrollments.academy_id', $academy->id)
            ->with(['studentProfile.user', 'course', 'assignedBy']);

        if ($trainerCourseIds !== null) {
            $query->whereIn('enrollments.course_id', $trainerCourseIds);
        }

        if (! empty($filters['course_id'])) {
            $query->where('enrollments.course_id', $filters['course_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('enrollments.status', strtolower($filters['status']));
        }

        if (! empty($filters['start_date'])) {
            $query->where('enrollments.enrolled_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if (! empty($filters['end_date'])) {
            $query->where('enrollments.enrolled_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        $enrollments = $query->latest('enrollments.enrolled_at')->get();
        $rows = [];

        foreach ($enrollments as $e) {
            $user = $e->studentProfile?->user;
            $course = $e->course;

            $rows[] = [
                $e->id,
                $user?->name ?? 'N/A',
                $user?->email ?? 'N/A',
                $course?->title ?? 'N/A',
                ucfirst((string) $e->status),
                $e->enrolled_at?->toDateString() ?? 'N/A',
                $e->starts_at?->toDateString() ?? '-',
                $e->expires_at?->toDateString() ?? '-',
                $e->completed_at?->toDateString() ?? '-',
                $e->assignedBy?->name ?? 'System',
            ];
        }

        return [
            'report_type' => self::REPORT_ENROLLMENTS,
            'title' => 'Enrollment History Report',
            'generated_at' => Carbon::now()->toDateTimeString(),
            'headers' => [
                'Enrollment ID',
                'Student Name',
                'Email',
                'Course',
                'Status',
                'Enrolled At',
                'Starts At',
                'Expires At',
                'Completed At',
                'Assigned By',
            ],
            'rows' => $rows,
            'total_records' => count($rows),
        ];
    }

    /**
     * Trainer Performance Report.
     */
    protected function getTrainerPerformanceReport(Academy $academy, array $filters, User $viewer): array
    {
        $isAdmin = $viewer->hasRole('admin');

        $query = TrainerProfile::query()
            ->where('academy_id', $academy->id)
            ->with('user');

        if (! $isAdmin) {
            $query->where('user_id', $viewer->id);
        } elseif (! empty($filters['trainer_id'])) {
            $query->where('user_id', $filters['trainer_id']);
        }

        $trainers = $query->get();
        $rows = [];

        foreach ($trainers as $tp) {
            $tUser = $tp->user;
            if (! $tUser) {
                continue;
            }

            $courses = Course::query()
                ->forAcademy($academy)
                ->whereHas('trainers', fn ($q) => $q->where('users.id', $tUser->id))
                ->withCount([
                    'enrollments',
                    'enrollments as active_count' => function ($q) {
                        $q->whereIn('status', [Enrollment::STATUS_ACTIVE, 'active', 'Active']);
                    },
                    'enrollments as completed_count' => function ($q) {
                        $q->whereIn('status', [Enrollment::STATUS_COMPLETED, 'completed', 'Completed']);
                    },
                ])
                ->get();

            $cIds = $courses->pluck('id');
            $distinctStudents = $cIds->isNotEmpty()
                ? Enrollment::query()->whereIn('course_id', $cIds)->distinct('student_profile_id')->count('student_profile_id')
                : 0;

            $totalEnrolled = $courses->sum('enrollments_count');
            $completed = $courses->sum('completed_count');
            $compRate = $totalEnrolled > 0 ? round(($completed / $totalEnrolled) * 100, 1).'%' : '0.0%';

            $pendingEvals = $cIds->isNotEmpty()
                ? AssignmentSubmission::query()
                    ->whereHas('assignment', fn ($q) => $q->whereIn('course_id', $cIds))
                    ->whereIn('status', [AssignmentSubmission::STATUS_SUBMITTED, AssignmentSubmission::STATUS_RESUBMITTED])
                    ->count()
                : 0;

            $rows[] = [
                $tUser->name,
                $tUser->email,
                $tp->specialty ?? 'General',
                $courses->count(),
                $distinctStudents,
                $courses->sum('active_count'),
                $completed,
                $compRate,
                $pendingEvals,
            ];
        }

        return [
            'report_type' => self::REPORT_TRAINER_PERFORMANCE,
            'title' => 'Trainer Performance Report',
            'generated_at' => Carbon::now()->toDateTimeString(),
            'headers' => [
                'Trainer Name',
                'Email',
                'Specialty',
                'Assigned Courses',
                'Total Students',
                'Active Enrollments',
                'Completed Enrollments',
                'Completion Rate',
                'Pending Evaluations',
            ],
            'rows' => $rows,
            'total_records' => count($rows),
        ];
    }

    /**
     * Assessment & Assignment Results Report.
     */
    protected function getAssessmentAssignmentReport(Academy $academy, array $filters, ?array $trainerCourseIds): array
    {
        $rows = [];

        // 1. Assessment attempts
        $attemptQuery = AssessmentAttempt::query()
            ->where('assessment_attempts.academy_id', $academy->id)
            ->where('assessment_attempts.status', AssessmentAttempt::STATUS_EVALUATED)
            ->with(['enrollment.course', 'user']);

        if ($trainerCourseIds !== null) {
            $attemptQuery->whereHas('enrollment', fn ($q) => $q->whereIn('course_id', $trainerCourseIds));
        }

        if (! empty($filters['course_id'])) {
            $attemptQuery->whereHas('enrollment', fn ($q) => $q->where('course_id', $filters['course_id']));
        }

        if (! empty($filters['student_id'])) {
            $attemptQuery->whereHas('enrollment', fn ($q) => $q->where('student_profile_id', $filters['student_id']));
        }

        if (! empty($filters['start_date'])) {
            $attemptQuery->where('submitted_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if (! empty($filters['end_date'])) {
            $attemptQuery->where('submitted_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        $attempts = $attemptQuery->latest('submitted_at')->take(500)->get();
        foreach ($attempts as $att) {
            $rows[] = [
                $att->user?->name ?? 'Student',
                $att->enrollment?->course?->title ?? 'N/A',
                'Assessment Attempt #'.$att->attempt_number,
                'Assessment',
                $att->submitted_at?->toDateString() ?? 'N/A',
                $att->score_percentage.'%',
                ucfirst((string) $att->status),
                $att->passed ? 'Passed' : 'Failed',
            ];
        }

        // 2. Assignment submissions
        $subQuery = AssignmentSubmission::query()
            ->whereHas('enrollment', fn ($q) => $q->where('academy_id', $academy->id))
            ->with(['assignment.course', 'user', 'enrollment']);

        if ($trainerCourseIds !== null) {
            $subQuery->whereHas('assignment', fn ($q) => $q->whereIn('course_id', $trainerCourseIds));
        }

        if (! empty($filters['course_id'])) {
            $subQuery->whereHas('assignment', fn ($q) => $q->where('course_id', $filters['course_id']));
        }

        if (! empty($filters['student_id'])) {
            $subQuery->whereHas('enrollment', fn ($q) => $q->where('student_profile_id', $filters['student_id']));
        }

        if (! empty($filters['start_date'])) {
            $subQuery->where('submitted_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if (! empty($filters['end_date'])) {
            $subQuery->where('submitted_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        $submissions = $subQuery->latest('submitted_at')->take(500)->get();
        foreach ($submissions as $sub) {
            $gradeDisplay = $sub->grade !== null ? $sub->grade : 'Ungraded';
            $passStatus = $sub->grade !== null
                ? ($sub->grade >= ($sub->assignment?->passing_marks ?? 50) ? 'Passed' : 'Failed')
                : 'Pending';

            $rows[] = [
                $sub->user?->name ?? 'Student',
                $sub->assignment?->course?->title ?? 'N/A',
                $sub->assignment?->title ?? 'Assignment',
                'Assignment',
                $sub->submitted_at?->toDateString() ?? 'N/A',
                $gradeDisplay,
                ucfirst(str_replace('_', ' ', (string) $sub->status)),
                $passStatus,
            ];
        }

        return [
            'report_type' => self::REPORT_ASSESSMENT_ASSIGNMENT,
            'title' => 'Assessment & Assignment Results Report',
            'generated_at' => Carbon::now()->toDateTimeString(),
            'headers' => [
                'Student Name',
                'Course',
                'Activity Item',
                'Type',
                'Submitted At',
                'Score / Grade',
                'Status',
                'Result',
            ],
            'rows' => $rows,
            'total_records' => count($rows),
        ];
    }

    /**
     * Export report data as a safe UTF-8 CSV stream.
     */
    public function exportCsv(Academy $academy, string $reportType, array $filters, User $viewer): StreamedResponse
    {
        $reportData = $this->getReportData($academy, $reportType, $filters, $viewer);

        $this->logExportAudit($academy, $viewer, 'csv', $reportType, count($reportData['rows']));

        $filename = sprintf(
            '%s-%s.csv',
            str_replace('_', '-', $reportType),
            Carbon::now()->format('Y-m-d-His')
        );

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'X-Content-Type-Options' => 'nosniff',
        ];

        return new StreamedResponse(function () use ($reportData) {
            $handle = fopen('php://output', 'w');

            // Write UTF-8 BOM
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write column headers
            fputcsv($handle, $reportData['headers']);

            // Write sanitized data rows
            foreach ($reportData['rows'] as $row) {
                $sanitizedRow = array_map([$this, 'sanitizeCsvCell'], $row);
                fputcsv($handle, $sanitizedRow);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export report data as a professionally formatted PDF.
     */
    public function exportPdf(Academy $academy, string $reportType, array $filters, User $viewer): Response
    {
        $reportData = $this->getReportData($academy, $reportType, $filters, $viewer);

        $this->logExportAudit($academy, $viewer, 'pdf', $reportType, count($reportData['rows']));

        $html = view('reports.pdf-template', [
            'academy' => $academy,
            'reportData' => $reportData,
            'filters' => $filters,
            'generatedBy' => $viewer->name,
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = sprintf(
            '%s-%s.pdf',
            str_replace('_', '-', $reportType),
            Carbon::now()->format('Y-m-d-His')
        );

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Sanitize individual cell value against CSV formula injection.
     */
    public function sanitizeCsvCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $str = (string) $value;

        // Disarm Excel formula injection triggers
        if (preg_match('/^[=\-+@\t\r]/', $str)) {
            return "'".$str;
        }

        return $str;
    }

    /**
     * Record sensitive report export action in audit logs.
     */
    protected function logExportAudit(Academy $academy, User $actor, string $format, string $reportType, int $recordCount): void
    {
        try {
            $this->auditLogService->record(
                academy: $academy,
                actor: $actor,
                action: 'export.report',
                subjectType: 'Report',
                subjectId: $reportType,
                properties: [
                    'format' => $format,
                    'report_type' => $reportType,
                    'record_count' => $recordCount,
                    'timestamp' => Carbon::now()->toIso8601String(),
                ]
            );
        } catch (\Throwable) {
            // Non-blocking fallback if audit log table has issues
        }
    }
}
