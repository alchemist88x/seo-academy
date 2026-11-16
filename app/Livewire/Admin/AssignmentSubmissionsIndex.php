<?php

namespace App\Livewire\Admin;

use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Assignment Submissions Queue')]
class AssignmentSubmissionsIndex extends Component
{
    use WithPagination;

    public Assignment $assignment;

    public string $search = '';

    public string $statusFilter = 'all';

    public function mount(Assignment $assignment, TenantContext $tenantContext): void
    {
        $user = Auth::user();
        if (! $user instanceof User || (! $user->hasRole('admin') && ! $user->hasRole('trainer'))) {
            abort(403, 'Unauthorized.');
        }

        if ($user->hasRole('trainer') && ! $user->hasRole('admin')) {
            $isAssigned = $assignment->course?->trainers()->where('users.id', $user->id)->exists();
            if (! $isAssigned) {
                abort(403, 'You are not assigned to this course.');
            }
        }

        $this->assignment = $assignment;
    }

    public function render()
    {
        $query = AssignmentSubmission::query()
            ->where('assignment_id', $this->assignment->id)
            ->with(['user.studentProfile', 'latestEvaluation.evaluator', 'attachments']);

        if ($this->search !== '') {
            $query->whereHas('user', function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%')
                    ->orWhereHas('studentProfile', function ($sp) {
                        $sp->where('student_code', 'like', '%'.$this->search.'%');
                    });
            });
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $submissions = $query->orderByDesc('submitted_at')->paginate(20);

        return view('livewire.admin.assignment-submissions-index', [
            'submissions' => $submissions,
        ]);
    }
}
