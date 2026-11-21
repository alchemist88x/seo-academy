<?php

namespace App\Livewire\Admin\Commerce;

use App\Domain\Commerce\Models\CommerceOrder;
use App\Domain\Commerce\Models\CommerceOrderInstallment;
use App\Domain\Commerce\Models\CourseBundle;
use App\Domain\Commerce\Services\OrderAndEnrollmentService;
use App\Domain\Courses\Models\Course;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Orders & Enrollment Payments')]
class OrdersManager extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    public ?int $selectedOrderId = null;

    public bool $showDetailModal = false;

    // Direct Assignment Modal
    public bool $showAssignModal = false;

    public string|int|null $assignStudentId = null;

    public string $assignItemType = 'course'; // course, bundle

    public string|int|null $assignItemId = null;

    public string $assignPaymentMethod = 'complimentary'; // complimentary, scholarship, cash, bank_transfer, manual_offline

    public ?float $assignAmountPaid = null;

    public string $assignReason = 'Admin grant';

    public string $assignNotes = '';

    // Record Offline Payment Modal
    public bool $showPaymentModal = false;

    public ?int $paymentOrderId = null;

    public ?int $paymentInstallmentId = null;

    public float $paymentAmount = 0.00;

    public string $paymentMethod = 'cash';

    public string $paymentReference = '';

    public string $paymentNotes = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function viewOrder(int $id): void
    {
        $this->selectedOrderId = $id;
        $this->showDetailModal = true;
    }

    public function openAssignModal(): void
    {
        $this->reset([
            'assignStudentId', 'assignItemId', 'assignAmountPaid',
            'assignReason', 'assignNotes',
        ]);
        $this->assignItemType = 'course';
        $this->assignPaymentMethod = 'complimentary';
        $this->assignReason = 'Admin grant';
        $this->showAssignModal = true;
    }

    public function executeAssign(OrderAndEnrollmentService $service): void
    {
        $this->validate([
            'assignStudentId' => 'required|exists:users,id',
            'assignItemType' => 'required|in:course,bundle',
            'assignItemId' => 'required',
            'assignPaymentMethod' => 'required|in:complimentary,scholarship,sponsored,cash,bank_transfer,manual_offline',
        ]);

        $student = User::findOrFail($this->assignStudentId);
        $item = $this->assignItemType === 'course'
            ? Course::findOrFail($this->assignItemId)
            : CourseBundle::findOrFail($this->assignItemId);

        $order = $service->adminDirectAssign(
            student: $student,
            item: $item,
            paymentMethod: $this->assignPaymentMethod,
            amountPaid: $this->assignAmountPaid,
            reason: $this->assignReason,
            notes: $this->assignNotes,
            admin: Auth::user()
        );

        $this->showAssignModal = false;
        session()->flash('success', "Enrolled {$student->name} into {$item->title} via {$this->assignPaymentMethod} (Order #{$order->order_number}).");
    }

    public function openPaymentModal(int $orderId, ?int $installmentId = null): void
    {
        $order = CommerceOrder::findOrFail($orderId);
        $this->paymentOrderId = $order->id;
        $this->paymentInstallmentId = $installmentId;

        if ($installmentId) {
            $inst = CommerceOrderInstallment::find($installmentId);
            $this->paymentAmount = $inst ? $inst->remaining_balance : (float) $order->outstanding_balance;
        } else {
            $this->paymentAmount = (float) $order->outstanding_balance;
        }

        $this->paymentMethod = 'cash';
        $this->paymentReference = 'REF-'.strtoupper(substr(uniqid(), -6));
        $this->paymentNotes = 'Manual offline collection';
        $this->showPaymentModal = true;
    }

    public function executeRecordPayment(OrderAndEnrollmentService $service): void
    {
        $this->validate([
            'paymentOrderId' => 'required|exists:commerce_orders,id',
            'paymentAmount' => 'required|numeric|min:0.01',
            'paymentMethod' => 'required|in:cash,bank_transfer,manual_offline,manual',
        ]);

        $order = CommerceOrder::findOrFail($this->paymentOrderId);
        $installment = $this->paymentInstallmentId ? CommerceOrderInstallment::find($this->paymentInstallmentId) : null;

        $service->recordPayment(
            order: $order,
            amount: $this->paymentAmount,
            paymentGateway: $this->paymentMethod,
            gatewayData: [
                'reference_number' => $this->paymentReference,
                'notes' => $this->paymentNotes,
            ],
            installment: $installment,
            recordedBy: Auth::user()
        );

        $this->showPaymentModal = false;
        if ($this->showDetailModal) {
            // refresh order detail
            $this->selectedOrderId = $order->id;
        }
        session()->flash('success', "Payment of {$order->currency} {$this->paymentAmount} recorded successfully for Order #{$order->order_number}.");
    }

    public function render(): View
    {
        $query = CommerceOrder::with(['user', 'coupon', 'installmentPlan']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('order_number', 'like', '%'.$this->search.'%')
                    ->orWhereHas('user', function ($uq) {
                        $uq->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('email', 'like', '%'.$this->search.'%');
                    });
            });
        }

        if ($this->statusFilter !== 'all') {
            $query->where('payment_status', $this->statusFilter);
        }

        $orders = $query->latest()->paginate(15);
        $selectedOrder = $this->selectedOrderId ? CommerceOrder::with(['user', 'installments', 'transactions.recordedBy', 'coupon'])->find($this->selectedOrderId) : null;

        // Data for assign modal
        $students = User::whereHas('roles', fn ($q) => $q->where('name', 'student'))->orderBy('name')->get();
        if ($students->isEmpty()) {
            $students = User::orderBy('name')->get();
        }
        $courses = Course::published()->orderBy('title')->get();
        $bundles = CourseBundle::where('status', 'published')->orderBy('title')->get();

        return view('livewire.admin.commerce.orders-manager', [
            'orders' => $orders,
            'selectedOrder' => $selectedOrder,
            'students' => $students,
            'courses' => $courses,
            'bundles' => $bundles,
        ]);
    }
}
