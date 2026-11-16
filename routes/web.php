<?php

use App\Domain\Students\Models\StudentProfile;
use App\Domain\Students\Services\StudentManagementService;
use App\Domain\Tenants\Services\TenantContext;
use App\Http\Controllers\AssignmentAttachmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BackupDownloadController;
use App\Http\Controllers\CertificateDownloadController;
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\CourseCatalogueController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\LearningProgressController;
use App\Http\Controllers\LessonPreviewController;
use App\Http\Controllers\PrivateMediaController;
use App\Http\Middleware\EnsureNotInstalled;
use App\Livewire\Admin\AcademySettings;
use App\Livewire\Admin\AssessmentGrading;
use App\Livewire\Admin\AssessmentsIndex;
use App\Livewire\Admin\AssignmentGradingView;
use App\Livewire\Admin\AssignmentsIndex;
use App\Livewire\Admin\AssignmentSubmissionsIndex;
use App\Livewire\Admin\AuditLogsIndex;
use App\Livewire\Admin\BackupsIndex;
use App\Livewire\Admin\CertificatesIndex as AdminCertificatesIndex;
use App\Livewire\Admin\CoursesIndex;
use App\Livewire\Admin\CourseStructureEditor;
use App\Livewire\Admin\EnrollmentsIndex;
use App\Livewire\Admin\FileManagementIndex;
use App\Livewire\Admin\MembersIndex;
use App\Livewire\Admin\QuestionBankManager;
use App\Livewire\Admin\StudentsIndex;
use App\Livewire\Admin\SystemHealthDashboard;
use App\Livewire\Admin\SystemSettingsIndex;
use App\Livewire\Admin\TrainersIndex;
use App\Livewire\Catalogue\PublicCoursesIndex;
use App\Livewire\Dashboard\AdminDashboard;
use App\Livewire\Dashboard\StudentDashboard;
use App\Livewire\Dashboard\SupportDashboard;
use App\Livewire\Dashboard\TrainerDashboard;
use App\Livewire\Grading\GradebookMatrix;
use App\Livewire\Notifications\NotificationCenter;
use App\Livewire\Notifications\NotificationPreferences;
use App\Livewire\Profile\ProfileManager;
use App\Livewire\Student\AcademicTranscript;
use App\Livewire\Student\AssessmentPlayer;
use App\Livewire\Student\AssignmentWorkspace;
use App\Livewire\Student\ClassroomPlayer;
use App\Livewire\Student\StudentAssignmentsIndex;
use App\Livewire\Student\StudentCertificatesIndex;
use App\Livewire\Student\StudentCoursesIndex;
use App\Livewire\Support\SupportTicketDetail;
use App\Livewire\Support\SupportTicketsIndex;
use App\Livewire\Trainer\TrainerCoursesIndex;
use App\Livewire\Trainer\TrainerStudentsIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/courses', PublicCoursesIndex::class)
    ->middleware('academy.context')
    ->name('courses.public.index');

Route::get('/courses/{slug}', [CourseCatalogueController::class, 'show'])
    ->middleware('academy.context')
    ->name('courses.public.show');

