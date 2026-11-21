<div class="bg-gray-50 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Breadcrumb & Currency Switcher -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 gap-4">
            <nav class="flex items-center text-sm font-medium text-gray-500 space-x-2">
                <a href="{{ route('home') }}" class="hover:text-gray-700">Home</a>
                <span>/</span>
                <a href="{{ route('bundles.public.index') }}" class="hover:text-gray-700">Course Bundles</a>
                <span>/</span>
                <span class="text-gray-900 font-bold truncate max-w-xs">{{ $bundle->title }}</span>
            </nav>

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

        <!-- Main Hero Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-12">
            <div class="bg-gradient-to-r from-indigo-900 via-indigo-800 to-purple-900 text-white p-8 sm:p-12">
                <div class="max-w-3xl">
                    <div class="flex flex-wrap items-center gap-2 mb-4">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-white/20 backdrop-blur-sm text-white">
                            {{ $bundle->courses->count() }} Accredited Courses Included
                        </span>
                        @if ($bundle->ownership_policy === 'exclude_owned_courses')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-500 text-white shadow">
                                Automatic Owned Course Deductions
                            </span>
                        @endif
                    </div>
                    <h1 class="text-3xl sm:text-5xl font-black text-white leading-tight">{{ $bundle->title }}</h1>
                    <p class="text-base sm:text-lg text-indigo-100 mt-4 leading-relaxed">
                        {{ $bundle->description }}
                    </p>
                </div>
            </div>

            <!-- Pricing and Action Bar -->
            <div class="p-8 sm:px-12 bg-gray-50 border-t border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Package Investment</div>
                    <div class="flex items-baseline space-x-3 mt-1">
                        <span class="text-4xl font-black text-gray-900">
                            @currency($pricing['final_price'], $pricing['currency'])
                        </span>
                        @if ($pricing['discount_amount'] > 0)
                            <span class="text-lg text-gray-400 line-through">
                                @currency($pricing['original_price'], $pricing['currency'])
                            </span>
                            <span class="text-sm font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                Save @currency($pricing['discount_amount'], $pricing['currency'])
                            </span>
                        @endif
                    </div>
                    @if ($bundle->installmentPlan)
                        <div class="text-xs text-purple-700 font-semibold mt-1">
                            Installments: Pay {{ $bundle->installmentPlan->initial_deposit_value }}% deposit today, remaining balance at {{ $bundle->installmentPlan->completion_milestone_percentage }}% course completion milestone!
                        </div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    @if ($isCurrencySwitcherEnabled ?? true)
                        <div class="flex items-center space-x-2 bg-white px-3 py-2 rounded-lg border border-gray-200 shadow-sm">
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
                    @if (auth()->check() && ! auth()->user()->hasRole('student'))
                        <div class="inline-flex items-center px-6 py-3 border border-amber-300 rounded-xl bg-amber-50 text-amber-900 text-xs font-semibold">
                            Staff Account &bull; Direct purchases disabled
                            @if (auth()->user()->hasRole('admin'))
                                <a href="{{ route('admin.orders.index') }}" class="ml-2 underline font-bold text-indigo-700">
                                    Assign via Admin Orders &rarr;
                                </a>
                            @endif
                        </div>
                    @else
                        <a href="{{ route('checkout.show', ['type' => 'bundle', 'id' => $bundle->id]) }}" class="inline-flex items-center px-8 py-4 border border-transparent text-base font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 shadow-md transition">
                            Enroll in Complete Bundle
                            <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- Curriculum Breakdown -->
        <div class="mb-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Courses Included in This Bundle</h2>
            <div class="space-y-4">
                @foreach ($bundle->courses as $idx => $course)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div class="flex items-start space-x-4">
                            <span class="flex-shrink-0 w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 font-black text-sm flex items-center justify-center">
                                {{ $idx + 1 }}
                            </span>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">{{ $course->title }}</h3>
                                <p class="text-sm text-gray-600 mt-1">{{ Str::limit($course->description, 160) }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                    <span class="bg-gray-100 px-2 py-0.5 rounded font-medium">{{ $course->difficulty }}</span>
                                    @if ($course->category)
                                        <span class="bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded font-medium">{{ $course->category->name }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="text-right flex-shrink-0 flex sm:flex-col items-center sm:items-end justify-between">
                            <div class="text-sm font-bold text-gray-900">
                                @currency($course->effective_price, $course->currency)
                            </div>
                            <a href="{{ route('courses.public.show', $course->slug) }}" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold mt-1">
                                Course Details &rarr;
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
