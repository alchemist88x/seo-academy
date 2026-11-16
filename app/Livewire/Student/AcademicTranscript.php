<?php

namespace App\Livewire\Student;

use App\Domain\Courses\Models\Course;
use App\Domain\Grading\Services\TranscriptService;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Academic Transcript')]
class AcademicTranscript extends Component
{
    public Course $course;

    public ?Enrollment $enrollment = null;

    public ?array $transcript = null;

    public ?string $errorMessage = null;

    public function mount(Course $course, TenantContext $tenantContext, TranscriptService $transcriptService): void
    {
        $this->course = $course;
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        // Find enrollment for the student or course
        if ($user->hasRole('student')) {
            $studentProfile = $user->studentProfile;
            $this->enrollment = Enrollment::where('course_id', $course->id)
                ->where('student_profile_id', $studentProfile?->id)
                ->first();
        } else {
            // Staff viewing: grab first enrollment or fail gracefully
            $this->enrollment = Enrollment::where('course_id', $course->id)->first();
        }

        if (! $this->enrollment) {
            $this->errorMessage = 'No enrollment record found for this course.';

            return;
        }

        try {
            $this->transcript = $transcriptService->getStudentTranscript($this->enrollment, $user);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.student.academic-transcript');
    }
}
