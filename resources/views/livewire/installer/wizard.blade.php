<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    {{-- Header Branding --}}
    <div class="mb-8 text-center">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary-50 dark:bg-primary-950/50 border border-primary-200 dark:border-primary-800 text-xs font-semibold text-primary-700 dark:text-primary-300 mb-2">
            SEO Academy v5.6.0 &bull; Commercial Self-Hosted LMS
        </div>
        <h1 class="text-3xl font-extrabold text-text-primary tracking-tight">Installation Wizard</h1>
        <p class="mt-1 text-sm text-text-secondary max-w-xl mx-auto">
            Step {{ $step }} of 11 &bull; Complete browser-based setup for your independent academy.
        </p>
    </div>

    {{-- 11-Step Progress Indicator --}}
    @php
        $stepLabels = [
            1 => 'Welcome',
            2 => 'Requirements',
            3 => 'Dependencies',
            4 => 'Engine',
            5 => 'Config',
            6 => 'Test',
            7 => 'Migrations',
            8 => 'Academy',
            9 => 'Admin',
            10 => 'Verification',
            11 => 'Complete',
        ];
    @endphp

    <div class="mb-8 overflow-x-auto pb-2">
        <div class="flex items-center justify-between min-w-[760px] border-b border-border-default pb-4">
            @foreach ($stepLabels as $num => $label)
                @php
                    $isCompleted = ($step > $num) || in_array($num, $completedSteps, true);
                    $isActive = ($step === $num);
                @endphp
                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        wire:click="goToStep({{ $num }})"
                        @disabled(! $isCompleted && ! $isActive)
                        class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all
                            {{ $isActive ? 'bg-primary-600 text-white shadow-md shadow-primary-500/20 ring-2 ring-primary-500/30' : ($isCompleted ? 'bg-emerald-600 text-white hover:bg-emerald-700 cursor-pointer' : 'bg-surface-elevated text-text-muted border border-border-default cursor-not-allowed') }}"
                        title="{{ $label }}"
                    >
                        @if ($isCompleted && ! $isActive)
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        @else
                            {{ $num }}
                        @endif
                    </button>
                    <span class="text-xs font-medium {{ $isActive ? 'text-primary-600 dark:text-primary-400 font-bold' : ($isCompleted ? 'text-text-primary' : 'text-text-muted') }}">
                        {{ $label }}
                    </span>
                    @if ($num < 11)
                        <div class="w-3 h-px bg-border-default mx-0.5"></div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Error Banner --}}
    @if ($generalError)
        <div class="mb-6 p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-xs flex items-start justify-between gap-3 shadow-sm">
            <div class="flex items-start gap-2.5">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div>
                    <span class="font-bold block text-sm mb-0.5">Attention Needed</span>
                    <span>{{ $generalError }}</span>
                </div>
            </div>
            <button type="button" wire:click="$set('generalError', null)" class="text-rose-500 hover:text-rose-700 text-sm font-bold">&times;</button>
        </div>
    @endif

    {{-- Individual Wizard Screen --}}
    <div class="bg-surface-card border border-border-default rounded-2xl p-6 sm:p-8 shadow-sm">
        @if ($step === 1)
            @include('livewire.installer.steps.welcome')
        @elseif ($step === 2)
            @include('livewire.installer.steps.requirements')
        @elseif ($step === 3)
            @include('livewire.installer.steps.dependencies')
        @elseif ($step === 4)
            @include('livewire.installer.steps.database_selection')
        @elseif ($step === 5)
            @include('livewire.installer.steps.database_configuration')
        @elseif ($step === 6)
            @include('livewire.installer.steps.database_test')
        @elseif ($step === 7)
            @include('livewire.installer.steps.migrations')
        @elseif ($step === 8)
            @include('livewire.installer.steps.academy')
        @elseif ($step === 9)
            @include('livewire.installer.steps.admin')
        @elseif ($step === 10)
            @include('livewire.installer.steps.verification')
        @elseif ($step === 11)
            @include('livewire.installer.steps.complete')
        @endif
    </div>

    {{-- Bottom Utility Bar --}}
    <div class="mt-6 flex items-center justify-between text-xs text-text-muted">
        <a href="{{ route('install.diagnostics') }}" target="_blank" class="hover:text-primary-600 underline flex items-center gap-1.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            System Diagnostics &amp; Sanity Report
        </a>
        <form method="POST" action="{{ route('install.reset') }}" onsubmit="return confirm('Are you sure you want to restart the installation wizard? Existing databases will not be touched.');">
            @csrf
            <button type="submit" class="hover:text-rose-600 underline transition-colors">
                Restart Wizard
            </button>
        </form>
    </div>
</div>
