<?php

namespace App\Livewire\Student;

use App\Domain\Commerce\Models\CommerceOrder;
use App\Domain\Commerce\Models\CommerceOrderInstallment;
use App\Domain\Commerce\Services\OrderAndEnrollmentService;
use App\Domain\Commerce\Services\RazorpayPaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class StudentOrderDetail extends Component
{
    public int $orderId;

    public function mount(int $order): void
    {
        if (! Auth::user()?->hasRole('student')) {
            abort(403, 'Only students can access student order receipts.');
        }

        $this->orderId = $order;
    }

    public function payInstallment(
        int $installmentId,
        OrderAndEnrollmentService $orderService,
        RazorpayPaymentService $razorpayService
    ): mixed {
        $user = Auth::user();

        if (! $user || ! $user->hasRole('student')) {
            abort(403, 'Only students may make payments.');
        }

        $order = CommerceOrder::where('id', $this->orderId)->where('user_id', $user->id)->firstOrFail();
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

        $amountDue = $installment->remaining_balance;

        $orderService->recordPayment(
            order: $order,
            amount: $amountDue,
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

    public function render(): View
    {
        $user = Auth::user();
        $order = CommerceOrder::with(['installments', 'transactions', 'coupon'])
            ->where('id', $this->orderId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return view('livewire.student.student-order-detail', [
            'order' => $order,
        ])->title('Order #'.$order->order_number.' — SEO Academy');
    }
}
