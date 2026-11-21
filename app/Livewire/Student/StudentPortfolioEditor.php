<?php

namespace App\Livewire\Student;

use App\Domain\Projects\Models\ProjectSubmission;
use App\Domain\Projects\Models\StudentPortfolio;
use App\Domain\Projects\Models\StudentPortfolioItem;
use App\Domain\Projects\Services\StudentPortfolioService;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('My SEO Portfolio')]
class StudentPortfolioEditor extends Component
{
    public StudentPortfolio $portfolio;

    // Header fields
    public string $title = '';

    public string $headline = '';

    public string $bio = '';

    public string $customSkillsText = '';

    // Sharing state
    public bool $isPublic = false;

    public ?string $publicToken = null;

    public bool $showConsentModal = false;

    public bool $consentAgreed = false;

    // Item editor modal state
    public bool $showItemModal = false;

    public ?string $editingItemId = null;

    public ?string $selectedSubmissionId = null;

    public string $itemTitle = '';

    public string $itemCaseStudy = '';

    public string $itemSkillsText = '';

    public bool $itemFeatured = false;

    public bool $itemVisible = true;

    public function mount(TenantContext $tenantContext, StudentPortfolioService $portfolioService): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->hasRole('student')) {
            abort(403, 'Unauthorized.');
        }

        $profile = $user->studentProfile;
        if (! $profile) {
            abort(403, 'Student profile not found.');
        }

        $this->portfolio = $portfolioService->getOrCreatePortfolio($profile);
        $this->syncFromModel();
    }

    protected function syncFromModel(): void
    {
        $this->title = $this->portfolio->title ?? '';
        $this->headline = $this->portfolio->headline ?? '';
        $this->bio = $this->portfolio->bio ?? '';
        $this->customSkillsText = implode(', ', $this->portfolio->custom_skills ?? []);
        $this->isPublic = (bool) $this->portfolio->is_public;
        $this->publicToken = $this->portfolio->public_token;
    }

    public function saveHeader(StudentPortfolioService $portfolioService): void
    {
        $skills = array_values(array_filter(array_map('trim', explode(',', $this->customSkillsText))));

        $portfolioService->updatePortfolio($this->portfolio, [
            'title' => $this->title,
            'headline' => $this->headline,
            'bio' => $this->bio,
            'custom_skills' => $skills,
        ]);

        session()->flash('message', 'Portfolio overview updated successfully.');
    }

    public function openShareModal(): void
    {
        $this->consentAgreed = false;
        $this->showConsentModal = true;
    }

    public function confirmEnableSharing(StudentPortfolioService $portfolioService): void
    {
        if (! $this->consentAgreed) {
            return;
        }

        $token = $portfolioService->enablePublicSharing($this->portfolio, true);
        $this->portfolio->refresh();
        $this->syncFromModel();
        $this->showConsentModal = false;
        session()->flash('message', 'Public sharing enabled! You can now share your verified portfolio link.');
    }

    public function revokeSharing(StudentPortfolioService $portfolioService): void
    {
        $portfolioService->revokePublicSharing($this->portfolio);
        $this->portfolio->refresh();
        $this->syncFromModel();
        session()->flash('message', 'Public sharing has been revoked. Past links will no longer work.');
    }

    public function openAddItemModal(): void
    {
        $this->resetItemForm();
        $this->showItemModal = true;
    }

    public function openEditItemModal(string $itemId): void
    {
        $item = StudentPortfolioItem::findOrFail($itemId);
        $this->editingItemId = $item->id;
        $this->selectedSubmissionId = $item->submission_id;
        $this->itemTitle = $item->title;
        $this->itemCaseStudy = $item->case_study ?? '';
        $this->itemSkillsText = implode(', ', $item->skills_demonstrated ?? []);
        $this->itemFeatured = (bool) $item->is_featured;
        $this->itemVisible = (bool) $item->is_visible;
        $this->showItemModal = true;
    }

    public function saveItem(StudentPortfolioService $portfolioService): void
    {
        $this->validate([
            'itemTitle' => 'required|string|max:255',
            'itemCaseStudy' => 'required|string|min:10',
        ]);

        $skills = array_values(array_filter(array_map('trim', explode(',', $this->itemSkillsText))));

        if ($this->editingItemId) {
            $item = StudentPortfolioItem::findOrFail($this->editingItemId);
            $portfolioService->updatePortfolioItem($item, [
                'title' => $this->itemTitle,
                'case_study' => $this->itemCaseStudy,
                'skills_demonstrated' => $skills,
                'is_featured' => $this->itemFeatured,
                'is_visible' => $this->itemVisible,
            ]);
        } else {
            if (! $this->selectedSubmissionId) {
                $this->addError('selectedSubmissionId', 'Please select an approved project.');

                return;
            }

            $submission = ProjectSubmission::findOrFail($this->selectedSubmissionId);
            $portfolioService->addProjectToPortfolio($this->portfolio, $submission, [
                'title' => $this->itemTitle,
                'case_study' => $this->itemCaseStudy,
                'skills_demonstrated' => $skills,
                'is_featured' => $this->itemFeatured,
            ]);
        }

        $this->showItemModal = false;
        $this->resetItemForm();
        session()->flash('message', 'Project successfully saved to portfolio.');
    }

    public function removeItem(string $itemId, StudentPortfolioService $portfolioService): void
    {
        $item = StudentPortfolioItem::findOrFail($itemId);
        $portfolioService->removePortfolioItem($item);
        session()->flash('message', 'Project removed from portfolio.');
    }

    public function toggleItemVisibility(string $itemId): void
    {
        $item = StudentPortfolioItem::findOrFail($itemId);
        $item->update(['is_visible' => ! $item->is_visible]);
    }

    protected function resetItemForm(): void
    {
        $this->editingItemId = null;
        $this->selectedSubmissionId = null;
        $this->itemTitle = '';
        $this->itemCaseStudy = '';
        $this->itemSkillsText = '';
        $this->itemFeatured = false;
        $this->itemVisible = true;
    }

    public function render()
    {
        $user = Auth::user();

        // Fetch approved submissions that are not yet added to portfolio
        $existingSubmissionIds = $this->portfolio->items()->pluck('submission_id')->all();

        $availableApprovedSubmissions = ProjectSubmission::query()
            ->where('user_id', $user->getKey())
            ->where('status', ProjectSubmission::STATUS_APPROVED)
            ->whereNotIn('id', $existingSubmissionIds)
            ->with(['project', 'achievement'])
            ->get();

        $portfolioItems = $this->portfolio->items()
            ->with(['submission.project', 'submission.achievement', 'submission.attachments'])
            ->orderBy('display_order')
            ->get();

        return view('livewire.student.student-portfolio-editor', [
            'availableApprovedSubmissions' => $availableApprovedSubmissions,
            'portfolioItems' => $portfolioItems,
        ]);
    }
}
