<?php

namespace App\Livewire\Admin;

use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Models\QuestionBank;
use App\Domain\Questions\Services\QuestionBankService;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Question Banks')]
class QuestionBankManager extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = 'active';

    #[Url]
    public string $categoryFilter = '';

    #[Url]
    public ?string $selectedBankId = null;

    // Bank Questions Filter
    public string $questionSearch = '';

    public string $questionTypeFilter = '';

    public string $difficultyFilter = '';

    // Bank Modal State
    public bool $showBankModal = false;

    public ?string $editingBankId = null;

    public string $bankName = '';

    public string $bankDescription = '';

    public string $bankCategory = '';

    // Question Modal State
    public bool $showQuestionModal = false;

    public ?string $editingQuestionId = null;

    public string $questionText = '';

    public string $questionType = Question::TYPE_SINGLE_CHOICE;

    public array $options = [];

    public mixed $correctAnswer = '';

    public string $explanation = '';

    public string $rubric = '';

    public int $points = 1;

    public string $difficulty = Question::DIFFICULTY_MEDIUM;

    public string $questionCategory = '';

    public string $tagsInput = '';

    // Add Existing Questions Modal State
    public bool $showAddExistingModal = false;

    public string $existingSearch = '';

    public array $selectedExistingQuestionIds = [];

    // Feedback
    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(TenantContext $tenantContext): void
    {
        $user = Auth::user();
        if (! $user instanceof User || (! $user->hasRole('admin') && ! $user->hasRole('trainer'))) {
            abort(403, 'Unauthorized access to question banks.');
        }

        $academy = $tenantContext->getAcademy();
        if (! $academy) {
            abort(404, 'No active academy context found.');
        }
    }

    public function selectBank(?string $bankId): void
    {
        $this->selectedBankId = $bankId;
        $this->resetPage();
        $this->questionSearch = '';
        $this->questionTypeFilter = '';
        $this->difficultyFilter = '';
        $this->clearMessages();
    }

    public function openCreateBankModal(): void
    {
        $this->clearMessages();
        $this->editingBankId = null;
        $this->bankName = '';
        $this->bankDescription = '';
        $this->bankCategory = '';
        $this->showBankModal = true;
    }

    public function openEditBankModal(string $bankId): void
    {
        $this->clearMessages();
        $bank = QuestionBank::findOrFail($bankId);
        $this->authorizeBankAction('update', $bank);

        $this->editingBankId = $bank->id;
        $this->bankName = $bank->name;
        $this->bankDescription = $bank->description ?? '';
        $this->bankCategory = $bank->category ?? '';
        $this->showBankModal = true;
    }

    public function saveBank(TenantContext $tenantContext, QuestionBankService $service): void
    {
        $this->validate([
            'bankName' => 'required|string|max:255',
            'bankCategory' => 'nullable|string|max:128',
        ]);

        $academy = $tenantContext->getAcademy();
        $user = Auth::user();

        try {
            if ($this->editingBankId) {
                $bank = QuestionBank::findOrFail($this->editingBankId);
                $this->authorizeBankAction('update', $bank);
                $service->updateBank($bank, [
                    'name' => $this->bankName,
                    'description' => $this->bankDescription,
                    'category' => $this->bankCategory,
                ]);
                $this->successMessage = 'Question bank updated successfully.';
            } else {
                $service->createBank($academy, [
                    'name' => $this->bankName,
                    'description' => $this->bankDescription,
                    'category' => $this->bankCategory,
                ], $user);
                $this->successMessage = 'Question bank created successfully.';
            }

            $this->showBankModal = false;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function toggleArchiveBank(string $bankId, QuestionBankService $service): void
    {
        $bank = QuestionBank::findOrFail($bankId);
        $this->authorizeBankAction('update', $bank);

        if ($bank->isArchived()) {
            $service->restoreBank($bank);
            $this->successMessage = "Question bank '{$bank->name}' restored.";
        } else {
            $service->archiveBank($bank);
            $this->successMessage = "Question bank '{$bank->name}' archived.";
        }
    }

    public function openCreateQuestionModal(): void
    {
        $this->clearMessages();
        $this->editingQuestionId = null;
        $this->questionText = '';
        $this->questionType = Question::TYPE_SINGLE_CHOICE;
        $this->options = [
            ['key' => 'A', 'text' => 'Option A'],
            ['key' => 'B', 'text' => 'Option B'],
            ['key' => 'C', 'text' => 'Option C'],
            ['key' => 'D', 'text' => 'Option D'],
        ];
        $this->correctAnswer = 'A';
        $this->explanation = '';
        $this->rubric = '';
        $this->points = 1;
        $this->difficulty = Question::DIFFICULTY_MEDIUM;
        $this->questionCategory = '';
        $this->tagsInput = '';
        $this->showQuestionModal = true;
    }

    public function openEditQuestionModal(string $questionId): void
    {
        $this->clearMessages();
        $question = Question::findOrFail($questionId);

        $this->editingQuestionId = $question->id;
        $this->questionText = $question->question_text;
        $this->questionType = $question->question_type;
        $this->options = $question->options ?? [];
        $this->correctAnswer = $question->correct_answer ?? '';
        $this->explanation = $question->explanation ?? '';
        $this->rubric = $question->rubric ?? '';
        $this->points = (int) ($question->points ?? 1);
        $this->difficulty = $question->difficulty ?? Question::DIFFICULTY_MEDIUM;
        $this->questionCategory = $question->category ?? '';
        $this->tagsInput = is_array($question->tags) ? implode(', ', $question->tags) : '';
        $this->showQuestionModal = true;
    }

    public function addOption(): void
    {
        $count = count($this->options);
        $nextKey = chr(65 + $count);
        $this->options[] = ['key' => $nextKey, 'text' => "Option {$nextKey}"];
    }

    public function removeOption(int $index): void
    {
        unset($this->options[$index]);
        $this->options = array_values($this->options);
    }

    public function saveQuestion(TenantContext $tenantContext, QuestionBankService $service): void
    {
        $this->validate([
            'questionText' => 'required|string',
            'questionType' => 'required|in:'.implode(',', Question::QUESTION_TYPES),
            'points' => 'required|integer|min:1',
            'difficulty' => 'required|in:'.implode(',', Question::DIFFICULTIES),
        ]);

        $academy = $tenantContext->getAcademy();
        $user = Auth::user();

        $tags = array_filter(array_map('trim', explode(',', $this->tagsInput)));
        $bank = $this->selectedBankId ? QuestionBank::find($this->selectedBankId) : null;

        if ($bank) {
            $this->authorizeBankAction('update', $bank);
        }

        try {
            $payload = [
                'question_text' => $this->questionText,
                'question_type' => $this->questionType,
                'options' => $this->options,
                'correct_answer' => $this->correctAnswer,
                'explanation' => $this->explanation,
                'rubric' => $this->rubric,
                'points' => $this->points,
                'difficulty' => $this->difficulty,
                'category' => $this->questionCategory,
                'tags' => array_values($tags),
            ];

            if ($this->editingQuestionId) {
                $question = Question::findOrFail($this->editingQuestionId);
                $service->updateQuestion($question, $payload);
                $this->successMessage = 'Question updated successfully.';
            } else {
                $service->createQuestion($academy, $payload, $bank, $user);
                $this->successMessage = 'Question created and added to bank.';
            }

            $this->showQuestionModal = false;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function removeQuestionFromCurrentBank(string $questionId, QuestionBankService $service): void
    {
        if (! $this->selectedBankId) {
            return;
        }

        $bank = QuestionBank::findOrFail($this->selectedBankId);
        $this->authorizeBankAction('update', $bank);

        $question = Question::findOrFail($questionId);
        $service->removeQuestionFromBank($bank, $question);

        $this->successMessage = 'Question removed from bank (preserved in master questions repository).';
    }

    public function openAddExistingModal(): void
    {
        $this->clearMessages();
        $this->selectedExistingQuestionIds = [];
        $this->existingSearch = '';
        $this->showAddExistingModal = true;
    }

    public function addSelectedQuestionsToBank(QuestionBankService $service): void
    {
        if (! $this->selectedBankId || empty($this->selectedExistingQuestionIds)) {
            $this->showAddExistingModal = false;

            return;
        }

        $bank = QuestionBank::findOrFail($this->selectedBankId);
        $this->authorizeBankAction('update', $bank);

        $addedCount = 0;
        foreach ($this->selectedExistingQuestionIds as $qId) {
            $question = Question::find($qId);
            if ($question && (string) $question->academy_id === (string) $bank->academy_id) {
                $service->addQuestionToBank($bank, $question);
                $addedCount++;
            }
        }

        $this->successMessage = "Added {$addedCount} questions to '{$bank->name}'.";
        $this->showAddExistingModal = false;
        $this->selectedExistingQuestionIds = [];
    }

    public function clearMessages(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    private function authorizeBankAction(string $ability, QuestionBank $bank): void
    {
        $user = Auth::user();
        if ($user->hasRole('admin')) {
            return;
        }

        if ($user->hasRole('trainer') && (string) $bank->created_by === (string) $user->id) {
            return;
        }

        abort(403, 'Unauthorized to modify this question bank.');
    }

    public function render(TenantContext $tenantContext): View
    {
        $academy = $tenantContext->getAcademy();

        if ($this->selectedBankId) {
            $currentBank = QuestionBank::with('creator')->findOrFail($this->selectedBankId);

            $questionsQuery = $currentBank->questions();

            if (! empty($this->questionSearch)) {
                $questionsQuery->where('questions.question_text', 'like', '%'.$this->questionSearch.'%');
            }

            if (! empty($this->questionTypeFilter)) {
                $questionsQuery->where('questions.question_type', $this->questionTypeFilter);
            }

            if (! empty($this->difficultyFilter)) {
                $questionsQuery->where('questions.difficulty', $this->difficultyFilter);
            }

            $questions = $questionsQuery->paginate(15);

            // Available unattached questions for Add Existing modal
            $alreadyAttachedIds = $currentBank->questions()->pluck('questions.id')->all();
            $availableQuestionsQuery = Question::query()
                ->where('academy_id', $academy->id)
                ->where('status', Question::STATUS_ACTIVE)
                ->whereNotIn('id', $alreadyAttachedIds);

            if (! empty($this->existingSearch)) {
                $availableQuestionsQuery->where('question_text', 'like', '%'.$this->existingSearch.'%');
            }

            $availableExistingQuestions = $availableQuestionsQuery->limit(20)->get();

            return view('livewire.admin.question-bank-manager', [
                'currentBank' => $currentBank,
                'questions' => $questions,
                'availableExistingQuestions' => $availableExistingQuestions,
                'banks' => null,
            ]);
        }

        // Banks listing view
        $banksQuery = QuestionBank::query()
            ->withCount('questions')
            ->where('academy_id', $academy->id);

        if ($this->statusFilter === 'active') {
            $banksQuery->where('status', QuestionBank::STATUS_ACTIVE);
        } elseif ($this->statusFilter === 'archived') {
            $banksQuery->where('status', QuestionBank::STATUS_ARCHIVED);
        }

        if (! empty($this->categoryFilter)) {
            $banksQuery->where('category', $this->categoryFilter);
        }

        if (! empty($this->search)) {
            $banksQuery->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('description', 'like', '%'.$this->search.'%');
            });
        }

        $banks = $banksQuery->latest()->paginate(12);

        $categories = QuestionBank::query()
            ->where('academy_id', $academy->id)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values();

        return view('livewire.admin.question-bank-manager', [
            'banks' => $banks,
            'categories' => $categories,
            'currentBank' => null,
            'questions' => null,
            'availableExistingQuestions' => [],
        ]);
    }
}
