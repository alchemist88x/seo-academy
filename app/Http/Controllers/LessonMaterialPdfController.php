<?php

namespace App\Http\Controllers;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Learning\Services\CourseNavigationService;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use App\Services\Pdf\Exceptions\OversizedLessonContentException;
use App\Services\Pdf\LessonMaterialPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LessonMaterialPdfController extends Controller
{
    public function __construct(
        protected LessonMaterialPdfService $pdfService,
        protected CourseNavigationService $navigationService
    ) {}

    /**
     * Download or view the branded one-page PDF notes for a course lesson.
     */
    public function download(Request $request, Course $course, Lesson $lesson): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->is_active) {
            abort(403, 'User account is inactive or unauthenticated.');
        }

        // Validate course academy context
        $academy = app(TenantContext::class)->getAcademy() ?? $course->academy ?? Academy::current();
        if ($academy === null || (string) $course->academy_id !== (string) $academy->getKey()) {
            abort(404, 'Course not found in the active academy context.');
        }

        // Validate lesson belongs to course
        if ($lesson->module === null || (string) $lesson->module->course_id !== (string) $course->id) {
            abort(404, 'Requested lesson does not belong to this course.');
        }

        // Validate lesson visibility and status
        $lessonStatus = strtolower((string) $lesson->status);
        if ($lessonStatus === 'inactive' || $lesson->visibility === 'hidden') {
            abort(404, 'Requested lesson is unavailable.');
        }

        // Authorize user access to this course and lesson
        $this->authorizeAccess($user, $course, $lesson);

        try {
            $pdfContent = $this->pdfService->renderPdf($lesson, $course, $academy, true);
        } catch (OversizedLessonContentException $e) {
            return response(
                $e->getMessage(),
                422,
                ['Content-Type' => 'text/plain']
            );
        }

        $filename = $this->pdfService->generateFileName($course, $lesson);
        $disposition = $request->has('inline') ? 'inline' : 'attachment';

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, $filename),
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Authorize user access based on role, enrollment status, and course prerequisites.
     */
    protected function authorizeAccess(User $user, Course $course, Lesson $lesson): void
    {
        if ($user->hasRole('admin')) {
            return;
        }

        if ($user->hasRole('trainer')) {
            $isAssigned = $course->trainers()->where('users.id', $user->id)->exists();
            if (! $isAssigned) {
                abort(403, 'Trainer is not assigned to this course.');
            }

            return;
        }

        if ($user->hasRole('student')) {
            $studentProfile = $user->studentProfile;
            if (! $studentProfile) {
                abort(403, 'No student profile exists for this user account.');
            }

            $enrollment = $studentProfile->enrollments()
                ->where('course_id', $course->id)
                ->first();

            if (! $enrollment) {
                abort(403, 'You are not enrolled in this course.');
            }

            if (! $enrollment->allowsCourseAccess()) {
                abort(403, 'Your enrollment in this course is not active or has expired/been suspended.');
            }

            if (! $course->isPublished()) {
                abort(403, 'This course is currently not published.');
            }

            if (! $this->navigationService->isLessonUnlocked($lesson, $user, $course)) {
                abort(403, 'This lesson is locked until prerequisite lessons are completed.');
            }

            return;
        }

        abort(403, 'You do not have authorization to access this lesson material.');
    }
}
