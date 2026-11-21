<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 pb-4 border-b border-gray-200">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Orders & Enrollments Commerce</h1>
                <p class="text-sm text-gray-600 mt-1">Manage student course and bundle purchases, offline payments, installment schedules, and direct manual enrollment.</p>
            </div>
            <div class="mt-4 md:mt-0 flex items-center space-x-3">
                <a href="{{ route('admin.reports.commerce') }}" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none">
                    <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Commerce Reports
                </a>
                <button wire:click="openAssignModal" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    Direct Student Assignment
                </button>
            </div>
        </div>

        @if (session()->has('success'))
            <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3 rounded-md flex items-center">
                <svg class="w-5 h-5 mr-2 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ session('success') }}
            </div>
        @endif

        <!-- Filter Bar -->
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="relative flex-1 max-w-md">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search orders by number, student name, email..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <label class="text-xs font-semibold text-gray-600 uppercase">Payment Status:</label>
                <select wire:model.live="statusFilter" class="border border-gray-300 rounded-md text-sm py-1.5 px-3 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="all">All Orders</option>
                    <option value="paid">Paid</option>
                    <option value="partially_paid">Partially Paid</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="complimentary">Complimentary / Scholarship</option>
                    <option value="refunded">Refunded</option>
                </select>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="bg-white shadow overflow-hidden sm:rounded-md border border-gray-200">
            @if ($orders->isEmpty())
                <div class="p-12 text-center text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    <p class="mt-2 text-base font-medium text-gray-900">No orders found</p>
                    <p class="text-sm text-gray-500">Orders placed by students or assigned by administrators will appear here.</p>
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Target Item</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total / Balance</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($orders as $order)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-mono text-sm font-bold text-gray-900">{{ $order->order_number }}</div>
                                    <div class="text-xs text-gray-500">{{ $order->created_at->format('M d, Y H:i') }}</div>
                                    @if ($order->is_installment)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-2xs font-semibold bg-purple-50 text-purple-700 border border-purple-200">
                                            Installment Plan
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $order->user->name ?? 'User #' . $order->user_id }}</div>
                                    <div class="text-xs text-gray-500">{{ $order->user->email ?? '' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-gray-900">{{ $order->target_title }}</div>
                                    <div class="text-xs text-gray-500 capitalize">{{ $order->orderable_type }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-gray-900">@currency($order->total_amount, $order->currency)</div>
                                    @if ($order->outstanding_balance > 0)
                                        <div class="text-xs font-medium text-amber-600">Due: @currency($order->outstanding_balance, $order->currency)</div>
                                    @else
                                        <div class="text-xs text-emerald-600">Paid: @currency($order->paid_amount, $order->currency)</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                        {{ $order->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800' :
                                           ($order->payment_status === 'partially_paid' ? 'bg-blue-100 text-blue-800' :
                                           ($order->payment_status === 'complimentary' ? 'bg-purple-100 text-purple-800' :
                                           ($order->payment_status === 'refunded' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'))) }}">
                                        {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                                    </span>
                                    @if ($order->payment_method)
                                        <div class="text-2xs text-gray-500 mt-0.5 uppercase">{{ str_replace('_', ' ', $order->payment_method) }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                    <button wire:click="viewOrder({{ $order->id }})" class="text-indigo-600 hover:text-indigo-900">
                                        View Details
                                    </button>
                                    @if ($order->outstanding_balance > 0)
                                        <button wire:click="openPaymentModal({{ $order->id }})" class="text-emerald-600 hover:text-emerald-900 font-semibold">
                                            Record Payment
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>

        <!-- Order Detail Modal -->
        @if ($showDetailModal && $selectedOrder)
            <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showDetailModal', false)"></div>

                    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Order #{{ $selectedOrder->order_number }}</h3>
                                <p class="text-xs text-gray-500">Created on {{ $selectedOrder->created_at->format('M d, Y H:i:s') }}</p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                {{ $selectedOrder->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800' :
                                   ($selectedOrder->payment_status === 'partially_paid' ? 'bg-blue-100 text-blue-800' :
                                   ($selectedOrder->payment_status === 'complimentary' ? 'bg-purple-100 text-purple-800' : 'bg-amber-100 text-amber-800')) }}">
                                {{ ucfirst(str_replace('_', ' ', $selectedOrder->payment_status)) }}
                            </span>
                        </div>

                        <div class="p-6 space-y-6">
                            <!-- Student & Target Info -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <div>
                                    <div class="text-xs font-semibold text-gray-500 uppercase">Student</div>
                                    <div class="text-sm font-bold text-gray-900">{{ $selectedOrder->user->name ?? 'User #' . $selectedOrder->user_id }}</div>
                                    <div class="text-xs text-gray-600">{{ $selectedOrder->user->email ?? '' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-semibold text-gray-500 uppercase">Enrolled Item</div>
                                    <div class="text-sm font-bold text-gray-900">{{ $selectedOrder->target_title }}</div>
                                    <div class="text-xs text-gray-600 capitalize">Type: {{ $selectedOrder->orderable_type }}</div>
                                </div>
                            </div>

                            <!-- Financial Breakdown -->
                            <div>
                                <h4 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-2">Financial Breakdown</h4>
                                <div class="bg-white border border-gray-200 rounded-md p-4 space-y-2 text-sm">
                                    <div class="flex justify-between text-gray-600">
                                        <span>Base Price</span>
                                        <span>@currency($selectedOrder->base_amount, $selectedOrder->currency)</span>
                                    </div>
                                    @if ($selectedOrder->discount_amount > 0)
                                        <div class="flex justify-between text-emerald-600">
                                            <span>Promotional Discount</span>
                                            <span>-@currency($selectedOrder->discount_amount, $selectedOrder->currency)</span>
                                        </div>
                                    @endif
                                    @if ($selectedOrder->owned_courses_discount > 0)
                                        <div class="flex justify-between text-blue-600">
                                            <span>Owned Courses Deducted</span>
                                            <span>-@currency($selectedOrder->owned_courses_discount, $selectedOrder->currency)</span>
                                        </div>
                                    @endif
                                    @if ($selectedOrder->coupon_discount_amount > 0)
                                        <div class="flex justify-between text-indigo-600">
                                            <span>Coupon Discount ({{ $selectedOrder->coupon_code }})</span>
                                            <span>-@currency($selectedOrder->coupon_discount_amount, $selectedOrder->currency)</span>
                                        </div>
                                    @endif
                                    <div class="border-t pt-2 flex justify-between font-bold text-gray-900 text-base">
                                        <span>Total Amount</span>
                                        <span>@currency($selectedOrder->total_amount, $selectedOrder->currency)</span>
                                    </div>
                                    <div class="flex justify-between text-emerald-700 font-semibold">
                                        <span>Paid Amount</span>
                                        <span>@currency($selectedOrder->paid_amount, $selectedOrder->currency)</span>
                                    </div>
                                    <div class="flex justify-between text-amber-700 font-semibold">
                                        <span>Outstanding Balance</span>
                                        <span>@currency($selectedOrder->outstanding_balance, $selectedOrder->currency)</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Installment Schedule -->
                            @if ($selectedOrder->is_installment && $selectedOrder->installments->isNotEmpty())
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Installment Schedule</h4>
                                        <span class="text-xs text-purple-700 font-semibold">{{ $selectedOrder->terms_snapshot['installment_plan'] ?? 'Milestone Plan' }}</span>
                                    </div>
                                    <div class="border border-gray-200 rounded-md overflow-hidden">
                                        <table class="min-w-full divide-y divide-gray-200 text-xs">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-gray-500">#</th>
                                                    <th class="px-4 py-2 text-left text-gray-500">Condition</th>
                                                    <th class="px-4 py-2 text-left text-gray-500">Amount</th>
                                                    <th class="px-4 py-2 text-left text-gray-500">Paid</th>
                                                    <th class="px-4 py-2 text-left text-gray-500">Due Date</th>
                                                    <th class="px-4 py-2 text-left text-gray-500">Status</th>
                                                    <th class="px-4 py-2 text-right text-gray-500">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 bg-white">
                                                @foreach ($selectedOrder->installments as $inst)
                                                    <tr>
                                                        <td class="px-4 py-2 font-bold">{{ $inst->installment_number }}</td>
                                                        <td class="px-4 py-2">
                                                            @if ($inst->due_condition === 'completion_milestone')
                                                                <span class="text-purple-700 font-semibold">{{ $inst->milestone_percentage }}% Milestone</span>
                                                            @elseif ($inst->due_condition === 'initial_deposit')
                                                                <span>Initial Deposit</span>
                                                            @else
                                                                <span>Scheduled</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-4 py-2 font-semibold">@currency($inst->amount, $inst->currency)</td>
                                                        <td class="px-4 py-2">@currency($inst->paid_amount, $inst->currency)</td>
                                                        <td class="px-4 py-2">{{ $inst->due_date ? $inst->due_date->format('M d, Y') : 'Upon milestone' }}</td>
                                                        <td class="px-4 py-2">
                                                            <span class="px-2 py-0.5 rounded text-2xs font-semibold
                                                                {{ $inst->status === 'paid' ? 'bg-emerald-100 text-emerald-800' :
                                                                   ($inst->status === 'due' ? 'bg-amber-100 text-amber-800' :
                                                                   ($inst->status === 'overdue' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700')) }}">
                                                                {{ ucfirst($inst->status) }}
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-2 text-right">
                                                            @if ($inst->status !== 'paid')
                                                                <button wire:click="openPaymentModal({{ $selectedOrder->id }}, {{ $inst->id }})" class="text-emerald-600 hover:text-emerald-900 font-bold">
                                                                    Pay
                                                                </button>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif

                            <!-- Transactions Log -->
                            <div>
                                <h4 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-2">Payment Transactions Log</h4>
                                @if ($selectedOrder->transactions->isEmpty())
                                    <p class="text-xs text-gray-500 italic">No transactions recorded yet.</p>
                                @else
                                    <div class="border border-gray-200 rounded-md overflow-hidden">
                                        <table class="min-w-full divide-y divide-gray-200 text-xs">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-gray-500">Date</th>
                                                    <th class="px-4 py-2 text-left text-gray-500">Gateway</th>
                                                    <th class="px-4 py-2 text-left text-gray-500">Reference</th>
                                                    <th class="px-4 py-2 text-left text-gray-500">Amount</th>
                                                    <th class="px-4 py-2 text-left text-gray-500">Status</th>
                                                    <th class="px-4 py-2 text-left text-gray-500">Recorded By</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 bg-white">
                                                @foreach ($selectedOrder->transactions as $txn)
                                                    <tr>
                                                        <td class="px-4 py-2">{{ $txn->created_at->format('M d, Y H:i') }}</td>
                                                        <td class="px-4 py-2 uppercase font-medium">{{ $txn->payment_gateway }}</td>
                                                        <td class="px-4 py-2 font-mono">{{ $txn->reference_number ?: '-' }}</td>
                                                        <td class="px-4 py-2 font-bold">@currency($txn->amount, $txn->currency)</td>
                                                        <td class="px-4 py-2">
                                                            <span class="text-emerald-600 font-semibold">{{ ucfirst($txn->status) }}</span>
                                                        </td>
                                                        <td class="px-4 py-2 text-gray-500">{{ $txn->recordedBy->name ?? 'System' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="bg-gray-50 px-6 py-3 flex items-center justify-between border-t border-gray-200">
                            <div>
                                @if ($selectedOrder->outstanding_balance > 0)
                                    <button wire:click="openPaymentModal({{ $selectedOrder->id }})" class="px-4 py-2 rounded-md shadow-sm text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700">
                                        Record Payment
                                    </button>
                                @endif
                            </div>
                            <button type="button" wire:click="$set('showDetailModal', false)" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Direct Admin Assignment Modal -->
        @if ($showAssignModal)
            <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showAssignModal', false)"></div>

                    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full">
                        <form wire:submit.prevent="executeAssign">
                            <div class="bg-white px-6 pt-5 pb-4">
                                <h3 class="text-lg font-bold text-gray-900 border-b pb-3 mb-4">
                                    Direct Student Enrollment & Payment Assignment
                                </h3>

                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Select Student *</label>
                                        <select wire:model="assignStudentId" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                                            <option value="">-- Choose Student --</option>
                                            @foreach ($students as $stu)
                                                <option value="{{ $stu->id }}">{{ $stu->name }} ({{ $stu->email }})</option>
                                            @endforeach
                                        </select>
                                        @error('assignStudentId') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Item Type *</label>
                                            <select wire:model.live="assignItemType" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                <option value="course">Individual Course</option>
                                                <option value="bundle">Course Bundle</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Select Item *</label>
                                            <select wire:model="assignItemId" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                                                <option value="">-- Select --</option>
                                                @if ($assignItemType === 'course')
                                                    @foreach ($courses as $c)
                                                        <option value="{{ $c->id }}">{{ $c->title }} (@currency($c->price, $c->currency))</option>
                                                    @endforeach
                                                @else
                                                    @foreach ($bundles as $b)
                                                        <option value="{{ $b->id }}">{{ $b->title }} (@currency($b->effective_price, $b->currency))</option>
                                                    @endforeach
                                                @endif
                                            </select>
                                            @error('assignItemId') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Payment / Assignment Method *</label>
                                            <select wire:model.live="assignPaymentMethod" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                <option value="complimentary">Complimentary / Free Grant</option>
                                                <option value="scholarship">Full Scholarship</option>
                                                <option value="sponsored">Corporate Sponsored</option>
                                                <option value="cash">Cash (Offline Payment)</option>
                                                <option value="bank_transfer">Bank Wire Transfer</option>
                                                <option value="manual_offline">Manual / Other Offline</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Amount Collected (If Offline Paid)</label>
                                            <input wire:model="assignAmountPaid" type="number" step="0.01" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. 150.00" {{ in_array($assignPaymentMethod, ['complimentary', 'scholarship', 'sponsored']) ? 'disabled' : '' }}>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Assignment Reason</label>
                                        <input wire:model="assignReason" type="text" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. Merit scholarship or In-office payment">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Internal Notes</label>
                                        <textarea wire:model="assignNotes" rows="2" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Receipt number, bank transaction ID, or scholarship board notes..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gray-50 px-6 py-3 flex items-center justify-end space-x-3 rounded-b-lg">
                                <button type="button" wire:click="$set('showAssignModal', false)" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100">
                                    Cancel
                                </button>
                                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                    Confirm & Enroll Student
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        <!-- Record Offline Payment Modal -->
        @if ($showPaymentModal)
            <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showPaymentModal', false)"></div>

                    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                        <form wire:submit.prevent="executeRecordPayment">
                            <div class="bg-white px-6 pt-5 pb-4">
                                <h3 class="text-lg font-bold text-gray-900 border-b pb-3 mb-4">
                                    Record Offline Payment
                                </h3>

                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Payment Amount *</label>
                                        <input wire:model="paymentAmount" type="number" step="0.01" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                                        @error('paymentAmount') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Payment Method *</label>
                                        <select wire:model="paymentMethod" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                            <option value="cash">Cash in Person</option>
                                            <option value="bank_transfer">Bank Wire Transfer</option>
                                            <option value="manual_offline">Manual POS / Other</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Receipt / Reference Number</label>
                                        <input wire:model="paymentReference" type="text" class="mt-1 block w-full font-mono border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Notes</label>
                                        <textarea wire:model="paymentNotes" rows="2" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gray-50 px-6 py-3 flex items-center justify-end space-x-3 rounded-b-lg">
                                <button type="button" wire:click="$set('showPaymentModal', false)" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100">
                                    Cancel
                                </button>
                                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700">
                                    Record Payment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
