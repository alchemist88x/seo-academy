<?php

namespace App\Livewire\Student;

use App\Domain\Certificates\Services\CertificateIssuanceService;
use App\Domain\Courses\Models\Course;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Services\CourseNavigationService;
use App\Domain\Learning\Services\LearningProgressService;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.classroom')]
#[Title('Course Classroom')]
class ClassroomPlayer extends Component
{
    public Course $course;

    public ?Lesson $currentLesson = null;

    public ?Enrollment $enrollment = null;

    public bool $isCompletedEnrollment = false;

    public ?string $certificateNumber = null;

    public bool $isLessonCompleted = false;

    public float $lastPositionSeconds = 0.0;

    public string $activeTab = 'overview';

    public array $navigation = [
        'previous' => null,
        'next' => null,
        'next_unlocked' => false,
    ];

    public array $curriculum = [
        'modules' => [],
        'total_lessons' => 0,
        'completed_lessons' => 0,
        'progress_percent' => 0,
    ];

    public bool $mobileCurriculumOpen = false;

    public function mount(
        Course $course,
        ?Lesson $lesson,
        TenantContext $tenantContext,
        CourseNavigationService $navigationService,
        LearningProgressService $progressService
    ): void {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->is_active) {
            abort(403, 'User account is inactive or unauthenticated.');
        }

        $academy = $tenantContext->getAcademy() ?? $course->academy ?? Academy::current();
        if ($academy === null || (string) $course->academy_id !== (string) $academy->getKey()) {
            abort(404, 'Course not found in the active academy.');
        }

        $this->authorizeAccess($user, $course);

        // Resolve or validate lesson
        if ($lesson === null || ! $lesson->exists) {
            $lesson = $navigationService->resolveResumeLesson($course, $user);
        } else {
            $this->validateLessonAccess($lesson, $course, $user, $navigationService);
        }

        $this->course = $course;
        $this->currentLesson = $lesson;

        if ($this->currentLesson) {
            $this->currentLesson->load(['media', 'materials.media', 'prerequisites']);

            if ($this->enrollment) {
                $progressService->updateResumeLocation($this->enrollment, $this->currentLesson, $user);
                $this->isLessonCompleted = $progressService->isLessonCompleted($this->enrollment, $this->currentLesson);

                $progress = LessonProgress::query()
                    ->where('enrollment_id', $this->enrollment->id)
                    ->where('lesson_id', $this->currentLesson->id)
                    ->first();
                $this->lastPositionSeconds = $progress ? (float) $progress->last_position_seconds : 0.0;
            }
        }

        $this->loadNavigationAndCurriculum($navigationService);

        if ($this->enrollment && $this->isCompletedEnrollment) {
            $cert = app(CertificateIssuanceService::class)->getCertificateForEnrollment($this->enrollment);
            $this->certificateNumber = $cert?->certificate_number;
        }
    }

    public function completeLesson(
        LearningProgressService $progressService,
        CourseNavigationService $navigationService
    ): void {
        $user = Auth::user();
        if (! $user instanceof User || ! $this->enrollment || ! $this->currentLesson) {
            return;
        }

        $progressService->completeLesson($this->enrollment, $this->currentLesson, $user);
        $this->enrollment->refresh();
        $this->isCompletedEnrollment = $this->enrollment->isCompleted();
        $this->isLessonCompleted = true;

        if ($this->isCompletedEnrollment) {
            $cert = app(CertificateIssuanceService::class)->getCertificateForEnrollment($this->enrollment);
            $this->certificateNumber = $cert?->certificate_number;
        }

        $this->loadNavigationAndCurriculum($navigationService);
    }

    public function recordHeartbeat(
        ?float $positionSeconds = null,
        ?LearningProgressService $progressService = null
    ): void {
        $user = Auth::user();
        if (! $user instanceof User || ! $this->enrollment || ! $this->currentLesson) {
            return;
        }

        $progressService ??= app(LearningProgressService::class);
        $progressService->recordHeartbeat($this->enrollment, $this->currentLesson, $user, $positionSeconds);
    }

    public function toggleMobileCurriculum(): void
    {
        $this->mobileCurriculumOpen = ! $this->mobileCurriculumOpen;
    }

    public function setActiveTab(string $tab): void
    {
        if (in_array($tab, ['overview', 'materials', 'notes'], true)) {
            $this->activeTab = $tab;
        }
    }

    public function render()
    {
        return view('livewire.student.classroom-player');
    }

    private function authorizeAccess(User $user, Course $course): void
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

            $this->enrollment = $studentProfile->enrollments()
                ->where('course_id', $course->id)
                ->first();

            if (! $this->enrollment) {
                abort(403, 'You are not enrolled in this course.');
            }

            if (! $this->enrollment->allowsCourseAccess()) {
                abort(403, 'Your enrollment in this course is not active or has expired/been suspended.');
            }

            $this->isCompletedEnrollment = $this->enrollment->isCompleted();

            if (! $course->isPublished()) {
                abort(403, 'This course is currently not published.');
            }

            return;
        }

        abort(403, 'You do not have authorization to access the classroom.');
    }

    private function validateLessonAccess(
        Lesson $lesson,
        Course $course,
        User $user,
        CourseNavigationService $navigationService
    ): void {
        if ($lesson->module === null || (string) $lesson->module->course_id !== (string) $course->id) {
            abort(404, 'Requested lesson does not belong to this course.');
        }

        $lessonStatus = strtolower((string) $lesson->status);
        if ($lessonStatus === 'inactive' || $lesson->visibility === 'hidden') {
            abort(404, 'Requested lesson is unavailable.');
        }

        if (! $navigationService->isLessonUnlocked($lesson, $user, $course)) {
            abort(403, 'This lesson is locked until prerequisite lessons are completed.');
        }
    }

    private function loadNavigationAndCurriculum(CourseNavigationService $navigationService): void
    {
        $user = Auth::user();

        if ($this->currentLesson) {
            $this->navigation = $navigationService->getPreviousAndNext($this->course, $this->currentLesson, $user);
        }

        $this->curriculum = $navigationService->getCurriculumStructure($this->course, $user, $this->currentLesson);
    }
}
