<?php

namespace App\Livewire\Commerce;

use App\Domain\Commerce\Models\CommerceInstallmentPlan;
use App\Domain\Commerce\Models\CommerceOrder;
use App\Domain\Commerce\Models\CourseBundle;
use App\Domain\Commerce\Services\CurrencyAndPricingService;
use App\Domain\Commerce\Services\OrderAndEnrollmentService;
use App\Domain\Commerce\Services\RazorpayPaymentService;
use App\Domain\Courses\Models\Course;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.marketing')]
class CheckoutComponent extends Component
{
    public string $type; // course, bundle

    public string|int $itemId;

    public string $activeCurrency = 'INR';

    public string $couponInput = '';

    public ?string $appliedCouponCode = null;

    public ?string $couponError = null;

    public string $paymentOption = 'full'; // full, installment

    public function mount(string $type, int|string $id, CurrencyAndPricingService $pricingService): void
    {
        if (Auth::check() && ! Auth::user()->hasRole('student')) {
            abort(403, 'Only students may make purchases. Administrators and instructors cannot purchase courses directly.');
        }

        $this->type = strtolower($type);
        if ($this->type === 'course') {
            $item = Course::where('id', $id)->orWhere('slug', $id)->firstOrFail();
            $this->itemId = $item->id;
        } elseif ($this->type === 'bundle') {
            $item = is_numeric($id) ? CourseBundle::findOrFail($id) : CourseBundle::where('slug', $id)->firstOrFail();
            $this->itemId = $item->id;
        } else {
            abort(404, 'Invalid checkout item type.');
        }

        $this->activeCurrency = $pricingService->resolveCurrency();
    }

    public function setCurrency(string $currency, CurrencyAndPricingService $pricingService): void
    {
        if ($pricingService->setCurrency($currency)) {
            $this->activeCurrency = $currency;
        }
    }

    public function applyCoupon(): void
    {
        $code = trim(strtoupper($this->couponInput));
        if (empty($code)) {
            $this->couponError = 'Please enter a coupon code.';

            return;
        }
        $this->appliedCouponCode = $code;
        $this->couponError = null;
    }

    public function removeCoupon(): void
    {
        $this->appliedCouponCode = null;
        $this->couponInput = '';
        $this->couponError = null;
    }

    public function getItem(): Course|CourseBundle
    {
        return $this->type === 'course'
            ? Course::findOrFail($this->itemId)
            : CourseBundle::with('courses')->findOrFail($this->itemId);
    }

    public function processCheckout(
        OrderAndEnrollmentService $orderService,
        RazorpayPaymentService $razorpayService
    ): mixed {
        if (! Auth::check()) {
            // Save checkout redirect URL
            session()->put('url.intended', request()->fullUrl());

            return redirect()->route('login');
        }

        $user = Auth::user();

        if (! $user->hasRole('student')) {
            abort(403, 'Only students may make purchases. Administrators and instructors cannot purchase courses directly.');
        }

        $item = $this->getItem();

        $plan = null;
        if ($this->paymentOption === 'installment' && $item->installment_plan_id) {
            $plan = CommerceInstallmentPlan::find($item->installment_plan_id);
        }

        // Calculate checkout
        $calc = $orderService->calculateCheckout(
            user: $user,
            item: $item,
            currency: $this->activeCurrency,
            couponCode: $this->appliedCouponCode,
            installmentPlan: $plan
        );

        if ($this->appliedCouponCode && $calc['coupon_error']) {
            $this->couponError = $calc['coupon_error'];

            return null;
        }

        // Create order
        $order = $orderService->createOrder(
            user: $user,
            item: $item,
            currency: $this->activeCurrency,
            couponCode: $this->appliedCouponCode,
            installmentPlan: $plan,
            paymentMethod: $calc['total_amount'] <= 0.00 ? 'free' : 'razorpay'
        );

        // If amount is zero (free course or 100% coupon discount), order is completed
        if ($calc['total_amount'] <= 0.00) {
            session()->flash('success', 'Enrollment successful! You have instant access to your curriculum.');

            return redirect()->route('student.orders.show', $order->id);
        }

        $depositDue = $calc['deposit_due_now'];

        // If Razorpay is not configured, do NOT mock payment — inform visitor clearly
        if (! $razorpayService->isConfigured()) {
            session()->flash('error', 'Online payment gateway is temporarily unconfigured. Please contact academy support.');

            return null;
        }

        // For Razorpay, create server order and emit client event
        try {
            $rzpOrder = $razorpayService->createOrder($order, $depositDue, $this->activeCurrency);
        } catch (\Throwable $e) {
            session()->flash('error', 'Payment gateway error: '.$e->getMessage());

            return null;
        }

        $this->dispatch('initiate-razorpay-checkout', [
            'order_id' => $order->id,
            'razorpay_order_id' => $rzpOrder['id'],
            'amount' => $rzpOrder['amount'],
            'currency' => $rzpOrder['currency'],
            'key' => $razorpayService->getKeyId(),
            'name' => 'SEO Academy',
            'description' => $item->title,
            'prefill_name' => $user->name,
            'prefill_email' => $user->email,
        ]);

        return null;
    }

