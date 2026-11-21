<?php

namespace App\Livewire\Student;

use App\Domain\Commerce\Models\CommerceOrder;
use App\Domain\Commerce\Models\CommerceOrderInstallment;
use App\Domain\Commerce\Services\InstallmentMilestoneService;
use App\Domain\Commerce\Services\OrderAndEnrollmentService;
use App\Domain\Commerce\Services\RazorpayPaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('My Orders & Payments')]
class StudentOrdersIndex extends Component
{
    public function mount(): void
    {
        if (! Auth::user()?->hasRole('student')) {
            abort(403, 'Only students can access student orders.');
        }
    }

    public function payInstallment(
        int $orderId,
        int $installmentId,
        OrderAndEnrollmentService $orderService,
        RazorpayPaymentService $razorpayService
    ): mixed {
        $user = Auth::user();

        if (! $user || ! $user->hasRole('student')) {
            abort(403, 'Only students may make payments.');
        }

        $order = CommerceOrder::where('id', $orderId)->where('user_id', $user->id)->firstOrFail();
        $installment = CommerceOrderInstallment::where('id', $installmentId)->where('order_id', $order->id)->firstOrFail();

        $amountDue = $installment->remaining_balance;

        if ($amountDue <= 0.00) {
            session()->flash('info', 'This installment has already been settled.');

            return null;
        }

        if (! $razorpayService->isConfigured()) {
            session()->flash('error', 'Online payment gateway is temporarily unconfigured. Please contact academy support.');

            return null;
        }

        try {
            $rzpOrder = $razorpayService->createOrder($order, $amountDue, $order->currency);
        } catch (\Throwable $e) {
            session()->flash('error', 'Payment gateway error: '.$e->getMessage());

            return null;
        }

        $this->dispatch('initiate-razorpay-checkout', [
            'order_id' => $order->id,
            'installment_id' => $installment->id,
            'razorpay_order_id' => $rzpOrder['id'],
            'amount' => $rzpOrder['amount'],
            'currency' => $rzpOrder['currency'],
            'key' => $razorpayService->getKeyId(),
            'name' => 'SEO Academy',
            'description' => "Installment #{$installment->installment_number} for {$order->target_title}",
            'prefill_name' => $user->name,
            'prefill_email' => $user->email,
        ]);

        return null;
    }

    public function handlePaymentCancelled(?int $orderId = null): void
    {
        session()->flash('info', 'Payment checkout was cancelled or closed. Your installment remains due.');
    }

    public function verifyInstallmentPayment(
        int $orderId,
        int $installmentId,
        string $paymentId,
        string $rzpOrderId,
        string $signature,
        RazorpayPaymentService $razorpayService,
        OrderAndEnrollmentService $orderService
    ): void {
        $user = Auth::user();

        if (! $user || ! $user->hasRole('student')) {
            abort(403, 'Only students may verify payments.');
        }

        $order = CommerceOrder::where('id', $orderId)->where('user_id', $user->id)->firstOrFail();
        $installment = CommerceOrderInstallment::where('id', $installmentId)->where('order_id', $order->id)->firstOrFail();

        if (! $razorpayService->verifyPaymentSignature($rzpOrderId, $paymentId, $signature)) {
            session()->flash('error', 'Payment verification failed. Invalid gateway signature.');

            return;
        }

        $orderService->recordPayment(
            order: $order,
            amount: $installment->remaining_balance,
            paymentGateway: 'razorpay',
            gatewayData: [
                'gateway_order_id' => $rzpOrderId,
                'gateway_payment_id' => $paymentId,
                'gateway_signature' => $signature,
            ],
            installment: $installment
        );

        session()->flash('success', "Installment #{$installment->installment_number} payment verified successfully!");
    }

    public function render(InstallmentMilestoneService $milestoneService): View
    {
        $user = Auth::user();
        $orders = CommerceOrder::with(['installments', 'transactions'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $orderProgress = [];
        foreach ($orders as $order) {
            $orderProgress[$order->id] = $milestoneService->calculateOrderProgress($order);
        }

        return view('livewire.student.student-orders-index', [
            'orders' => $orders,
            'orderProgress' => $orderProgress,
        ]);
    }
}
