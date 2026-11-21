<?php

namespace App\Livewire\Admin\Contact;

use App\Domain\Academies\Models\Academy;
use App\Domain\Cms\Models\CmsContactInquiry;
use App\Domain\Cms\Services\ContactInquiryService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Contact Inquiries & Admissions')]
class ContactInquiriesIndex extends Component
{
    use WithPagination;

    public ?string $academyId = null;

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    #[Url(as: 'source')]
    public string $sourceFilter = 'all';

    #[Url(as: 'assigned')]
    public string $assignedFilter = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'from')]
    public ?string $dateFrom = null;

    #[Url(as: 'to')]
    public ?string $dateTo = null;

    #[Url(as: 'sort')]
    public string $sortBy = 'recent'; // recent, oldest, follow_up

    public int $perPage = 15;

    // Detail Modal State
    #[Url(as: 'view')]
    public ?string $selectedInquiryId = null;

    public ?CmsContactInquiry $selectedInquiry = null;

    public string $editStatus = 'new';

    public ?string $editAssignedStaffId = null;

    public ?string $editFollowUpDate = null;

    public string $newInternalNote = '';

    public ?string $feedbackMessage = null;

    public string $feedbackType = 'success';

    public function mount(?string $view = null): void
    {
        $user = auth()->user();
        if (! $user || (! $user->hasRole('admin') && ! $user->hasRole('support_staff'))) {
            abort(403, 'Unauthorized access to contact inquiries.');
        }

        $academy = Academy::current() ?? Academy::first();
        $this->academyId = $academy?->id;

        $targetView = $view ?: request()->query('view');
        if ($targetView) {
            $this->openInquiryModal($targetView);
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSourceFilter(): void
    {
        $this->resetPage();
    }

    public function updatingAssignedFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function updatingSortBy(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->statusFilter = 'all';
        $this->sourceFilter = 'all';
        $this->assignedFilter = 'all';
        $this->search = '';
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->sortBy = 'recent';
        $this->resetPage();
    }

    public function openInquiryModal(string $inquiryId): void
    {
        $inquiry = CmsContactInquiry::with(['assignedTo', 'conversation'])->find($inquiryId);
        if (! $inquiry) {
            return;
        }

        $this->selectedInquiryId = $inquiry->id;
        $this->selectedInquiry = $inquiry;
        $this->editStatus = $inquiry->status;
        $this->editAssignedStaffId = $inquiry->assigned_to_user_id;
        $this->editFollowUpDate = $inquiry->follow_up_date ? $inquiry->follow_up_date->format('Y-m-d') : null;
        $this->newInternalNote = '';
        $this->feedbackMessage = null;
    }

    public function closeInquiryModal(): void
    {
        $this->selectedInquiryId = null;
        $this->selectedInquiry = null;
        $this->newInternalNote = '';
        $this->feedbackMessage = null;
    }

    public function updateStatus(string $newStatus, ContactInquiryService $service): void
    {
        if (! $this->selectedInquiry) {
            return;
        }

        $user = auth()->user();
        $this->selectedInquiry->updateStatus($newStatus, $user);
        $this->editStatus = $newStatus;
        $this->selectedInquiry->refresh();
        $this->setFeedback("Status updated to '{$newStatus}'.", 'success');
    }

    public function assignStaff(?string $staffId, ContactInquiryService $service): void
    {
        if (! $this->selectedInquiry) {
            return;
        }

        $user = auth()->user();
        $staff = $staffId ? User::find($staffId) : null;
        $service->assignInquiry($this->selectedInquiry, $staff, $user);
        $this->editAssignedStaffId = $staffId;
        $this->selectedInquiry->refresh();
        $this->setFeedback($staff ? "Inquiry assigned to {$staff->name}." : 'Inquiry unassigned.', 'success');
    }

    public function saveFollowUpDate(): void
    {
        if (! $this->selectedInquiry) {
            return;
        }

        $user = auth()->user();
        $this->selectedInquiry->setFollowUpDate($this->editFollowUpDate, $user);
        $this->selectedInquiry->refresh();
        $this->setFeedback('Follow-up date updated.', 'success');
    }

    public function submitInternalNote(): void
    {
        if (! $this->selectedInquiry || trim($this->newInternalNote) === '') {
            return;
        }

        $user = auth()->user();
        $this->selectedInquiry->addInternalNote($this->newInternalNote, $user);
        $this->selectedInquiry->refresh();
        $this->newInternalNote = '';
        $this->setFeedback('Internal note added.', 'success');
    }

    protected function setFeedback(string $message, string $type = 'success'): void
    {
        $this->feedbackMessage = $message;
        $this->feedbackType = $type;
    }

    public function render(): View
    {
        $query = CmsContactInquiry::query()
            ->with(['assignedTo', 'conversation']);

        if ($this->academyId) {
            $query->where('academy_id', $this->academyId);
        }

        // Status Filter
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        // Source Filter
        if ($this->sourceFilter !== 'all') {
            $query->where('source', $this->sourceFilter);
        }

        // Assigned Filter
        if ($this->assignedFilter !== 'all') {
            if ($this->assignedFilter === 'unassigned') {
                $query->whereNull('assigned_to_user_id');
            } else {
                $query->where('assigned_to_user_id', $this->assignedFilter);
            }
        }

        // Search Filter
        if (! empty($this->search)) {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('phone_formatted', 'like', $term)
                    ->orWhere('subject', 'like', $term)
                    ->orWhere('course_interest', 'like', $term)
                    ->orWhere('message', 'like', $term);
            });
        }

        // Date Range Filter
        if (! empty($this->dateFrom)) {
            $query->whereDate('created_at', '>=', Carbon::parse($this->dateFrom)->toDateString());
        }
        if (! empty($this->dateTo)) {
            $query->whereDate('created_at', '<=', Carbon::parse($this->dateTo)->toDateString());
        }

        // Sorting
        switch ($this->sortBy) {
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'follow_up':
                $query->orderByRaw('follow_up_date IS NULL, follow_up_date ASC');
                break;
            case 'recent':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $inquiries = $query->paginate($this->perPage);

        // Status counts for tabs
        $countsQuery = CmsContactInquiry::query();
        if ($this->academyId) {
            $countsQuery->where('academy_id', $this->academyId);
        }
        $rawCounts = (clone $countsQuery)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $totalCount = (clone $countsQuery)->count();
        $statusCounts = [
            'all' => $totalCount,
            'new' => $rawCounts['new'] ?? 0,
            'contacted' => $rawCounts['contacted'] ?? 0,
            'in_progress' => $rawCounts['in_progress'] ?? 0,
            'converted' => $rawCounts['converted'] ?? 0,
            'closed' => $rawCounts['closed'] ?? 0,
        ];

        // Available Staff for assignment
        $availableStaff = User::query()
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['admin', 'support_staff']);
            })
            ->orderBy('name')
            ->get();

        return view('livewire.admin.contact.contact-inquiries-index', [
            'inquiries' => $inquiries,
            'statusCounts' => $statusCounts,
            'availableStaff' => $availableStaff,
        ]);
    }
}
