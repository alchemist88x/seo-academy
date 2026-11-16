<?php

namespace App\Livewire\Admin;

use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Courses\Models\Course;
use App\Domain\Questions\Models\QuestionBank;
use App\Domain\Questions\Services\QuestionBankService;
use App\Domain\Quizzes\Models\Quiz;
use App\Domain\Quizzes\Models\QuizQuestionPool;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Assessments Management')]
class AssessmentsIndex extends Component
{
    public string $search = '';

    public string $statusFilter = 'all';

    // New assessment modal fields
    public bool $showCreateModal = false;

    public string $newTitle = '';

    public string $newDescription = '';

    public string $newCourseId = '';

    public string $newType = 'quiz';

    public bool $newIsRequired = true;

    public int $newPassingPercentage = 70;

    public ?int $newAttemptLimit = null;

    public ?int $newTimeLimit = null;

    public bool $newRandomizeQuestions = false;

    public bool $newRandomizeOrder = false;

    // Pool Configuration Modal State
    public bool $showPoolModal = false;

    public ?string $managingQuizId = null;

    public ?Quiz $managingQuiz = null;

    public string $poolBankId = '';

    public int $poolQuestionCount = 5;

    public string $poolDifficulty = '';

    public string $poolCategory = '';

    public string $poolQuestionType = '';

    public ?int $poolPointsOverride = null;

    public ?string $poolErrorMessage = null;

    public ?string $poolSuccessMessage = null;

    public function mount(TenantContext $tenantContext): void
    {
        $user = Auth::user();
        if (! $user instanceof User || (! $user->hasRole('admin') && ! $user->hasRole('trainer'))) {
            abort(403, 'Unauthorized.');
        }
    }

    public function openCreateModal(): void
    {
        $this->reset([
            'newTitle', 'newDescription', 'newCourseId', 'newType',
            'newIsRequired', 'newPassingPercentage', 'newAttemptLimit', 'newTimeLimit',
            'newRandomizeQuestions', 'newRandomizeOrder',
        ]);
        $this->showCreateModal = true;
    }

    public function createAssessment(TenantContext $tenantContext): void
    {
        $this->validate([
            'newTitle' => 'required|string|max:255',
            'newCourseId' => 'required|exists:courses,id',
            'newPassingPercentage' => 'required|integer|min:1|max:100',
        ]);

        $user = Auth::user();
        $this->authorizeCourseAction($this->newCourseId, $user);

        $academy = $tenantContext->getAcademy();

        Quiz::create([
            'course_id' => $this->newCourseId,
            'title' => $this->newTitle,
            'description' => $this->newDescription,
            'type' => $this->newType,
            'is_required' => $this->newIsRequired,
            'passing_percentage' => $this->newPassingPercentage,
            'pass_mark' => $this->newPassingPercentage,
            'attempt_limit' => $this->newAttemptLimit,
            'time_limit_minutes' => $this->newTimeLimit,
            'status' => 'Active',
            'published_at' => now(),
            'randomize_questions' => $this->newRandomizeQuestions,
            'randomize_order' => $this->newRandomizeOrder,
            'question_source' => 'direct',
        ]);

        $this->showCreateModal = false;
    }

    public function openPoolsModal(string $quizId): void
    {
        $quiz = Quiz::with(['pools.questionBank', 'course'])->findOrFail($quizId);
        $user = Auth::user();
        $this->authorizeCourseAction($quiz->course_id, $user);

        $this->managingQuizId = $quiz->id;
        $this->managingQuiz = $quiz;
        $this->poolBankId = '';
        $this->poolQuestionCount = 5;
        $this->poolDifficulty = '';
        $this->poolCategory = '';
        $this->poolQuestionType = '';
        $this->poolPointsOverride = null;
        $this->poolErrorMessage = null;
        $this->poolSuccessMessage = null;
        $this->showPoolModal = true;
    }

