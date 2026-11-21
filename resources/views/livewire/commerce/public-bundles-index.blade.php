<div class="bg-gray-50 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header & Currency Switcher -->
        <div class="flex flex-col md:flex-row md:items-end md:justify-between mb-10 pb-6 border-b border-gray-200">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded">Curriculum Packages</span>
                <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mt-2">Comprehensive Course Bundles</h1>
                <p class="text-base text-gray-600 mt-2 max-w-2xl">
                    Master end-to-end digital marketing and technical SEO. Get multiple accredited courses packaged together at exclusive bundled savings.
                </p>
            </div>

            <!-- Currency Selector -->
            @if ($isCurrencySwitcherEnabled ?? true)
                <div class="mt-4 md:mt-0 flex items-center space-x-2 bg-white px-3 py-2 rounded-lg border border-gray-200 shadow-sm">
                    <span class="text-xs font-medium text-gray-500">Currency:</span>
                    <select wire:change="setCurrency($event.target.value)" class="text-xs font-bold text-gray-800 bg-transparent border-0 focus:ring-0 cursor-pointer">
                        @foreach ($supportedCurrencies as $code => $info)
                            <option value="{{ $code }}" {{ $activeCurrency === $code ? 'selected' : '' }}>
                                {{ $code }} ({{ $info['symbol'] }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        @if ($bundles->isEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center text-gray-500">
                <p class="text-lg font-medium text-gray-900">No active course bundles right now</p>
                <p class="text-sm text-gray-500 mt-1">Please explore our individual courses catalogue.</p>
                <a href="{{ route('courses.public.index') }}" class="mt-4 inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                    Browse Courses
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach ($bundles as $bundle)
                    @php
                        $priceInfo = $bundlePricings[$bundle->id] ?? null;
                    @endphp
                    <div class="bg-white rounded-2xl shadow-sm hover:shadow-md transition border border-gray-200 flex flex-col overflow-hidden">
                        <!-- Top Banner -->
                        <div class="h-44 bg-gradient-to-br from-indigo-900 via-indigo-800 to-purple-900 p-6 flex flex-col justify-between text-white relative">
                            <div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-white/20 backdrop-blur-sm text-white">
                                    {{ $bundle->courses->count() }} Courses Included
                                </span>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold line-clamp-2 text-white">{{ $bundle->title }}</h3>
                            </div>
                            @if ($bundle->ownership_policy === 'exclude_owned_courses')
                                <div class="absolute top-4 right-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-semibold bg-emerald-500 text-white shadow" title="If you already own courses in this bundle, checkout deducts them automatically!">
                                        Smart Ownership Deductions
                                    </span>
                                </div>
                            @endif
                        </div>

                        <!-- Card Body -->
                        <div class="p-6 flex-1 flex flex-col justify-between">
                            <div>
                                <p class="text-sm text-gray-600 line-clamp-3 mb-4">
                                    {{ $bundle->description }}
                                </p>

                                <!-- Included Courses List -->
                                <div class="space-y-2 mb-6">
                                    <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Curriculum Path:</div>
                                    <ul class="space-y-1.5 text-xs text-gray-700">
                                        @foreach ($bundle->courses->take(4) as $course)
                                            <li class="flex items-center space-x-2">
                                                <svg class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                <span class="truncate">{{ $course->title }}</span>
                                            </li>
                                        @endforeach
                                        @if ($bundle->courses->count() > 4)
                                            <li class="text-xs text-indigo-600 font-semibold pl-5">
                                                + {{ $bundle->courses->count() - 4 }} more courses
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            </div>

                            <!-- Pricing & Action Footer -->
                            <div class="border-t border-gray-100 pt-4">
                                @if ($bundle->installmentPlan)
                                    <div class="mb-3 bg-purple-50 rounded-lg p-2.5 text-xs text-purple-900 border border-purple-100 flex items-center space-x-2">
                                        <svg class="w-4 h-4 text-purple-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        <span>
                                            <strong>{{ $bundle->installmentPlan->initial_deposit_value }}% Deposit</strong>, balance on reaching <strong>{{ $bundle->installmentPlan->completion_milestone_percentage }}% completion</strong>!
                                        </span>
                                    </div>
                                @endif

                                <div class="flex items-end justify-between mb-4">
                                    <div>
                                        <div class="text-2xs text-gray-500 uppercase tracking-wider font-semibold">Bundle Price</div>
                                        <div class="text-2xl font-black text-gray-900">
                                            {{ $priceInfo['currency'] ?? $activeCurrency }} {{ number_format($priceInfo['final_price'] ?? $bundle->effective_price, 2) }}
                                        </div>
                                    </div>
                                    @if ($priceInfo && $priceInfo['discount_amount'] > 0)
                                        <div class="text-right">
                                            <div class="text-xs text-gray-400 line-through">
                                                {{ $priceInfo['currency'] }} {{ number_format($priceInfo['original_price'], 2) }}
                                            </div>
                                            <div class="text-xs font-bold text-emerald-600">
                                                Save {{ $priceInfo['currency'] }} {{ number_format($priceInfo['discount_amount'], 2) }}
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <a href="{{ route('bundles.public.show', $bundle->slug) }}" class="inline-flex justify-center items-center px-3 py-2 border border-gray-300 shadow-sm text-xs font-bold rounded-lg text-gray-700 bg-white hover:bg-gray-50 text-center">
                                        View Details
                                    </a>
                                    @if (auth()->check() && ! auth()->user()->hasRole('student'))
                                        <a href="{{ route('bundles.public.show', $bundle->slug) }}" class="inline-flex justify-center items-center px-3 py-2 border border-amber-300 shadow-sm text-xs font-bold rounded-lg text-amber-800 bg-amber-50 text-center">
                                            Staff View
                                        </a>
                                    @else
                                        <a href="{{ route('checkout.show', ['type' => 'bundle', 'id' => $bundle->id]) }}" class="inline-flex justify-center items-center px-3 py-2 border border-transparent shadow-sm text-xs font-bold rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 text-center">
                                            Enroll Now
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
