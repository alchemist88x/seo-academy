<?php

namespace App\Livewire\Student;

use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('My Assignments')]
class StudentAssignmentsIndex extends Component
{
    public string $statusFilter = 'all';

    public function mount(TenantContext $tenantContext): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->hasRole('student')) {
            abort(403, 'Unauthorized.');
        }
    }

    public function render()
    {
        $user = Auth::user();
        $studentProfile = $user->studentProfile;

        // Get student's enrolled courses with active or valid access
        $enrolledCourseIds = Enrollment::query()
            ->where('student_profile_id', $studentProfile?->id)
            ->allowsCourseAccess()
            ->pluck('course_id');

        $query = Assignment::query()
            ->whereIn('course_id', $enrolledCourseIds)
            ->published()
            ->with(['course']);

        $assignments = $query->orderBy('due_date')->get();

        // Attach student's latest submission to each assignment
        $assignmentsData = $assignments->map(function (Assignment $assignment) use ($user) {
            $latestSubmission = AssignmentSubmission::query()
                ->where('assignment_id', $assignment->id)
                ->where('user_id', $user->id)
                ->orderByDesc('version_number')
                ->with(['latestEvaluation'])
                ->first();

            $eval = $latestSubmission?->latestEvaluation;

            return [
                'assignment' => $assignment,
                'submission' => $latestSubmission,
                'evaluation' => $eval,
                'status' => $latestSubmission ? $latestSubmission->status : 'unsubmitted',
            ];
        });

        if ($this->statusFilter !== 'all') {
            $assignmentsData = $assignmentsData->filter(function ($item) {
                if ($this->statusFilter === 'pending') {
                    return in_array($item['status'], ['unsubmitted', 'revision_requested'], true);
                }
                if ($this->statusFilter === 'submitted') {
                    return in_array($item['status'], ['submitted', 'resubmitted', 'under_review'], true);
                }
                if ($this->statusFilter === 'graded') {
                    return $item['status'] === 'graded';
                }

                return true;
            });
        }

        return view('livewire.student.student-assignments-index', [
            'assignmentsData' => $assignmentsData,
        ]);
    }
}
