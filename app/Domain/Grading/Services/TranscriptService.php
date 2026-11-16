<?php

namespace App\Domain\Grading\Services;

use App\Domain\Grading\Models\EnrollmentFinalGrade;
use App\Domain\Grading\Models\EnrollmentGradeLifecycleEvent;
use App\Domain\Students\Models\Enrollment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class TranscriptService
{
    /**
     * Retrieve the official student academic transcript for an enrollment.
     * Enforces privacy and role-based visibility:
     * - Students can only view their own transcript.
     * - Students NEVER see unpublished draft grades (only official published snapshots).
     * - Trainers may view transcripts for courses they are assigned to.
     * - Administrators may view all transcripts.
     *
     * @throws AuthorizationException
     */
    public function getStudentTranscript(Enrollment $enrollment, User $viewer): array
    {
        $this->assertCanViewTranscript($viewer, $enrollment);

        $course = $enrollment->course;
        $studentProfile = $enrollment->studentProfile;
        $studentUser = $studentProfile?->user;

        $isCompleted = $enrollment->status === Enrollment::STATUS_COMPLETED
            || $enrollment->completed_at !== null;

        $currentGrade = $enrollment->currentGrade;
        $isPublished = $currentGrade !== null && $currentGrade->status === 'published' && $currentGrade->published_final_grade_id !== null;
        $publishedGrade = $isPublished ? $currentGrade->publishedGrade : null;

        $isStaff = ($viewer->role === 'admin' || $viewer->hasRole('admin'))
            || ($viewer->role === 'trainer' || $viewer->hasRole('trainer'));

        // Student privacy rule: Students only see published final grades.
        // Unpublished draft working calculations are visible only to staff.
        $gradeData = [
            'is_published' => $isPublished,
            'status' => $currentGrade?->status ?? 'in_progress',
            'final_score' => $publishedGrade ? (string) $publishedGrade->final_score : null,
            'letter_grade' => $publishedGrade?->letter_grade,
            'is_passing' => $publishedGrade ? (bool) $publishedGrade->is_passing : false,
            'academic_status' => $publishedGrade?->academic_status ?? ($isCompleted ? 'pending_evaluation' : 'in_progress'),
            'version' => $publishedGrade?->version,
            'published_at' => $publishedGrade?->published_at?->toIso8601String(),
            'breakdown' => $publishedGrade?->calculation_snapshot ?? null,
        ];

        // For staff, optionally include working draft info if not published
        $draftData = null;
        if ($isStaff && ! $isPublished && $enrollment->gradeDraft) {
            $draft = $enrollment->gradeDraft;
            $draftData = [
                'current_score' => (string) $draft->current_score,
                'current_letter_grade' => $draft->current_letter_grade,
                'is_passing' => (bool) $draft->is_passing,
                'academic_status' => $draft->academic_status,
                'missing_mandatory' => (bool) $draft->missing_mandatory_assessments,
                'last_calculated_at' => $draft->last_calculated_at?->toIso8601String(),
            ];
        }

        // Certificate eligibility strictly requires BOTH course completion AND a published passing grade (Policy 1 & 2)
        $isCertificateEligible = app(GradebookCalculationService::class)->isEligibleForCertificate($enrollment);

        $transcript = [
            'enrollment_id' => $enrollment->id,
            'student' => [
                'id' => $studentUser?->id,
                'name' => $studentUser?->name ?? 'Student',
                'email' => $studentUser?->email,
                'enrolled_at' => $enrollment->enrolled_at?->toDateString(),
            ],
            'course' => [
                'id' => $course?->id,
                'title' => $course?->title,
                'slug' => $course?->slug,
                'difficulty' => $course?->difficulty,
                'duration_months' => $course?->duration_months,
            ],
            // Policy 1: Course completion is independent of academic passing
            'completion' => [
                'is_completed' => $isCompleted,
                'status' => $enrollment->status,
                'completed_at' => $enrollment->completed_at?->toIso8601String(),
            ],
            'grade' => $gradeData,
            'draft' => $draftData,
            'is_certificate_eligible' => $isCertificateEligible,
            'generated_at' => now()->toIso8601String(),
        ];

        if ($isStaff) {
            $transcript['historical_versions'] = EnrollmentFinalGrade::where('enrollment_id', $enrollment->id)
                ->orderBy('version')
                ->get()
                ->map(fn (EnrollmentFinalGrade $g) => [
                    'version' => $g->version,
                    'final_score' => (string) $g->final_score,
                    'letter_grade' => $g->letter_grade,
                    'is_passing' => (bool) $g->is_passing,
                    'academic_status' => $g->academic_status,
                    'published_at' => $g->published_at?->toIso8601String(),
                    'supersedes_grade_id' => $g->supersedes_grade_id,
                ])
                ->all();

            $transcript['lifecycle_events'] = EnrollmentGradeLifecycleEvent::where('enrollment_id', $enrollment->id)
                ->orderBy('created_at')
                ->get()
                ->map(fn (EnrollmentGradeLifecycleEvent $e) => [
                    'event_type' => $e->event_type,
                    'payload' => $e->payload,
                    'created_at' => $e->created_at?->toIso8601String(),
                ])
                ->all();
        }

        return $transcript;
    }

    /**
     * Verify authorization to view the transcript.
     *
     * @throws AuthorizationException
     */
    public function assertCanViewTranscript(User $viewer, Enrollment $enrollment): void
    {
        if (! $viewer->is_active) {
            throw new AuthorizationException('Inactive users cannot view transcripts.');
        }

        if ($viewer->role === 'admin' || $viewer->hasRole('admin')) {
            return;
        }

        if ($viewer->role === 'trainer' || $viewer->hasRole('trainer')) {
            $course = $enrollment->course;
            $isAssigned = $course && $course->trainers()
                ->where('users.id', $viewer->getKey())
                ->exists();

            if ($isAssigned) {
                return;
            }

            throw new AuthorizationException('Unauthorized: Trainers may only view transcripts for their assigned courses.');
        }

        if ($viewer->role === 'student' || $viewer->hasRole('student')) {
            $studentProfile = $enrollment->studentProfile;
            $isOwner = $studentProfile && (string) $studentProfile->user_id === (string) $viewer->getKey();

            if ($isOwner) {
                return;
            }

            throw new AuthorizationException('Unauthorized: Students can only view their own academic transcripts.');
        }

        throw new AuthorizationException('Unauthorized to view this academic transcript.');
    }
}
