<div class="p-6 max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('student.courses.index') }}" class="text-sm font-medium text-indigo-400 hover:text-indigo-300 transition-colors">
                &larr; Back to My Courses
            </a>
            <h1 class="text-2xl font-bold text-white tracking-tight mt-1">Official Academic Transcript</h1>
            <p class="text-sm text-slate-300">{{ $course->title }}</p>
        </div>
        <div>
            <button
                type="button"
                onclick="window.print()"
                class="px-4 py-2 text-sm font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 transition-colors shadow-sm inline-flex items-center gap-2"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Print Transcript
            </button>
        </div>
    </div>

    @if ($errorMessage)
        <div class="p-4 rounded-lg bg-rose-950/80 border border-rose-500/50 text-rose-200 text-sm font-medium" role="alert">
            {{ $errorMessage }}
        </div>
    @elseif ($transcript)
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-8 space-y-6 shadow-sm print:bg-white print:text-black print:border-none print:p-0">
            {{-- Student & Course Header --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 border-b border-slate-800 pb-6 print:border-black">
                <div>
                    <h2 class="text-xs uppercase font-semibold text-slate-400 tracking-wider">Student Information</h2>
                    <p class="text-lg font-bold text-white mt-1 print:text-black">{{ $transcript['student']['name'] }}</p>
                    <p class="text-sm text-slate-300 print:text-black">{{ $transcript['student']['email'] }}</p>
                    <p class="text-xs text-slate-400 mt-1 print:text-black">Enrolled: {{ $transcript['student']['enrolled_at'] ?? 'N/A' }}</p>
                </div>
                <div class="sm:text-right">
                    <h2 class="text-xs uppercase font-semibold text-slate-400 tracking-wider">Course Information</h2>
                    <p class="text-lg font-bold text-white mt-1 print:text-black">{{ $transcript['course']['title'] }}</p>
                    <p class="text-sm text-slate-300 print:text-black">Difficulty: {{ $transcript['course']['difficulty'] ?? 'Standard' }}</p>
                    <p class="text-xs text-slate-400 mt-1 print:text-black">Duration: {{ $transcript['course']['duration_months'] ? $transcript['course']['duration_months'] . ' Months' : 'Self-paced' }}</p>
                </div>
            </div>

            {{-- Course Completion vs Academic Passing (Mandatory Policy 1) --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-4 bg-slate-950 border border-slate-800 rounded-xl space-y-1 print:border-black print:bg-gray-50">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Course Progress / Lifecycle</span>
                    <div class="flex items-center justify-between">
                        <span class="text-base font-bold text-white print:text-black">
                            {{ $transcript['completion']['is_completed'] ? 'Completed' : 'In Progress' }}
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                            {{ $transcript['completion']['is_completed'] ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : 'bg-slate-800 text-slate-300 border border-slate-700' }}">
                            {{ ucfirst($transcript['completion']['status']) }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-400">
                        {{ $transcript['completion']['completed_at'] ? 'Completed on ' . \Illuminate\Support\Carbon::parse($transcript['completion']['completed_at'])->format('M d, Y') : 'Lessons in progress' }}
                    </p>
                </div>

                <div class="p-4 bg-slate-950 border border-slate-800 rounded-xl space-y-1 print:border-black print:bg-gray-50">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Academic Grade & Standing</span>
                    @if ($transcript['grade']['is_published'])
                        <div class="flex items-center justify-between">
                            <span class="text-2xl font-black text-indigo-400 print:text-black">
                                {{ $transcript['grade']['final_score'] }}%
                            </span>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold
                                {{ $transcript['grade']['is_passing'] ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : 'bg-rose-950 text-rose-300 border border-rose-800' }}">
                                Grade: {{ $transcript['grade']['letter_grade'] }} ({{ ucfirst($transcript['grade']['academic_status']) }})
                            </span>
                        </div>
                        <p class="text-xs text-slate-400">
                            Official Grade Snapshot v{{ $transcript['grade']['version'] }} &bull; Published {{ \Illuminate\Support\Carbon::parse($transcript['grade']['published_at'])->format('M d, Y') }}
                        </p>
                    @else
                        <div class="flex items-center justify-between">
                            <span class="text-base font-bold text-slate-400 italic">Pending Official Publication</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-800 text-slate-300">
                                In Evaluation
                            </span>
                        </div>
                        <p class="text-xs text-slate-400">Final grades are recorded once officially evaluated and published by course staff.</p>
                    @endif
                </div>
            </div>

            {{-- Certificate Eligibility Summary (Mandatory Policy 2) --}}
            <div class="p-4 rounded-xl border {{ $transcript['is_certificate_eligible'] ? 'bg-emerald-950/40 border-emerald-500/40' : 'bg-slate-950 border-slate-800' }}">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold {{ $transcript['is_certificate_eligible'] ? 'text-emerald-200' : 'text-slate-300' }}">
                            Certificate Eligibility
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Requires both complete curriculum attendance AND an officially published passing grade.
                        </p>
                    </div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold
                        {{ $transcript['is_certificate_eligible'] ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-400' }}">
                        {{ $transcript['is_certificate_eligible'] ? 'Eligible' : 'Not Yet Eligible' }}
                    </span>
                </div>
            </div>

            {{-- Itemized Category Breakdown if Published --}}
            @if (!empty($transcript['grade']['breakdown']['categories']))
                <div class="space-y-3 pt-4 border-t border-slate-800 print:border-black">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider print:text-black">Curriculum Assessment Breakdown</h3>
                    <div class="space-y-2">
                        @foreach ($transcript['grade']['breakdown']['categories'] as $cat)
                            <div class="p-4 bg-slate-950 border border-slate-800 rounded-lg flex items-center justify-between print:border-black print:bg-white">
                                <div>
                                    <span class="font-semibold text-white print:text-black">{{ $cat['name'] ?? 'Category' }}</span>
                                    <span class="text-xs text-slate-400 block">Weight: {{ $cat['normalized_weight'] ?? $cat['weight'] ?? 0 }}%</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm font-bold text-indigo-400 print:text-black">{{ $cat['unrounded_score'] ?? 0 }}%</span>
                                    <span class="text-xs text-slate-400 block">Course Contribution: {{ $cat['course_contribution'] ?? 0 }}%</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Footer --}}
            <div class="pt-6 border-t border-slate-800 text-center text-xs text-slate-400 print:border-black print:text-black">
                <p>This is an official institutional academic transcript generated on {{ \Illuminate\Support\Carbon::parse($transcript['generated_at'])->format('F d, Y \a\t H:i:s T') }}.</p>
                <p class="mt-1">Enrollment ID: <code>{{ $transcript['enrollment_id'] }}</code></p>
            </div>
        </div>
    @endif
</div>
