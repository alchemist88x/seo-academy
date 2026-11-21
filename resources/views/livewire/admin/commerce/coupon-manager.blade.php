<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 pb-4 border-b border-gray-200">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Discount Coupons</h1>
                <p class="text-sm text-gray-600 mt-1">Configure percentage or fixed promotional coupons, usage limits, and course/bundle eligibility.</p>
            </div>
            <div class="mt-4 md:mt-0">
                <button wire:click="openCreateModal" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Create Coupon
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
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by coupon code or description..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <label class="text-xs font-semibold text-gray-600 uppercase">Status:</label>
                <select wire:model.live="statusFilter" class="border border-gray-300 rounded-md text-sm py-1.5 px-3 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="all">All Coupons</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive Only</option>
                </select>
            </div>
        </div>

        <!-- Coupons Table -->
        <div class="bg-white shadow overflow-hidden sm:rounded-md border border-gray-200">
            @if ($coupons->isEmpty())
                <div class="p-12 text-center text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    <p class="mt-2 text-base font-medium text-gray-900">No discount coupons found</p>
                    <p class="text-sm text-gray-500">Create promotional codes to offer discounts to students during checkout.</p>
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Coupon Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Validity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($coupons as $coupon)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center space-x-2">
                                        <span class="font-mono font-bold text-sm text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded border border-indigo-200">
                                            {{ $coupon->code }}
                                        </span>
                                        @if ($coupon->first_purchase_only)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200" title="First purchase only">
                                                1st Order Only
                                            </span>
                                        @endif
                                    </div>
                                    @if ($coupon->description)
                                        <div class="text-xs text-gray-500 mt-1 max-w-xs truncate">{{ $coupon->description }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="font-bold">
                                        @if ($coupon->discount_type === 'percentage')
                                            {{ $coupon->discount_value }}% OFF
                                        @else
                                            @currency($coupon->discount_value, $coupon->currency) OFF
                                        @endif
                                    </span>
                                    @if ($coupon->max_discount_amount)
                                        <div class="text-xs text-gray-500">Max: @currency($coupon->max_discount_amount, $coupon->currency)</div>
                                    @endif
                                    @if ($coupon->min_purchase_amount)
                                        <div class="text-xs text-gray-500">Min spend: @currency($coupon->min_purchase_amount, $coupon->currency)</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <div class="font-medium text-gray-900">{{ $coupon->usage_count }} redeemed</div>
                                    <div class="text-xs text-gray-500">
                                        Limit: {{ $coupon->global_usage_limit ? $coupon->global_usage_limit : 'Unlimited' }}
                                        (Max {{ $coupon->per_student_usage_limit }}/student)
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">
                                    @if ($coupon->expires_at)
                                        <div class="{{ $coupon->expires_at < now() ? 'text-red-600 font-semibold' : '' }}">
                                            Expires: {{ $coupon->expires_at->format('M d, Y') }}
                                        </div>
                                    @else
                                        <span class="text-gray-400">No expiration</span>
                                    @endif
                                    @if ($coupon->starts_at && $coupon->starts_at > now())
                                        <div class="text-amber-600">Starts: {{ $coupon->starts_at->format('M d, Y') }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button wire:click="toggleStatus({{ $coupon->id }})" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $coupon->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $coupon->is_active ? 'Active' : 'Disabled' }}
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                    <button wire:click="editCoupon({{ $coupon->id }})" class="text-indigo-600 hover:text-indigo-900">Edit</button>
                                    <button wire:click="deleteCoupon({{ $coupon->id }})" wire:confirm="Are you sure you want to delete this coupon?" class="text-red-600 hover:text-red-900">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $coupons->links() }}
                </div>
            @endif
        </div>

        <!-- Coupon Modal -->
        @if ($showModal)
            <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showModal', false)"></div>

                    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full">
                        <form wire:submit.prevent="saveCoupon">
                            <div class="bg-white px-6 pt-5 pb-4">
                                <h3 class="text-lg font-bold text-gray-900 border-b pb-3 mb-4">
                                    {{ $editingCouponId ? 'Edit Discount Coupon' : 'Create Discount Coupon' }}
                                </h3>

                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Coupon Code *</label>
                                        <input wire:model="code" type="text" class="mt-1 block w-full uppercase font-mono font-bold border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. SAVE20" required>
                                        @error('code') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Description</label>
                                        <input wire:model="description" type="text" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Promotional campaign description...">
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Discount Type *</label>
                                            <select wire:model.live="discount_type" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                <option value="percentage">Percentage (%)</option>
                                                <option value="fixed">Fixed Amount</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Discount Value *</label>
                                            <input wire:model="discount_value" type="number" step="0.01" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Currency</label>
                                            <select wire:model="currency" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                @foreach (\App\Domain\Commerce\Services\CurrencyAndPricingService::SUPPORTED_CURRENCIES as $code => $info)
                                                    <option value="{{ $code }}">{{ $code }} ({{ $info['symbol'] }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Max Discount Amount (Cap)</label>
                                            <input wire:model="max_discount_amount" type="number" step="0.01" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Optional cap">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Min Purchase Subtotal</label>
                                            <input wire:model="min_purchase_amount" type="number" step="0.01" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Optional min subtotal">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Starts At</label>
                                            <input wire:model="starts_at" type="datetime-local" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Expires At</label>
                                            <input wire:model="expires_at" type="datetime-local" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Global Usage Limit</label>
                                            <input wire:model="global_usage_limit" type="number" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Leave empty for unlimited">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Limit Per Student *</label>
                                            <input wire:model="per_student_usage_limit" type="number" min="1" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                                        </div>
                                    </div>

                                    <div class="flex items-center space-x-6 pt-2">
                                        <label class="flex items-center space-x-2 text-sm text-gray-700">
                                            <input type="checkbox" wire:model="first_purchase_only" class="rounded text-indigo-600 focus:ring-indigo-500">
                                            <span>First purchase only</span>
                                        </label>
                                        <label class="flex items-center space-x-2 text-sm text-gray-700">
                                            <input type="checkbox" wire:model="is_active" class="rounded text-indigo-600 focus:ring-indigo-500">
                                            <span>Active for redemption</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gray-50 px-6 py-3 flex items-center justify-end space-x-3 rounded-b-lg">
                                <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100">
                                    Cancel
                                </button>
                                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                    {{ $editingCouponId ? 'Update Coupon' : 'Create Coupon' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
