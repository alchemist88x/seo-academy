<div class="py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Navigation -->
        <div class="mb-6">
            <a href="{{ route('student.orders.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Back to All Orders
            </a>
        </div>

        @if (session()->has('success'))
            <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3 rounded-lg flex items-center">
                <svg class="w-5 h-5 mr-2 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ session('success') }}
            </div>
        @endif

        <!-- Receipt Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <!-- Header -->
            <div class="bg-gray-50 p-6 sm:p-8 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-600">Official Receipt</span>
                    <h1 class="text-2xl font-black text-gray-900 mt-1">Order #{{ $order->order_number }}</h1>
                    <p class="text-xs text-gray-500 mt-0.5">Purchased on {{ $order->created_at->format('M d, Y \a\t H:i') }}</p>
                </div>
                <div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold
                        {{ $order->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800' :
                           ($order->payment_status === 'partially_paid' ? 'bg-blue-100 text-blue-800' :
                           ($order->payment_status === 'complimentary' ? 'bg-purple-100 text-purple-800' : 'bg-amber-100 text-amber-800')) }}">
                        {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                    </span>
                </div>
            </div>

            <!-- Receipt Content -->
            <div class="p-6 sm:p-8 space-y-8">
                <!-- Enrolled Course / Bundle -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Curriculum Enrolled</h3>
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 flex items-center justify-between">
                        <div>
                            <span class="text-2xs uppercase font-bold text-gray-500">{{ $order->orderable_type }}</span>
                            <div class="text-base font-bold text-gray-900">{{ $order->target_title }}</div>
                        </div>
                        <a href="{{ route('student.courses.index') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 bg-white px-3 py-1.5 rounded-lg border border-gray-200 shadow-sm">
                            Go to Learning Classroom &rarr;
                        </a>
                    </div>
                </div>

                <!-- Breakdown -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3">Price Breakdown</h3>
                    <div class="border border-gray-200 rounded-xl p-4 space-y-2 text-sm bg-white">
                        <div class="flex justify-between text-gray-600">
                            <span>Base List Price</span>
                            <span>{{ $order->currency }} {{ number_format($order->base_amount, 2) }}</span>
                        </div>
                        @if ($order->discount_amount > 0)
                            <div class="flex justify-between text-emerald-600">
                                <span>Promotional Discount</span>
                                <span>-@currency($order->discount_amount, $order->currency)</span>
                            </div>
                        @endif
                        @if ($order->owned_courses_discount > 0)
                            <div class="flex justify-between text-blue-600">
                                <span>Owned Courses Deduction</span>
                                <span>-@currency($order->owned_courses_discount, $order->currency)</span>
                            </div>
                        @endif
                        @if ($order->coupon_discount_amount > 0)
                            <div class="flex justify-between text-indigo-600">
                                <span>Coupon ({{ $order->coupon_code }})</span>
                                <span>-@currency($order->coupon_discount_amount, $order->currency)</span>
                            </div>
                        @endif
                        <div class="border-t pt-2 flex justify-between font-black text-gray-900 text-base">
                            <span>Total Order Investment</span>
                            <span>@currency($order->total_amount, $order->currency)</span>
                        </div>
                        <div class="flex justify-between text-emerald-700 font-semibold">
                            <span>Amount Paid to Date</span>
                            <span>@currency($order->paid_amount, $order->currency)</span>
                        </div>
                        @if ($order->outstanding_balance > 0)
                            <div class="flex justify-between text-amber-700 font-semibold">
                                <span>Remaining Balance Due</span>
                                <span>@currency($order->outstanding_balance, $order->currency)</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Installments -->
                @if ($order->is_installment && $order->installments->isNotEmpty())
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3">Installment Milestone Breakdown</h3>
                        <div class="space-y-3">
                            @foreach ($order->installments as $inst)
                                <div class="p-4 rounded-xl border {{ $inst->status === 'paid' ? 'bg-emerald-50/40 border-emerald-200' : 'bg-gray-50 border-gray-200' }} flex items-center justify-between">
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm font-bold text-gray-900">Installment #{{ $inst->installment_number }}</span>
                                            <span class="px-2 py-0.5 rounded text-2xs font-bold
                                                {{ $inst->status === 'paid' ? 'bg-emerald-100 text-emerald-800' :
                                                   ($inst->status === 'due' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700') }}">
                                                {{ ucfirst($inst->status) }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            @if ($inst->due_condition === 'completion_milestone')
                                                Trigger Condition: Verified {{ $inst->milestone_percentage }}% course completion milestone
                                            @elseif ($inst->due_date)
                                                Due: {{ $inst->due_date->format('M d, Y') }}
                                            @else
                                                Initial Deposit
                                            @endif
                                        </div>
                                    </div>

                                    <div class="flex items-center space-x-4">
                                        <div class="text-right">
                                            <div class="text-sm font-bold text-gray-900">@currency($inst->amount, $inst->currency)</div>
                                            @if ($inst->status === 'paid')
                                                <div class="text-2xs text-emerald-600 font-medium">Paid {{ $inst->paid_at ? $inst->paid_at->format('M d, Y') : '' }}</div>
                                            @endif
                                        </div>
                                        @if ($inst->status === 'due' || $inst->status === 'overdue')
                                            <button wire:click="payInstallment({{ $inst->id }})" class="px-4 py-2 rounded-lg text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm">
                                                Pay Now
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
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
                    @this.call('verifyInstallmentPayment',
                        payload.order_id,
                        payload.installment_id,
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
