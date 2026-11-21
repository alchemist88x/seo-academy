<div class="bg-gray-50 min-h-screen py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Title and Currency Switcher -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-6 mb-8 border-b border-gray-200 gap-4">
            <div>
                <span class="text-xs font-bold text-indigo-600 uppercase tracking-wider">Secure Enrollment Checkout</span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mt-1">Order Summary</h1>
            </div>
            <div class="flex items-center space-x-2 bg-white px-3 py-1.5 rounded-lg border border-gray-200 shadow-sm w-max">
                <span class="text-xs font-medium text-gray-500">Currency:</span>
                <select wire:change="setCurrency($event.target.value)" class="text-xs font-bold text-gray-800 bg-transparent border-0 focus:ring-0 cursor-pointer">
                    @foreach ($supportedCurrencies as $code => $info)
                        <option value="{{ $code }}" {{ $activeCurrency === $code ? 'selected' : '' }}>
                            {{ $code }} ({{ $info['symbol'] }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        @if (session()->has('error'))
            <div class="mb-6 bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-lg flex items-center">
                <svg class="w-5 h-5 mr-2 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: Item Overview & Payment Plan Selection -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Enrolling Item Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $type === 'bundle' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }} uppercase">
                                {{ $type === 'bundle' ? 'Course Bundle' : 'Accredited Course' }}
                            </span>
                            <h2 class="text-xl font-bold text-gray-900 mt-2">{{ $item->title }}</h2>
                            <p class="text-xs text-gray-600 mt-1 max-w-md">{{ Str::limit($item->description, 150) }}</p>
                        </div>
                    </div>

                    @if ($type === 'bundle' && $item->courses->isNotEmpty())
                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <div class="text-xs font-bold uppercase text-gray-500 mb-2">Included in this bundle:</div>
                            <div class="space-y-1.5">
                                @foreach ($item->courses as $c)
                                    <div class="flex items-center justify-between text-xs text-gray-700">
                                        <div class="flex items-center space-x-2">
                                            <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                            <span class="font-medium">{{ $c->title }}</span>
                                        </div>
                                        @if ($calc && in_array($c->id, $calc['owned_course_ids']))
                                            <span class="text-2xs font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded border border-blue-200">
                                                Already Owned (Deducted)
                                            </span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Payment Plan Selector (If item has installment plan) -->
                @if ($availablePlan)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-gray-900 mb-4">Choose Payment Option</h3>

                        <div class="space-y-3">
                            <label class="flex items-start p-4 border rounded-lg cursor-pointer transition {{ $paymentOption === 'full' ? 'border-indigo-600 bg-indigo-50/40 ring-1 ring-indigo-600' : 'border-gray-200 hover:bg-gray-50' }}">
                                <input type="radio" wire:model.live="paymentOption" value="full" class="mt-1 text-indigo-600 focus:ring-indigo-500">
                                <div class="ml-3 flex-1">
                                    <div class="flex justify-between font-bold text-sm text-gray-900">
                                        <span>Full One-Time Payment</span>
                                        <span>100% Upfront</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-0.5">Pay the total balance now with no subsequent installment milestones.</p>
                                </div>
                            </label>

                            <label class="flex items-start p-4 border rounded-lg cursor-pointer transition {{ $paymentOption === 'installment' ? 'border-purple-600 bg-purple-50/40 ring-1 ring-purple-600' : 'border-gray-200 hover:bg-gray-50' }}">
                                <input type="radio" wire:model.live="paymentOption" value="installment" class="mt-1 text-purple-600 focus:ring-purple-500">
                                <div class="ml-3 flex-1">
                                    <div class="flex justify-between font-bold text-sm text-gray-900">
                                        <span class="text-purple-900">{{ $availablePlan->title }}</span>
                                        <span class="text-purple-700 font-bold">{{ $availablePlan->initial_deposit_value }}% Deposit Due Now</span>
                                    </div>
                                    <p class="text-xs text-purple-800 mt-1">
                                        Pay only <strong>{{ $availablePlan->initial_deposit_value }}% today</strong>. The remaining balance becomes due upon verified completion of <strong>{{ $availablePlan->completion_milestone_percentage }}%</strong> of your curriculum!
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>
                @endif

                <!-- Coupon Box -->
                @if ($item->is_coupon_eligible)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-gray-900 mb-3">Have a Discount Coupon?</h3>
                        @if ($appliedCouponCode)
                            <div class="flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-lg p-3">
                                <div class="flex items-center space-x-2">
                                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span class="font-mono font-bold text-sm text-emerald-900">{{ $appliedCouponCode }}</span>
                                    <span class="text-xs text-emerald-700 font-medium">Applied</span>
                                </div>
                                <button wire:click="removeCoupon" class="text-xs font-semibold text-red-600 hover:text-red-800">
                                    Remove
                                </button>
                            </div>
                        @else
                            <div class="flex space-x-2">
                                <input wire:model="couponInput" type="text" placeholder="Enter coupon code (e.g. SAVE20)" class="flex-1 uppercase font-mono text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <button wire:click="applyCoupon" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-bold text-gray-700 bg-gray-50 hover:bg-gray-100">
                                    Apply
                                </button>
                            </div>
                            @if ($couponError)
                                <p class="text-xs text-red-600 mt-2 font-medium">{{ $couponError }}</p>
                            @endif
                        @endif
                    </div>
                @endif
            </div>

            <!-- Right Column: Pre-order Price Snapshot & Checkout Button -->
            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sticky top-6">
                    <div class="flex items-center justify-between border-b pb-3 mb-4">
                        <h3 class="text-base font-bold text-gray-900">Investment Summary</h3>
                        @if ($isCurrencySwitcherEnabled ?? true)
                            <div class="flex items-center space-x-1.5 text-xs text-gray-500 bg-gray-50 px-2 py-1 rounded border border-gray-200">
                                <span>Currency:</span>
                                <select wire:change="setCurrency($event.target.value)" class="text-xs font-bold text-gray-800 bg-transparent border-0 p-0 focus:ring-0 cursor-pointer">
                                    @foreach ($supportedCurrencies as $code => $info)
                                        <option value="{{ $code }}" {{ $activeCurrency === $code ? 'selected' : '' }}>
                                            {{ $code }} ({{ $info['symbol'] }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>

                    @if ($calc)
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between text-gray-600">
                                <span>Original List Price</span>
                                <span>@currency($calc['base_amount'], $calc['currency'])</span>
                            </div>

                            @if ($calc['promo_discount'] > 0)
                                <div class="flex justify-between text-emerald-600 font-medium">
                                    <span>Special Discount</span>
                                    <span>-@currency($calc['promo_discount'], $calc['currency'])</span>
                                </div>
                            @endif

                            @if ($calc['owned_courses_discount'] > 0)
                                <div class="flex justify-between text-blue-600 font-medium">
                                    <span>Owned Courses Deducted</span>
                                    <span>-@currency($calc['owned_courses_discount'], $calc['currency'])</span>
                                </div>
                            @endif

                            @if ($calc['coupon_discount'] > 0)
                                <div class="flex justify-between text-indigo-600 font-medium">
                                    <span>Coupon Discount ({{ $calc['coupon_code'] }})</span>
                                    <span>-@currency($calc['coupon_discount'], $calc['currency'])</span>
                                </div>
                            @endif

                            <div class="border-t border-gray-200 pt-3 flex justify-between items-baseline font-black text-gray-900 text-lg">
                                <span>Order Total</span>
                                <span>@currency($calc['total_amount'], $calc['currency'])</span>
                            </div>

                            @if ($paymentOption === 'installment' && $availablePlan)
                                <div class="bg-purple-50 rounded-lg p-3 border border-purple-100 mt-4 space-y-2">
                                    <div class="flex justify-between text-xs font-bold text-purple-900">
                                        <span>Deposit Due Today</span>
                                        <span class="text-sm font-black">@currency($calc['deposit_due_now'], $calc['currency'])</span>
                                    </div>
                                    <div class="flex justify-between text-2xs text-purple-700">
                                        <span>Remaining Balance</span>
                                        <span>@currency($calc['total_amount'] - $calc['deposit_due_now'], $calc['currency'])</span>
                                    </div>
                                    <div class="text-2xs text-purple-600 border-t border-purple-200/60 pt-1.5">
                                        Balance is automatically deferred until you reach {{ $availablePlan->completion_milestone_percentage }}% course progress!
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Checkout Button -->
                        <div class="mt-6">
                            @auth
                                @if (! auth()->user()->hasRole('student'))
                                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-xs text-amber-900 space-y-2">
                                        <div class="font-bold flex items-center space-x-1.5 text-amber-800">
                                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                            <span>Staff Account Notice</span>
                                        </div>
                                        <p class="leading-relaxed">
                                            You are signed in as an administrator or trainer. Staff accounts cannot purchase courses or bundles directly.
                                        </p>
                                        @if (auth()->user()->hasRole('admin'))
                                            <a href="{{ route('admin.orders.index') }}" class="inline-block font-bold text-indigo-700 hover:underline">
                                                Assign directly to a student via Admin Orders &rarr;
                                            </a>
                                        @endif
                                    </div>
                                @else
                                    <button wire:click="processCheckout" wire:loading.attr="disabled" class="w-full flex items-center justify-center px-6 py-3.5 border border-transparent rounded-xl shadow-md text-base font-bold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50">
                                        <span wire:loading.remove>
                                            @if ($calc['total_amount'] <= 0)
                                                Complete Free Enrollment
                                            @elseif ($paymentOption === 'installment')
                                                Pay Deposit: @currency($calc['deposit_due_now'], $calc['currency'])
                                            @else
                                                Pay Now: @currency($calc['total_amount'], $calc['currency'])
                                            @endif
                                        </span>
                                        <span wire:loading class="flex items-center">
                                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            Processing Order...
                                        </span>
                                    </button>
                                @endif
                            @else
                                <a href="{{ route('login') }}" class="w-full flex items-center justify-center px-6 py-3.5 border border-transparent rounded-xl shadow-md text-base font-bold text-white bg-indigo-600 hover:bg-indigo-700 text-center">
                                    Sign In to Complete Enrollment
                                </a>
                            @endauth
                        </div>
                    @else
                        <!-- Visitor not logged in calculation preview -->
                        <div class="text-sm text-gray-500 italic py-4 text-center">
                            Please sign in to compute customized discounts and eligible installment options.
                        </div>
                        <a href="{{ route('login') }}" class="w-full flex items-center justify-center px-6 py-3 border border-transparent rounded-xl text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 text-center">
                            Sign In to Enroll
                        </a>
                    @endif

                    <div class="mt-4 text-center">
                        <span class="text-2xs text-gray-400 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Encrypted 256-Bit SSL Checkout • Razorpay Verified
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('initiate-razorpay-checkout', (data) => {
            const payload = data[0];
            if (typeof Razorpay === 'undefined') {
                alert('The Razorpay checkout script could not be loaded. Please ensure you are connected to the internet and disable any ad blockers.');
                return;
            }

            const options = {
                key: payload.key,
                amount: payload.amount,
                currency: payload.currency,
                name: payload.name,
                description: payload.description,
                order_id: payload.razorpay_order_id,
                prefill: {
                    name: payload.prefill_name,
                    email: payload.prefill_email,
                },
                theme: {
                    color: '#4f46e5'
                },
                modal: {
                    ondismiss: function () {
                        if (window.Livewire) {
                            @this.call('handlePaymentCancelled', payload.order_id);
                        }
                    }
                },
                handler: function (response) {
                    @this.call('verifyRazorpayPayment',
                        payload.order_id,
                        response.razorpay_payment_id,
                        response.razorpay_order_id,
                        response.razorpay_signature
                    );
                }
            };
            const rzp = new Razorpay(options);
            rzp.on('payment.failed', function (resp) {
                if (window.Livewire) {
                    @this.call('handlePaymentCancelled', payload.order_id);
                }
            });
            rzp.open();
        });
    });
</script>
