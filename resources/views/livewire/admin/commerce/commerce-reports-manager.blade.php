<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 pb-4 border-b border-gray-200">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Commerce & Revenue Reports</h1>
                <p class="text-sm text-gray-600 mt-1">Financial reconciliation, verified collected revenues, and outstanding installment balances.</p>
            </div>
            <div class="mt-4 md:mt-0 flex items-center space-x-3">
                <select wire:model.live="period" class="border border-gray-300 rounded-md text-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="all">All Time</option>
                    <option value="this_month">This Month</option>
                    <option value="30days">Last 30 Days</option>
                </select>
                <button wire:click="exportCsv" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export Safe CSV
                </button>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <!-- Verified Revenue -->
            <div class="bg-white overflow-hidden shadow rounded-lg border border-gray-200 p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-emerald-100 rounded-md p-3">
                        <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="ml-4 w-0 flex-1">
                        <dl>
                            <dt class="text-xs font-semibold text-gray-500 uppercase truncate">Verified Net Revenue</dt>
                            <dd class="text-2xl font-black text-gray-900 mt-1">@currency($netRevenue)</dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-3 text-2xs text-gray-500 border-t pt-2">
                    Excludes complimentary & scholarship orders.
                </div>
            </div>

            <!-- Outstanding Balance -->
            <div class="bg-white overflow-hidden shadow rounded-lg border border-gray-200 p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-amber-100 rounded-md p-3">
                        <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="ml-4 w-0 flex-1">
                        <dl>
                            <dt class="text-xs font-semibold text-gray-500 uppercase truncate">Outstanding Receivables</dt>
                            <dd class="text-2xl font-black text-amber-600 mt-1">@currency($totalOutstanding)</dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-3 text-2xs text-gray-500 border-t pt-2">
                    Pending installment & partial payments.
                </div>
            </div>

            <!-- Total Orders -->
            <div class="bg-white overflow-hidden shadow rounded-lg border border-gray-200 p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-indigo-100 rounded-md p-3">
                        <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    </div>
                    <div class="ml-4 w-0 flex-1">
                        <dl>
                            <dt class="text-xs font-semibold text-gray-500 uppercase truncate">Total Orders Processed</dt>
                            <dd class="text-2xl font-black text-gray-900 mt-1">{{ $totalOrdersCount }}</dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-3 text-2xs text-gray-500 border-t pt-2 flex justify-between">
                    <span>Paid: {{ $paidOrdersCount }}</span>
                    <span>Partial: {{ $partialOrdersCount }}</span>
                    <span>Grant: {{ $complimentaryOrdersCount }}</span>
                </div>
            </div>

            <!-- Coupon Savings -->
            <div class="bg-white overflow-hidden shadow rounded-lg border border-gray-200 p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-purple-100 rounded-md p-3">
                        <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    </div>
                    <div class="ml-4 w-0 flex-1">
                        <dl>
                            <dt class="text-xs font-semibold text-gray-500 uppercase truncate">Promotional Discounts</dt>
                            <dd class="text-2xl font-black text-purple-700 mt-1">@currency($totalCouponSavings)</dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-3 text-2xs text-gray-500 border-t pt-2">
                    Across {{ $couponRedemptionsCount }} coupon redemptions.
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="bg-white shadow rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-base font-bold text-gray-900">Recent Payment Transactions</h3>
                <a href="{{ route('admin.orders.index') }}" class="text-xs text-indigo-600 font-semibold hover:text-indigo-800">
                    View All Orders &rarr;
                </a>
            </div>
            @if ($recentTransactions->isEmpty())
                <div class="p-8 text-center text-gray-500 text-sm">
                    No transactions recorded for the selected period.
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gateway / Ref</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach ($recentTransactions as $txn)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">
                                    {{ $txn->paid_at ? $txn->paid_at->format('M d, Y H:i') : $txn->created_at->format('M d, Y H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900">{{ $txn->user->name ?? 'User #' . $txn->user_id }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-xs text-indigo-600">
                                    {{ $txn->order->order_number ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 uppercase">
                                        {{ $txn->payment_gateway }}
                                    </span>
                                    @if ($txn->reference_number)
                                        <span class="text-xs text-gray-500 ml-1">({{ $txn->reference_number }})</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-bold text-emerald-600">
                                    @currency($txn->amount, $txn->currency)
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
