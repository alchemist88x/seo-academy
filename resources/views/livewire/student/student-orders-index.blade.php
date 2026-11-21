<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8 pb-4 border-b border-gray-200">
            <h1 class="text-2xl font-bold text-gray-900">My Orders & Payments</h1>
            <p class="text-sm text-gray-600 mt-1">Review your enrollment orders, receipts, and track upcoming installment milestones.</p>
        </div>

        @if (session()->has('success'))
            <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3 rounded-lg flex items-center">
                <svg class="w-5 h-5 mr-2 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ session('success') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mb-6 bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-lg flex items-center">
                <svg class="w-5 h-5 mr-2 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('error') }}
            </div>
        @endif

        @if ($orders->isEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center text-gray-500">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                <p class="mt-2 text-base font-medium text-gray-900">No orders placed yet</p>
                <p class="text-sm text-gray-500 mt-1">Browse our course bundles and individual curriculum to get started.</p>
                <a href="{{ route('bundles.public.index') }}" class="mt-4 inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                    Explore Bundles
                </a>
            </div>
        @else
            <div class="space-y-6">
                @foreach ($orders as $order)
                    @php
                        $progress = $orderProgress[$order->id] ?? 0.0;
                    @endphp
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <!-- Card Header -->
                        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div>
                                <span class="text-xs text-gray-500">Order Placed: {{ $order->created_at->format('M d, Y') }}</span>
                                <div class="font-mono text-sm font-bold text-gray-900">{{ $order->order_number }}</div>
                            </div>
                            <div class="flex items-center space-x-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                    {{ $order->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800' :
                                       ($order->payment_status === 'partially_paid' ? 'bg-blue-100 text-blue-800' :
                                       ($order->payment_status === 'complimentary' ? 'bg-purple-100 text-purple-800' : 'bg-amber-100 text-amber-800')) }}">
                                    {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                                </span>
                                <a href="{{ route('student.orders.show', $order->id) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                    View Receipt &rarr;
                                </a>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="p-6">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                <div>
                                    <span class="text-2xs font-semibold uppercase tracking-wider text-gray-500">{{ $order->orderable_type }}</span>
                                    <h3 class="text-lg font-bold text-gray-900">{{ $order->target_title }}</h3>
                                    <div class="mt-1 flex items-center space-x-3 text-xs text-gray-600">
                                        <span>Total: <strong>@currency($order->total_amount, $order->currency)</strong></span>
                                        <span>&bull;</span>
                                        <span>Paid: <strong>@currency($order->paid_amount, $order->currency)</strong></span>
                                        @if ($order->outstanding_balance > 0)
                                            <span>&bull;</span>
                                            <span class="text-amber-600 font-bold">Due: @currency($order->outstanding_balance, $order->currency)</span>
                                        @endif
                                    </div>
                                </div>

                                @if ($order->is_installment)
                                    <!-- Milestone Progress Bar -->
                                    <div class="w-full md:w-72 bg-purple-50 p-3 rounded-lg border border-purple-100">
                                        @php
                                            $milestoneInst = $order->installments->firstWhere('due_condition', 'completion_milestone');
                                            $threshold = $milestoneInst ? (float) $milestoneInst->milestone_percentage : 60.0;
                                        @endphp
                                        <div class="flex justify-between text-xs font-bold text-purple-900 mb-1">
                                            <span>Course Progress</span>
                                            <span>{{ round($progress) }}% / {{ round($threshold) }}%</span>
                                        </div>
                                        <div class="w-full bg-purple-200 rounded-full h-2">
                                            <div class="bg-purple-600 h-2 rounded-full transition-all duration-500" style="width: {{ min(100, $progress) }}%"></div>
                                        </div>
                                        <div class="text-2xs text-purple-700 mt-1">
                                            @if ($progress >= $threshold)
                                                Milestone achieved! Installment is now due.
                                            @else
                                                Reach {{ round($threshold) }}% progress to unlock your final payment milestone.
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <!-- Installment Schedule for Student -->
                            @if ($order->is_installment && $order->installments->isNotEmpty())
                                <div class="mt-6 pt-4 border-t border-gray-100">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3">Installment Schedule</h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        @foreach ($order->installments as $inst)
                                            <div class="p-3.5 rounded-lg border {{ $inst->status === 'paid' ? 'bg-emerald-50/50 border-emerald-200' : ($inst->status === 'due' ? 'bg-amber-50/50 border-amber-300 ring-1 ring-amber-300' : 'bg-gray-50 border-gray-200') }} flex items-center justify-between">
                                                <div>
                                                    <div class="flex items-center space-x-2">
                                                        <span class="text-xs font-bold text-gray-900">Installment #{{ $inst->installment_number }}</span>
                                                        <span class="px-1.5 py-0.5 rounded text-2xs font-semibold
                                                            {{ $inst->status === 'paid' ? 'bg-emerald-100 text-emerald-800' :
                                                               ($inst->status === 'due' ? 'bg-amber-100 text-amber-800' :
                                                               ($inst->status === 'overdue' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600')) }}">
                                                            {{ ucfirst($inst->status) }}
                                                        </span>
                                                    </div>
                                                    <div class="text-xs text-gray-600 mt-0.5">
                                                        Amount: <strong>@currency($inst->amount, $inst->currency)</strong>
                                                    </div>
                                                    <div class="text-2xs text-gray-500">
                                                        @if ($inst->due_condition === 'completion_milestone')
                                                            Trigger: Upon {{ $inst->milestone_percentage }}% completion
                                                        @elseif ($inst->due_date)
                                                            Due: {{ $inst->due_date->format('M d, Y') }}
                                                        @endif
                                                    </div>
                                                </div>

                                                <div>
                                                    @if ($inst->status === 'due' || $inst->status === 'overdue')
                                                        <button wire:click="payInstallment({{ $order->id }}, {{ $inst->id }})" class="px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm">
                                                            Pay Now
                                                        </button>
                                                    @elseif ($inst->status === 'paid')
                                                        <span class="text-xs text-emerald-600 font-bold flex items-center">
                                                            <svg class="w-4 h-4 mr-1 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                            Paid
                                                        </span>
                                                    @else
                                                        <span class="text-2xs text-gray-400 font-medium">Upcoming</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('initiate-razorpay-checkout', (data) => {
            const payload = data[0];
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
            rzp.open();
        });
    });
</script>
