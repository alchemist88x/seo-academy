<?php

namespace App\Http\Controllers;

use App\Domain\Courses\Models\Course;
use App\Domain\Learning\Services\LearningProgressService;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class LearningProgressController extends Controller
{
    public function __construct(
        protected LearningProgressService $progressService,
        protected TenantContext $tenantContext
    ) {}

    /**
     * Record active participation (heartbeat) for a lesson.
     */
    public function recordActivity(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $enrollment = $this->resolveEnrollment($user, $course);
        if (! $enrollment) {
            return response()->json(['message' => 'Active enrollment not found for this course.'], 403);
        }

        $validated = $request->validate([
            'position_seconds' => ['nullable', 'numeric', 'min:0'],
        ]);

        $positionSeconds = isset($validated['position_seconds']) ? (float) $validated['position_seconds'] : null;

        try {
            $progress = $this->progressService->recordActivity($enrollment, $lesson, $user, $positionSeconds);

            return response()->json([
                'success' => true,
                'progress' => [
                    'id' => $progress->id,
                    'enrollment_id' => $progress->enrollment_id,
                    'lesson_id' => $progress->lesson_id,
                    'status' => $progress->status,
                    'last_position_seconds' => (float) $progress->last_position_seconds,
                    'progress_percent' => $progress->progress_percent,
                    'last_accessed_at' => $progress->last_accessed_at?->toIso8601String(),
                ],
            ]);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Explicitly complete a lesson.
     */
    public function completeLesson(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $enrollment = $this->resolveEnrollment($user, $course);
        if (! $enrollment) {
            return response()->json(['message' => 'Active enrollment not found for this course.'], 403);
        }

        try {
            $progress = $this->progressService->completeLesson($enrollment, $lesson, $user);
            $summary = $this->progressService->getCourseProgressSummary($enrollment);
            $completionStatus = $this->progressService->getCompletionStatus($enrollment->fresh());

            return response()->json([
                'success' => true,
                'message' => 'Lesson marked as completed.',
                'progress' => [
                    'id' => $progress->id,
                    'enrollment_id' => $progress->enrollment_id,
                    'lesson_id' => $progress->lesson_id,
                    'status' => $progress->status,
                    'completed_at' => $progress->completed_at?->toIso8601String(),
                    'progress_percent' => $progress->progress_percent,
                ],
                'summary' => [
                    'total_lessons' => $summary['total_lessons'],
                    'completed_lessons' => $summary['completed_lessons'],
                    'incomplete_lessons' => $summary['incomplete_lessons'],
                    'completion_percentage' => $summary['completion_percentage'],
                    'next_incomplete_lesson_id' => $summary['next_incomplete_lesson']?->id,
                ],
                'course_completion' => [
                    'is_completed' => $completionStatus['is_completed'],
                    'completed_at' => $completionStatus['completed_at']?->toIso8601String(),
                    'certificate_number' => $completionStatus['certificate']?->certificate_number,
                ],
            ]);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Retrieve course progress summary for the authenticated student.
     */
    public function progress(Request $request, Course $course): JsonResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $enrollment = $this->resolveEnrollment($user, $course);
        if (! $enrollment) {
            return response()->json(['message' => 'Active enrollment not found for this course.'], 403);
        }

        $summary = $this->progressService->getCourseProgressSummary($enrollment);
        $progressList = $this->progressService->getProgressForEnrollment($enrollment);
        $completionStatus = $this->progressService->getCompletionStatus($enrollment);

        return response()->json([
            'success' => true,
            'summary' => [
                'total_lessons' => $summary['total_lessons'],
                'completed_lessons' => $summary['completed_lessons'],
                'incomplete_lessons' => $summary['incomplete_lessons'],
                'completion_percentage' => $summary['completion_percentage'],
                'last_active_lesson_id' => $summary['last_active_lesson']?->id,
                'last_active_at' => $summary['last_active_at']?->toIso8601String(),
                'next_incomplete_lesson_id' => $summary['next_incomplete_lesson']?->id,
            ],
            'completion' => [
                'is_completed' => $completionStatus['is_completed'],
                'completed_at' => $completionStatus['completed_at']?->toIso8601String(),
                'is_eligible' => $completionStatus['is_eligible'],
                'certificate_number' => $completionStatus['certificate']?->certificate_number,
                'modules' => $completionStatus['modules'],
            ],
            'lessons' => $progressList->map(fn ($p) => [
                'lesson_id' => $p->lesson_id,
                'status' => $p->status,
                'progress_percent' => $p->progress_percent,
                'completed_at' => $p->completed_at?->toIso8601String(),
                'last_accessed_at' => $p->last_accessed_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * Retrieve the resume lesson for the authenticated student.
     */
    public function resume(Request $request, Course $course): JsonResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $enrollment = $this->resolveEnrollment($user, $course);
        if (! $enrollment) {
            return response()->json(['message' => 'Active enrollment not found for this course.'], 403);
        }

        $resumeLesson = $this->progressService->getResumeLesson($enrollment, $user);

        return response()->json([
            'success' => true,
            'resume_lesson' => $resumeLesson ? [
                'id' => $resumeLesson->id,
                'title' => $resumeLesson->title,
                'type' => $resumeLesson->type,
                'url' => route('student.courses.learn', [$course, $resumeLesson->id]),
            ] : null,
        ]);
    }

    private function resolveEnrollment(User $user, Course $course)
    {
        $profile = $user->studentProfile;
        if (! $profile) {
            return null;
        }

        return $profile->enrollments()
            ->where('course_id', $course->id)
            ->first();
    }
}