Route::get('/certificates/verify/{code}', [CertificateVerificationController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('certificates.verify');

Route::get('/verify/certificate/{code}', [CertificateVerificationController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('certificates.verify.alternate');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::get('/media/{media}', [PrivateMediaController::class, 'show'])
    ->middleware('auth')
    ->name('media.show');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        $user = Auth::user();

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return redirect()->route('admin.dashboard');
        }

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('trainer')) {
            return redirect()->route('trainer.dashboard');
        }

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('support_staff')) {
            return redirect()->route('support.dashboard');
        }

        return redirect()->route('student.dashboard');
    })->name('dashboard');

    Route::get('/admin/dashboard', AdminDashboard::class)
        ->middleware('role:admin')
        ->name('admin.dashboard');

    Route::get('/admin/academy/settings', AcademySettings::class)
        ->middleware(['auth', 'academy.context', 'academy.access', 'role:admin'])
        ->name('admin.academy.settings');

    Route::middleware(['auth', 'academy.context', 'academy.access', 'role:admin'])->group(function () {
        Route::get('/admin/students', StudentsIndex::class)->name('admin.students.index');
        Route::get('/admin/students/csv-template', function (Request $request, TenantContext $tenantContext, StudentManagementService $studentService) {
            $academy = $tenantContext->getAcademy();
            if ($academy === null) {
                abort(404, 'No active academy selected.');
            }

            if ($request->user()->cannot('export', [StudentProfile::class, $academy])) {
                abort(403);
            }

            $headers = $studentService->csvTemplateHeaders();
            $sampleRow = [
                $studentService->sanitizeCsvCell('Jane Doe'),
                $studentService->sanitizeCsvCell('jane@example.com'),
                $studentService->sanitizeCsvCell('+15550100'),
                $studentService->sanitizeCsvCell(''),
                $studentService->sanitizeCsvCell('Cohort 2026-A'),
                $studentService->sanitizeCsvCell('Beginner'),
                $studentService->sanitizeCsvCell(now()->toDateString()),
                $studentService->sanitizeCsvCell('Imported student'),
            ];

            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, $headers);
            fputcsv($handle, $sampleRow);
            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="students-import-template.csv"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        })->name('admin.students.csv-template');

        Route::get('/admin/trainers', TrainersIndex::class)->name('admin.trainers.index');
        Route::get('/admin/members', MembersIndex::class)->name('admin.members.index');
        Route::get('/admin/users', MembersIndex::class)->name('admin.users.index');
        Route::get('/admin/courses', CoursesIndex::class)->name('admin.courses.index');
        Route::get('/admin/courses/{course}/structure', CourseStructureEditor::class)->name('admin.courses.structure');
        Route::get('/admin/courses/{course}/gradebook', GradebookMatrix::class)->name('admin.courses.gradebook');
        Route::get('/admin/assessments', AssessmentsIndex::class)->name('admin.assessments.index');
        Route::get('/admin/assessments/{assessment}/grading', AssessmentGrading::class)->name('admin.assessments.grading');
        Route::get('/admin/assignments', AssignmentsIndex::class)->name('admin.assignments.index');
        Route::get('/admin/assignments/{assignment}/submissions', AssignmentSubmissionsIndex::class)->name('admin.assignments.submissions');
        Route::get('/admin/assignments/{assignment}/submissions/{submission}/grade', AssignmentGradingView::class)->name('admin.assignments.grade');
        Route::get('/admin/question-banks', QuestionBankManager::class)->name('admin.question-banks.index');
        Route::get('/admin/enrollments', EnrollmentsIndex::class)->name('admin.enrollments.index');
        Route::get('/admin/certificates', AdminCertificatesIndex::class)->name('admin.certificates.index');
        Route::get('/admin/files', FileManagementIndex::class)->name('admin.files.index');
        Route::get('/admin/audit-logs', AuditLogsIndex::class)->name('admin.audit-logs.index');
        Route::get('/admin/system/health', SystemHealthDashboard::class)->name('admin.system.health');
        Route::get('/admin/system/settings', SystemSettingsIndex::class)->name('admin.system.settings');
        Route::get('/admin/system/backups', BackupsIndex::class)->name('admin.system.backups');
        Route::get('/admin/system/backups/{backup}/download', [BackupDownloadController::class, 'download'])->name('admin.backups.download');
    });

    Route::get('/admin/courses/{course}/preview', [CourseCatalogueController::class, 'preview'])
        ->middleware(['auth', 'academy.context', 'academy.access', 'role:admin|trainer'])
        ->name('admin.courses.preview');

    Route::get('/courses/{course}/lessons/{lesson}/preview', [LessonPreviewController::class, 'show'])
        ->middleware(['auth', 'academy.context', 'academy.access'])
        ->name('lessons.preview');

    Route::get('/trainer/dashboard', TrainerDashboard::class)
        ->middleware('role:trainer')
        ->name('trainer.dashboard');

    Route::get('/trainer/courses', TrainerCoursesIndex::class)
        ->middleware(['auth', 'academy.context', 'academy.access', 'role:trainer'])
        ->name('trainer.courses.index');

    Route::get('/trainer/courses/{course}/structure', CourseStructureEditor::class)
        ->middleware(['auth', 'academy.context', 'academy.access', 'role:trainer'])
        ->name('trainer.courses.structure');

    Route::get('/trainer/courses/{course}/gradebook', GradebookMatrix::class)
        ->middleware(['auth', 'academy.context', 'academy.access', 'role:trainer'])
        ->name('trainer.courses.gradebook');

    Route::get('/trainer/assessments', AssessmentsIndex::class)
        ->middleware(['auth', 'academy.context', 'academy.access', 'role:trainer'])
        ->name('trainer.assessments.index');

    Route::get('/trainer/assessments/{assessment}/grading', AssessmentGrading::class)
        ->middleware(['auth', 'academy.context', 'academy.access', 'role:trainer'])
        ->name('trainer.assessments.grading');

    Route::get('/trainer/assignments', AssignmentsIndex::class)
        ->middleware(['auth', 'academy.context', 'academy.access', 'role:trainer'])
        ->name('trainer.assignments.index');

    Route::get('/trainer/assignments/{assignment}/submissions', AssignmentSubmissionsIndex::class)
        ->middleware(['auth', 'academy.context', 'academy.access', 'role:trainer'])
        ->name('trainer.assignments.submissions');

    Route::get('/trainer/assignments/{assignment}/submissions/{submission}/grade', AssignmentGradingView::class)
        ->middleware(['auth', 'academy.context', 'academy.access', 'role:trainer'])
        ->name('trainer.assignments.grade');

    Route::get('/trainer/question-banks', QuestionBankManager::class)
        ->middleware(['auth', 'academy.context', 'academy.access', 'role:trainer'])
        ->name('trainer.question-banks.index');

    Route::get('/trainer/students', TrainerStudentsIndex::class)
        ->middleware(['auth', 'academy.context', 'academy.access', 'role:trainer'])
        ->name('trainer.students.index');

    Route::get('/support/dashboard', SupportDashboard::class)
        ->middleware('role:support_staff')
        ->name('support.dashboard');

    Route::get('/support/tickets', SupportTicketsIndex::class)
        ->middleware(['auth', 'academy.context', 'academy.access', 'role:support_staff|admin'])
        ->name('support.tickets.index');

    Route::get('/support/tickets/{ticket}', SupportTicketDetail::class)
        ->middleware(['auth', 'academy.context', 'academy.access', 'role:support_staff|admin'])
        ->name('support.tickets.show');

    Route::get('/student/dashboard', StudentDashboard::class)
        ->middleware('role:student')
        ->name('student.dashboard');

    Route::get('/student/courses', StudentCoursesIndex::class)
        ->middleware(['auth', 'academy.context', 'academy.access', 'role:student'])
        ->name('student.courses.index');

    Route::get('/student/certificates', StudentCertificatesIndex::class)
        ->middleware(['auth', 'academy.context', 'academy.access', 'role:student'])
        ->name('student.certificates.index');

    Route::get('/student/assignments', StudentAssignmentsIndex::class)
        ->middleware(['auth', 'academy.context', 'academy.access', 'role:student'])
        ->name('student.assignments.index');

    Route::get('/student/courses/{course}/assignments/{assignment}', AssignmentWorkspace::class)
        ->middleware(['auth', 'academy.context', 'academy.access'])
        ->name('student.courses.assignments.show');

    Route::get('/assignments/submissions/{submission}/attachments/{attachment}/download', [AssignmentAttachmentController::class, 'download'])
        ->middleware(['auth', 'academy.context', 'academy.access'])
        ->name('assignments.attachments.download');

    Route::get('/certificates/{certificate}/download', [CertificateDownloadController::class, 'download'])
        ->middleware(['auth', 'academy.context', 'academy.access'])
        ->name('certificates.download');

    Route::get('/student/courses/{course}/learn/{lesson?}', ClassroomPlayer::class)
        ->middleware(['auth', 'academy.context', 'academy.access'])
        ->name('student.courses.learn');

    Route::get('/student/courses/{course}/assessments/{assessment}', AssessmentPlayer::class)
        ->middleware(['auth', 'academy.context', 'academy.access'])
        ->name('student.courses.assessments.show');

    Route::post('/student/courses/{course}/lessons/{lesson}/activity', [LearningProgressController::class, 'recordActivity'])
        ->middleware(['auth', 'academy.context', 'academy.access'])
        ->name('student.courses.lessons.activity');

    Route::post('/student/courses/{course}/lessons/{lesson}/complete', [LearningProgressController::class, 'completeLesson'])
        ->middleware(['auth', 'academy.context', 'academy.access'])
        ->name('student.courses.lessons.complete');

    Route::get('/student/courses/{course}/progress', [LearningProgressController::class, 'progress'])
        ->middleware(['auth', 'academy.context', 'academy.access'])
        ->name('student.courses.progress');

    Route::get('/student/courses/{course}/resume', [LearningProgressController::class, 'resume'])
        ->middleware(['auth', 'academy.context', 'academy.access'])
        ->name('student.courses.resume');

    Route::get('/student/courses/{course}/transcript', AcademicTranscript::class)
        ->middleware(['auth', 'academy.context', 'academy.access'])
        ->name('student.courses.transcript');

    Route::get('/notifications', NotificationCenter::class)
        ->middleware(['academy.context', 'academy.access'])
        ->name('notifications.index');

    Route::get('/notifications/preferences', NotificationPreferences::class)
        ->middleware(['academy.context', 'academy.access'])
        ->name('notifications.preferences');

    Route::get('/profile', ProfileManager::class)
        ->middleware(['academy.context', 'academy.access'])
        ->name('profile');
});

Route::get('/invitations/{token}/accept', [InvitationAcceptanceController::class, 'show'])
    ->name('invitations.accept');
Route::post('/invitations/{token}/accept', [InvitationAcceptanceController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('invitations.accept.store');

// Installation Wizard (guarded by installed.lock)
Route::middleware(EnsureNotInstalled::class)
    ->prefix('install')
    ->name('install.')
    ->group(function () {
        Route::get('/', [InstallController::class, 'index'])->name('index');
        Route::post('/test-connection', [InstallController::class, 'testConnection'])->name('test');
        Route::post('/database', [InstallController::class, 'store'])->name('store');
        Route::get('/complete', [InstallController::class, 'complete'])->name('complete');
    });