    public function addPool(QuestionBankService $service): void
    {
        if (! $this->managingQuizId) {
            return;
        }

        $this->validate([
            'poolBankId' => 'required|exists:question_banks,id',
            'poolQuestionCount' => 'required|integer|min:1',
        ]);

        $quiz = Quiz::findOrFail($this->managingQuizId);
        $bank = QuestionBank::findOrFail($this->poolBankId);

        try {
            $this->poolErrorMessage = null;

            $service->addPoolToQuiz($quiz, $bank, $this->poolQuestionCount, [
                'difficulty' => ! empty($this->poolDifficulty) ? $this->poolDifficulty : null,
                'category' => ! empty($this->poolCategory) ? $this->poolCategory : null,
                'question_type' => ! empty($this->poolQuestionType) ? $this->poolQuestionType : null,
                'points_per_question' => $this->poolPointsOverride,
            ]);

            // If quiz now has pools, update question_source
            $quiz->update(['question_source' => 'pools']);

            $this->poolSuccessMessage = "Question pool added from '{$bank->name}'.";
            $this->managingQuiz = $quiz->fresh(['pools.questionBank']);
            $this->poolBankId = '';
        } catch (\Throwable $e) {
            $this->poolErrorMessage = $e->getMessage();
        }
    }

    public function removePool(string $poolId): void
    {
        $pool = QuizQuestionPool::findOrFail($poolId);
        $quiz = Quiz::findOrFail($pool->quiz_id);
        $user = Auth::user();
        $this->authorizeCourseAction($quiz->course_id, $user);

        $pool->delete();

        $remainingPools = $quiz->pools()->count();
        if ($remainingPools === 0 && $quiz->questions()->count() > 0) {
            $quiz->update(['question_source' => 'direct']);
        }

        $this->managingQuiz = $quiz->fresh(['pools.questionBank']);
        $this->poolSuccessMessage = 'Question pool removed.';
    }

    public function toggleQuizRandomization(string $field): void
    {
        if (! $this->managingQuiz) {
            return;
        }

        if (in_array($field, ['randomize_questions', 'randomize_order'], true)) {
            $this->managingQuiz->update([
                $field => ! $this->managingQuiz->{$field},
            ]);
            $this->managingQuiz = $this->managingQuiz->fresh();
        }
    }

    public function toggleStatus(string $assessmentId): void
    {
        $quiz = Quiz::findOrFail($assessmentId);
        $user = Auth::user();
        $this->authorizeCourseAction($quiz->course_id, $user);

        $quiz->status = $quiz->status === 'Active' ? 'Draft' : 'Active';
        $quiz->save();
    }

    private function authorizeCourseAction(?string $courseId, ?User $user): void
    {
        if (! $user || ! $courseId) {
            return;
        }

        if ($user->hasRole('admin')) {
            return;
        }

        if ($user->hasRole('trainer')) {
            $isAssigned = Course::where('id', $courseId)
                ->whereHas('trainers', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                })
                ->exists();

            if (! $isAssigned) {
                abort(403, 'You are not assigned to manage assessments for this course.');
            }
        }
    }

    public function render(TenantContext $tenantContext)
    {
        $academy = $tenantContext->getAcademy();
        $user = Auth::user();

        $query = Quiz::query()->with(['course', 'questions', 'pools']);

        if ($academy) {
            $query->whereHas('course', function ($q) use ($academy) {
                $q->where('academy_id', $academy->id);
            });
        }

        // Trainer scope: only courses they are assigned to
        if ($user && $user->hasRole('trainer') && ! $user->hasRole('admin')) {
            $query->whereHas('course.trainers', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }

        if ($this->search) {
            $query->where('title', 'like', '%'.$this->search.'%');
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $assessments = $query->latest()->paginate(15);

        $coursesQuery = Course::query()->where('academy_id', $academy?->id);
        if ($user && $user->hasRole('trainer') && ! $user->hasRole('admin')) {
            $coursesQuery->whereHas('trainers', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }
        $courses = $coursesQuery->orderBy('title')->get();

        $banks = QuestionBank::query()
            ->where('academy_id', $academy?->id)
            ->where('status', QuestionBank::STATUS_ACTIVE)
            ->orderBy('name')
            ->get();

        $pendingGradingCounts = AssessmentAttempt::query()
            ->where('requires_manual_grading', true)
            ->where('status', AssessmentAttempt::STATUS_SUBMITTED)
            ->selectRaw('assessment_id, count(*) as count')
            ->groupBy('assessment_id')
            ->pluck('count', 'assessment_id');

        return view('livewire.admin.assessments-index', [
            'assessments' => $assessments,
            'courses' => $courses,
            'banks' => $banks,
            'pendingGradingCounts' => $pendingGradingCounts,
        ]);
    }
}
