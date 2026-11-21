<?php

namespace App\Livewire\Admin\Analytics;

use App\Domain\Analytics\Services\StudentAnalyticsService;
use App\Domain\Students\Models\StudentProfile;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Student Learning Performance')]
class StudentAnalyticsView extends Component
{
    public ?StudentProfile $studentProfile = null;

    public function mount(?StudentProfile $student = null): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        // Student self-service route
        if ($student === null || ! $student->exists) {
            if ($user->hasRole('student')) {
                $student = $user->studentProfile;
                if (! $student) {
                    abort(404, 'No student profile associated with this account.');
                }
            } else {
                abort(404, 'Student profile not found.');
            }
        }

        $this->studentProfile = $student;
    }

    public function render(StudentAnalyticsService $analyticsService)
    {
        $viewer = Auth::user();

        try {
            $data = $analyticsService->getStudentAnalytics($this->studentProfile, $viewer);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        }

        return view('livewire.admin.analytics.student-analytics-view', [
            'data' => $data,
            'isSelf' => $viewer->id === $this->studentProfile->user_id,
        ]);
    }
}
