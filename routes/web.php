<?php

use App\Domain\Academies\Models\Academy;
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
use App\Models\User;
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

if (app()->environment('local', 'development', 'testing')) {
    Route::get('/dev/login-as/{role}', function (string $role) {
        $email = request()->query('email');
        $query = User::query();
        if ($email) {
            $query->where('email', $email);
        } else {
            $query->role($role);
        }
        $user = $query->firstOrFail();
        Auth::login($user);
        request()->session()->regenerate();
        $academy = Academy::first();
        if ($academy) {
            session([TenantContext::SESSION_KEY => $academy->id]);
        }
        $redirectTo = match ($role) {
            'admin' => route('admin.dashboard'),
            'trainer' => route('trainer.dashboard'),
            'support_staff' => route('support.dashboard'),
            default => route('student.dashboard'),
        };

        return redirect()->intended($redirectTo);
    })->name('dev.login-as');
}

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
            $sampleRows = $studentService->csvTemplateSampleRows();

            $handle = fopen('php://temp', 'r+');
            // Write UTF-8 BOM
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, $headers);
            foreach ($sampleRows as $row) {
                fputcsv($handle, array_map([$studentService, 'sanitizeCsvCell'], $row));
            }
            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="students-import-template.csv"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        })->name('admin.students.csv-template');

        Route::get('/admin/students/export', function (Request $request, TenantContext $tenantContext, StudentManagementService $studentService) {
            $academy = $tenantContext->getAcademy();
            if ($academy === null) {
                abort(404, 'No active academy selected.');
            }

            if ($request->user()->cannot('export', [StudentProfile::class, $academy])) {
                abort(403);
            }

            $filters = $request->only(['search', 'status', 'proficiency', 'level', 'batch', 'course_id']);

            return $studentService->exportCsv($academy, $filters, $request->user());
        })->name('admin.students.export');

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
        ->middleware(['auth', 'academy.context', 'academy.access', 'role:student'])
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

// Zero-Terminal Installation Wizard (guarded by installed.lock and EnsureNotInstalled)
Route::middleware(EnsureNotInstalled::class)
    ->prefix('install')
    ->name('install.')
    ->group(function () {
        Route::get('/', [InstallController::class, 'index'])->name('index');
        Route::post('/database', [InstallController::class, 'store'])->name('store');
        Route::post('/test-connection', [InstallController::class, 'testConnection'])->name('test');
        Route::post('/reset', [InstallController::class, 'reset'])->name('reset');

        // Step 1: Server Requirements
        Route::get('/step/requirements', [InstallController::class, 'showRequirements'])->name('step.requirements');
        Route::post('/step/requirements', [InstallController::class, 'storeRequirements'])->name('step.requirements.store');

        // Step 2: Composer Dependencies
        Route::get('/step/dependencies', [InstallController::class, 'showDependencies'])->name('step.dependencies');
        Route::post('/step/dependencies', [InstallController::class, 'storeDependencies'])->name('step.dependencies.store');

        // Step 3: Environment Setup
        Route::get('/step/environment', [InstallController::class, 'showEnvironment'])->name('step.environment');
        Route::post('/step/environment', [InstallController::class, 'storeEnvironment'])->name('step.environment.store');

        // Step 4: Database Selection & Configuration
        Route::get('/step/database', [InstallController::class, 'showDatabase'])->name('step.database');
        Route::post('/step/database', [InstallController::class, 'storeDatabase'])->name('step.database.store');

        // Step 5: Database Initialization & Migrations
        Route::get('/step/migrations', [InstallController::class, 'showMigrations'])->name('step.migrations');
        Route::post('/step/migrations', [InstallController::class, 'storeMigrations'])->name('step.migrations.store');

        // Step 6: Filesystem Storage Setup
        Route::get('/step/storage', [InstallController::class, 'showStorage'])->name('step.storage');
        Route::post('/step/storage', [InstallController::class, 'storeStorage'])->name('step.storage.store');

        // Step 7: Academy Configuration
        Route::get('/step/academy', [InstallController::class, 'showAcademy'])->name('step.academy');
        Route::post('/step/academy', [InstallController::class, 'storeAcademy'])->name('step.academy.store');

        // Step 8: Administrator Creation
        Route::get('/step/admin', [InstallController::class, 'showAdmin'])->name('step.admin');
        Route::post('/step/admin', [InstallController::class, 'storeAdmin'])->name('step.admin.store');

        // Step 9: Finalization
        Route::get('/step/finalization', [InstallController::class, 'showFinalization'])->name('step.finalization');
        Route::post('/step/finalization', [InstallController::class, 'storeFinalization'])->name('step.finalization.store');

        // Completion & Diagnostics
        Route::get('/complete', [InstallController::class, 'complete'])->name('complete');
        Route::get('/diagnostics', [InstallController::class, 'diagnostics'])->name('diagnostics');
        Route::get('/diagnostics/download', [InstallController::class, 'downloadDiagnostics'])->name('diagnostics.download');
    });