    public function handlePaymentCancelled(?int $orderId = null): void
    {
        session()->flash('info', 'Payment checkout was cancelled or closed. Your order has been recorded and remains unpaid.');
    }

    public function verifyRazorpayPayment(
        int $orderId,
        string $paymentId,
        string $rzpOrderId,
        string $signature,
        RazorpayPaymentService $razorpayService,
        OrderAndEnrollmentService $orderService
    ): mixed {
        $user = Auth::user();
        if (! $user || ! $user->hasRole('student')) {
            abort(403, 'Only students may verify payments.');
        }

        $order = CommerceOrder::where('id', $orderId)->where('user_id', $user->id)->firstOrFail();

        if (! $razorpayService->verifyPaymentSignature($rzpOrderId, $paymentId, $signature)) {
            session()->flash('error', 'Payment verification failed. Invalid gateway signature.');

            return null;
        }

        if ($order->payment_status === 'paid') {
            session()->flash('info', 'Order has already been settled.');

            return redirect()->route('student.orders.show', $order->id);
        }

        $installment = $order->is_installment
            ? $order->installments()->where('status', 'due')->first()
            : null;

        $amountToPay = $installment ? (float) $installment->remaining_balance : (float) $order->total_amount;

        $orderService->recordPayment(
            order: $order,
            amount: $amountToPay,
            paymentGateway: 'razorpay',
            gatewayData: [
                'gateway_order_id' => $rzpOrderId,
                'gateway_payment_id' => $paymentId,
                'gateway_signature' => $signature,
            ],
            installment: $installment
        );

        session()->flash('success', 'Payment processed successfully! Your enrollment is active.');

        return redirect()->route('student.orders.show', $order->id);
    }

    public function render(OrderAndEnrollmentService $orderService): View
    {
        $item = $this->getItem();
        $user = Auth::user();

        $plan = null;
        if ($this->paymentOption === 'installment' && $item->installment_plan_id) {
            $plan = CommerceInstallmentPlan::find($item->installment_plan_id);
        }

        $calc = null;
        if ($user) {
            $calc = $orderService->calculateCheckout(
                user: $user,
                item: $item,
                currency: $this->activeCurrency,
                couponCode: $this->appliedCouponCode,
                installmentPlan: $plan
            );
        }

        $pricingService = app(CurrencyAndPricingService::class);
        $enabledCodes = $pricingService->getEnabledCurrencies();
        $supportedCurrencies = array_intersect_key(
            CurrencyAndPricingService::SUPPORTED_CURRENCIES,
            array_flip($enabledCodes)
        );

        return view('livewire.commerce.checkout-component', [
            'item' => $item,
            'calc' => $calc,
            'availablePlan' => $item->installmentPlan,
            'supportedCurrencies' => $supportedCurrencies,
            'isCurrencySwitcherEnabled' => $pricingService->isCurrencySwitcherEnabled(),
        ])->title('Checkout — '.$item->title);
    }
}
