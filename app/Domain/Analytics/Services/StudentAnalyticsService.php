<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Courses\Models\Course;
use App\Domain\Grading\Services\TranscriptService;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class StudentAnalyticsService
{
    public function __construct(
        protected TranscriptService $transcriptService
    ) {}

    /**
     * Retrieve student performance analytics with strict role-based access control.
     *
     * @throws AuthorizationException
     */
    public function getStudentAnalytics(StudentProfile $studentProfile, User $viewer): array
    {
        $this->authorizeViewer($studentProfile, $viewer);

        $studentUser = $studentProfile->user;
        $isAdmin = $viewer->hasRole('admin');
        $isTrainer = $viewer->hasRole('trainer') && ! $isAdmin;

        // Determine accessible courses for the viewer
        $accessibleCourseIds = null;
        if ($isTrainer) {
            $accessibleCourseIds = Course::query()
                ->whereHas('trainers', fn (Builder $q) => $q->where('users.id', $viewer->id))
                ->pluck('id')
                ->all();
        }

        // 1. Enrollment history
        $enrollmentQuery = Enrollment::query()
            ->where('student_profile_id', $studentProfile->id)
            ->with(['course', 'currentGrade.publishedGrade', 'currentGrade.draftGrade']);

        if ($accessibleCourseIds !== null) {
            $enrollmentQuery->whereIn('course_id', $accessibleCourseIds);
        }

        $enrollments = $enrollmentQuery->latest('enrolled_at')->get();

        $activeCoursesCount = 0;
        $completedCoursesCount = 0;
        $coursesData = [];

        foreach ($enrollments as $enrollment) {
            $course = $enrollment->course;
            if (! $course) {
                continue;
            }

            if ($enrollment->status === Enrollment::STATUS_ACTIVE) {
                $activeCoursesCount++;
            } elseif ($enrollment->status === Enrollment::STATUS_COMPLETED) {
                $completedCoursesCount++;
            }

            // Total lessons in course
            $totalLessons = $course->lessons()
                ->where(function ($q) {
                    $q->whereNull('lessons.status')->orWhere('lessons.status', 'Active')->orWhere('lessons.status', 'active');
                })
                ->count();

            $completedLessons = LessonProgress::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('status', 'completed')
                ->count();

            $progressPercent = $totalLessons > 0
                ? min(100.0, round(($completedLessons / $totalLessons) * 100, 1))
                : 0.0;

            if ($enrollment->status === Enrollment::STATUS_COMPLETED) {
                $progressPercent = 100.0;
            }

            // Grade snapshot
            $publishedGrade = $enrollment->currentGrade?->publishedGrade;
            $gradeInfo = $publishedGrade ? [
                'final_score' => (string) $publishedGrade->final_score,
                'letter_grade' => $publishedGrade->letter_grade,
                'is_passing' => (bool) $publishedGrade->is_passing,
            ] : null;

            $coursesData[] = [
                'enrollment_id' => $enrollment->id,
                'course_id' => $course->id,
                'course_title' => $course->title,
                'course_slug' => $course->slug,
                'status' => $enrollment->status,
                'enrolled_at' => $enrollment->enrolled_at?->toDateString(),
                'completed_at' => $enrollment->completed_at?->toDateString(),
                'last_active_at' => $enrollment->last_active_at?->toIso8601String(),
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedLessons,
                'progress_percent' => $progressPercent,
                'grade' => $gradeInfo,
            ];
        }

        // 2. Assessment attempts
        $attemptQuery = AssessmentAttempt::query()
            ->where('user_id', $studentUser->id);

        if ($accessibleCourseIds !== null) {
            $attemptQuery->whereHas('enrollment', fn ($q) => $q->whereIn('course_id', $accessibleCourseIds));
        }

        $assessmentAttempts = $attemptQuery
            ->with(['enrollment.course'])
            ->latest('created_at')
            ->take(20)
            ->get()
            ->map(fn (AssessmentAttempt $a) => [
                'attempt_id' => $a->id,
                'course_title' => $a->enrollment?->course?->title ?? 'N/A',
                'status' => $a->status,
                'started_at' => $a->started_at?->toIso8601String(),
                'submitted_at' => $a->submitted_at?->toIso8601String(),
                'score_percentage' => (float) $a->score_percentage,
                'passed' => (bool) $a->passed,
                'earned_points' => (float) $a->earned_points,
                'total_points' => (float) $a->total_points,
            ]);

        // 3. Assignment submissions
        $submissionQuery = AssignmentSubmission::query()
            ->where('user_id', $studentUser->id);

        if ($accessibleCourseIds !== null) {
            $submissionQuery->whereHas('assignment', fn ($q) => $q->whereIn('course_id', $accessibleCourseIds));
        }

        $assignmentSubmissions = $submissionQuery
            ->with(['assignment.course', 'latestEvaluation'])
            ->latest('submitted_at')
            ->take(20)
            ->get()
            ->map(fn (AssignmentSubmission $s) => [
                'submission_id' => $s->id,
                'assignment_title' => $s->assignment?->title ?? 'N/A',
                'course_title' => $s->assignment?->course?->title ?? 'N/A',
                'status' => $s->status,
                'grade' => $s->grade !== null ? (float) $s->grade : null,
                'is_late' => (bool) $s->is_late,
                'submitted_at' => $s->submitted_at?->toIso8601String(),
                'feedback' => $s->latestEvaluation?->feedback,
            ]);

        // 4. Certificates
        $certificateQuery = Certificate::query()
            ->where('user_id', $studentUser->id)
            ->where('status', Certificate::STATUS_ISSUED);

        if ($accessibleCourseIds !== null) {
            $certificateQuery->whereIn('course_id', $accessibleCourseIds);
        }

        $certificates = $certificateQuery
            ->with('course')
            ->latest('issued_at')
            ->get()
            ->map(fn (Certificate $c) => [
                'certificate_id' => $c->id,
                'course_title' => $c->course?->title ?? 'N/A',
                'certificate_number' => $c->certificate_number,
                'verification_code' => $c->verification_code,
                'issued_at' => $c->issued_at?->toDateString(),
            ]);

        // 5. Activity timeline
        $timeline = $this->buildActivityTimeline($studentUser, $accessibleCourseIds);

        return [
            'student' => [
                'id' => $studentProfile->id,
                'user_id' => $studentUser->id,
                'name' => $studentUser->name,
                'email' => $studentUser->email,
                'student_code' => $studentProfile->student_code,
                'batch' => $studentProfile->batch,
                'level' => $studentProfile->level,
                'active' => (bool) $studentProfile->active,
                'created_at' => $studentProfile->created_at?->toDateString(),
            ],
            'summary' => [
                'total_enrolled_courses' => count($coursesData),
                'active_courses' => $activeCoursesCount,
                'completed_courses' => $completedCoursesCount,
                'certificates_count' => $certificates->count(),
            ],
            'courses' => $coursesData,
            'assessment_attempts' => $assessmentAttempts->all(),
            'assignment_submissions' => $assignmentSubmissions->all(),
            'certificates' => $certificates->all(),
            'activity_timeline' => $timeline,
        ];
    }

    /**
     * Authorize whether the viewer can access this student's analytics.
     *
     * @throws AuthorizationException
     */
    protected function authorizeViewer(StudentProfile $studentProfile, User $viewer): void
    {
        // 1. Admins have academy-wide access
        if ($viewer->hasRole('admin')) {
            return;
        }

        // 2. Student can only access their own profile
        if ($viewer->hasRole('student') && ! $viewer->hasRole('trainer')) {
            if ($studentProfile->user_id !== $viewer->id) {
                throw new AuthorizationException('Students are not authorized to view other students’ analytics.');
            }

            return;
        }

        // 3. Trainers can only view students enrolled in their assigned courses
        if ($viewer->hasRole('trainer')) {
            $hasCommonCourse = Enrollment::query()
                ->where('student_profile_id', $studentProfile->id)
                ->whereHas('course.trainers', function (Builder $q) use ($viewer) {
                    $q->where('users.id', $viewer->id);
                })
                ->exists();

            if (! $hasCommonCourse) {
                throw new AuthorizationException('Trainers may only view analytics for students enrolled in their assigned courses.');
            }

            return;
        }

        throw new AuthorizationException('Unauthorized access to student analytics.');
    }

    /**
     * Build unified chronological learning activity timeline.
     *
     * @param  array<int, string>|null  $accessibleCourseIds
     * @return array<int, array{type: string, title: string, description: string, timestamp: string}>
     */
    protected function buildActivityTimeline(User $studentUser, ?array $accessibleCourseIds = null): array
    {
        $events = collect();

        // Lesson progress
        $progressQuery = LessonProgress::query()
            ->where('user_id', $studentUser->id)
            ->whereNotNull('last_accessed_at')
            ->with(['lesson', 'enrollment.course']);

        if ($accessibleCourseIds !== null) {
            $progressQuery->whereHas('enrollment', fn ($q) => $q->whereIn('course_id', $accessibleCourseIds));
        }

        $progressItems = $progressQuery->latest('last_accessed_at')->take(15)->get();
        foreach ($progressItems as $item) {
            $isComplete = $item->status === 'completed';
            $events->push([
                'type' => $isComplete ? 'lesson_completed' : 'lesson_accessed',
                'title' => $isComplete ? 'Lesson Completed' : 'Studied Lesson',
                'description' => ($item->lesson?->title ?? 'Lesson').' in '.($item->enrollment?->course?->title ?? 'Course'),
                'timestamp' => $item->last_accessed_at->toIso8601String(),
                'sort_time' => $item->last_accessed_at->timestamp,
            ]);
        }

        // Assessments
        $attemptQuery = AssessmentAttempt::query()
            ->where('user_id', $studentUser->id)
            ->whereNotNull('submitted_at')
            ->with('enrollment.course');

        if ($accessibleCourseIds !== null) {
            $attemptQuery->whereHas('enrollment', fn ($q) => $q->whereIn('course_id', $accessibleCourseIds));
        }

        $attemptItems = $attemptQuery->latest('submitted_at')->take(10)->get();
        foreach ($attemptItems as $att) {
            $events->push([
                'type' => 'assessment_submitted',
                'title' => 'Assessment Submitted',
                'description' => 'Scored '.$att->score_percentage.'% ('.($att->passed ? 'Passed' : 'Failed').') in '.($att->enrollment?->course?->title ?? 'Course'),
                'timestamp' => $att->submitted_at->toIso8601String(),
                'sort_time' => $att->submitted_at->timestamp,
            ]);
        }

        // Assignments
        $submissionQuery = AssignmentSubmission::query()
            ->where('user_id', $studentUser->id)
            ->whereNotNull('submitted_at')
            ->with('assignment.course');

        if ($accessibleCourseIds !== null) {
            $submissionQuery->whereHas('assignment', fn ($q) => $q->whereIn('course_id', $accessibleCourseIds));
        }

        $submissionItems = $submissionQuery->latest('submitted_at')->take(10)->get();
        foreach ($submissionItems as $sub) {
            $events->push([
                'type' => 'assignment_submitted',
                'title' => 'Assignment Submitted',
                'description' => ($sub->assignment?->title ?? 'Assignment').' ('.($sub->is_late ? 'Late' : 'On Time').')',
                'timestamp' => $sub->submitted_at->toIso8601String(),
                'sort_time' => $sub->submitted_at->timestamp,
            ]);
        }

        return $events->sortByDesc('sort_time')->take(20)->values()->all();
    }
}
