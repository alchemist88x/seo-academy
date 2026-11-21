<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 pb-4 border-b border-gray-200">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Course Bundles</h1>
                <p class="text-sm text-gray-600 mt-1">Package multiple courses together with bundled pricing, custom discounts, and installment plans.</p>
            </div>
            <div class="mt-4 md:mt-0 flex items-center space-x-3">
                <a href="{{ route('bundles.public.index') }}" target="_blank" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none">
                    <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    Public Bundle Catalogue
                </a>
                <button wire:click="openCreateModal" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Create Bundle
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
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search bundles by title or description..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <label class="text-xs font-semibold text-gray-600 uppercase">Status:</label>
                <select wire:model.live="statusFilter" class="border border-gray-300 rounded-md text-sm py-1.5 px-3 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="all">All Statuses</option>
                    <option value="published">Published</option>
                    <option value="draft">Draft</option>
                    <option value="archived">Archived</option>
                </select>
            </div>
        </div>

        <!-- Bundles List -->
        <div class="bg-white shadow overflow-hidden sm:rounded-md border border-gray-200">
            @if ($bundles->isEmpty())
                <div class="p-12 text-center text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <p class="mt-2 text-base font-medium text-gray-900">No course bundles found</p>
                    <p class="text-sm text-gray-500">Get started by creating your first bundled curriculum offering.</p>
                </div>
            @else
                <ul class="divide-y divide-gray-200">
                    @foreach ($bundles as $bundle)
                        <li class="p-6 hover:bg-gray-50 transition">
                            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3">
                                        <h3 class="text-lg font-bold text-gray-900">{{ $bundle->title }}</h3>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                            {{ $bundle->status === 'published' ? 'bg-green-100 text-green-800' : ($bundle->status === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }}">
                                            {{ ucfirst($bundle->status) }}
                                        </span>
                                        @if ($bundle->ownership_policy === 'exclude_owned_courses')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                                Deducts Owned Courses
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-sm text-gray-600 mt-1 max-w-3xl line-clamp-2">{{ $bundle->description }}</p>
                                    
                                    <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-gray-500">
                                        <span class="font-semibold text-gray-700 bg-gray-100 px-2.5 py-1 rounded">
                                            {{ $bundle->courses->count() }} {{ Str::plural('Course', $bundle->courses->count()) }} Included
                                        </span>
                                        <span>Slug: <code class="bg-gray-100 px-1 py-0.5 rounded text-gray-700">{{ $bundle->slug }}</code></span>
                                        @if ($bundle->installmentPlan)
                                            <span class="text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100">
                                                Plan: {{ $bundle->installmentPlan->title }} ({{ $bundle->installmentPlan->completion_milestone_percentage }}% Milestone)
                                            </span>
                                        @endif
                                    </div>

                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach ($bundle->courses as $c)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                {{ $c->title }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="mt-4 lg:mt-0 flex flex-col sm:flex-row items-start sm:items-center lg:flex-col lg:items-end gap-3">
                                    <div class="text-right">
                                        <div class="text-2xl font-black text-gray-900">
                                            @currency($bundle->effective_price, $bundle->currency)
                                        </div>
                                        @if ($bundle->original_total_price && $bundle->original_total_price > $bundle->effective_price)
                                            <div class="text-xs text-gray-500 line-through">
                                                Regular: @currency($bundle->original_total_price, $bundle->currency)
                                            </div>
                                            <div class="text-xs font-semibold text-emerald-600">
                                                Save @currency($bundle->calculateCalculatedSavings(), $bundle->currency)
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex items-center space-x-2">
                                        <a href="{{ route('bundles.public.show', $bundle->slug) }}" target="_blank" class="p-1.5 text-gray-500 hover:text-indigo-600 rounded hover:bg-gray-100" title="View Public Page">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>
                                        <button wire:click="editBundle({{ $bundle->id }})" class="p-1.5 text-gray-500 hover:text-indigo-600 rounded hover:bg-gray-100" title="Edit Bundle">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button wire:click="deleteBundle({{ $bundle->id }})" wire:confirm="Are you sure you want to delete this course bundle?" class="p-1.5 text-gray-400 hover:text-red-600 rounded hover:bg-gray-100" title="Delete Bundle">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $bundles->links() }}
                </div>
            @endif
        </div>

        <!-- Bundle Create/Edit Modal -->
        @if ($showModal)
            <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showModal', false)"></div>

                    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                        <form wire:submit.prevent="saveBundle">
                            <div class="bg-white px-6 pt-5 pb-4">
                                <h3 class="text-lg font-bold text-gray-900 border-b pb-3 mb-4">
                                    {{ $editingBundleId ? 'Edit Course Bundle' : 'Create Course Bundle' }}
                                </h3>

                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Bundle Title *</label>
                                        <input wire:model.live="title" type="text" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                                        @error('title') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">URL Slug *</label>
                                        <input wire:model="slug" type="text" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                                        @error('slug') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Included Courses *</label>
                                        <p class="text-xs text-gray-500 mb-2">Select courses bundled in this package.</p>
                                        <div class="max-h-48 overflow-y-auto border border-gray-200 rounded-md p-3 space-y-2 bg-gray-50">
                                            @foreach ($availableCourses as $c)
                                                <label class="flex items-center space-x-2 text-sm text-gray-800">
                                                    <input type="checkbox" wire:model.live="selectedCourseIds" value="{{ $c->id }}" class="rounded text-indigo-600 focus:ring-indigo-500">
                                                    <span class="flex-1">{{ $c->title }}</span>
                                                    <span class="text-xs text-gray-500 font-semibold">@currency($c->price, $c->currency)</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @error('selectedCourseIds') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Bundle Price *</label>
                                            <input wire:model="price" type="number" step="0.01" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Currency *</label>
                                            <select wire:model="currency" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                @foreach (\App\Domain\Commerce\Services\CurrencyAndPricingService::SUPPORTED_CURRENCIES as $code => $info)
                                                    <option value="{{ $code }}">{{ $code }} ({{ $info['symbol'] }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Original / Sum Price</label>
                                            <input wire:model="original_total_price" type="number" step="0.01" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Auto-calculated">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Promotional Discount Price</label>
                                            <input wire:model="discount_price" type="number" step="0.01" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. 199.00">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Or Discount Percentage (%)</label>
                                            <input wire:model="discount_percentage" type="number" step="0.01" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. 25">
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Existing Course Ownership Policy *</label>
                                        <select wire:model="ownership_policy" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                            <option value="full_bundle_price">Full Bundle Price (Charge full package regardless of prior ownership)</option>
                                            <option value="exclude_owned_courses">Exclude Owned Courses (Automatically deduct price of already owned courses)</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Installment Payment Plan</label>
                                        <select wire:model="installment_plan_id" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                            <option value="">No Installments (Full Payment Upfront)</option>
                                            @foreach ($availablePlans as $p)
                                                <option value="{{ $p->id }}">{{ $p->title }} ({{ $p->completion_milestone_percentage }}% Milestone / {{ $p->initial_deposit_value }}% Deposit)</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Regional Prices (JSON)</label>
                                        <textarea wire:model="regional_prices_json" rows="2" placeholder='{"INR": 9999, "EUR": 129, "GBP": 109}' class="mt-1 block w-full font-mono text-xs border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Status</label>
                                            <select wire:model="status" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                <option value="published">Published</option>
                                                <option value="draft">Draft</option>
                                                <option value="archived">Archived</option>
                                            </select>
                                        </div>
                                        <div class="flex items-center pt-6">
                                            <label class="flex items-center space-x-2 text-sm text-gray-700">
                                                <input type="checkbox" wire:model="is_coupon_eligible" class="rounded text-indigo-600 focus:ring-indigo-500">
                                                <span>Allow Discount Coupons</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Description</label>
                                        <textarea wire:model="description" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Marketing overview of bundle benefits..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gray-50 px-6 py-3 flex items-center justify-end space-x-3 rounded-b-lg">
                                <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100">
                                    Cancel
                                </button>
                                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                    {{ $editingBundleId ? 'Update Bundle' : 'Create Bundle' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
