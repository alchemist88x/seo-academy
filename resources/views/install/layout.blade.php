@props([
    'title' => 'Installation Wizard',
    'stepNumber' => 1,
    'totalSteps' => 9,
    'state' => [],
])

@component('layouts.guest', ['title' => $title])
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Wizard Brand Header --}}
        <div class="mb-6 text-center">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary-50 dark:bg-primary-950/50 border border-primary-200 dark:border-primary-800 text-xs font-semibold text-primary-700 dark:text-primary-300 mb-2">
                SEO Academy v5.6.0 &bull; Zero-Terminal Browser Installer
            </div>
            <h1 class="text-3xl font-extrabold text-text-primary tracking-tight">{{ $title }}</h1>
            <p class="mt-1 text-sm text-text-secondary max-w-xl mx-auto">
                Step {{ $stepNumber }} of {{ $totalSteps }} &bull; Follow the guided wizard to configure your academy, database, and administrator account.
            </p>
        </div>

        {{-- Step Navigation Stepper --}}
        @php
            $steps = [
                1 => ['label' => 'Requirements', 'route' => 'install.step.requirements', 'key' => 'requirements_check'],
                2 => ['label' => 'Dependencies', 'route' => 'install.step.dependencies', 'key' => 'dependencies_check'],
                3 => ['label' => 'Environment', 'route' => 'install.step.environment', 'key' => 'environment_setup'],
                4 => ['label' => 'Database', 'route' => 'install.step.database', 'key' => 'database_configuration'],
                5 => ['label' => 'Migrations', 'route' => 'install.step.migrations', 'key' => 'migration'],
                6 => ['label' => 'Storage', 'route' => 'install.step.storage', 'key' => 'filesystem_setup'],
                7 => ['label' => 'Academy', 'route' => 'install.step.academy', 'key' => 'academy_configuration'],
                8 => ['label' => 'Admin', 'route' => 'install.step.admin', 'key' => 'administrator_creation'],
                9 => ['label' => 'Finalize', 'route' => 'install.step.finalization', 'key' => 'finalization'],
            ];
            $completedSteps = $state['completed_steps'] ?? [];
        @endphp

        <div class="mb-8 overflow-x-auto pb-2">
            <div class="flex items-center justify-between min-w-[700px] border-b border-border-default pb-4">
                @foreach ($steps as $num => $s)
                    @php
                        $isCompleted = in_array($s['key'], $completedSteps, true);
                        $isActive = ($num === $stepNumber);
                    @endphp
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-colors
                            {{ $isActive ? 'bg-primary-600 text-white shadow-md shadow-primary-500/20' : ($isCompleted ? 'bg-emerald-600 text-white' : 'bg-surface-elevated text-text-muted border border-border-default') }}">
                            @if ($isCompleted && ! $isActive)
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            @else
                                {{ $num }}
                            @endif
                        </div>
                        <span class="text-xs font-medium {{ $isActive ? 'text-primary-600 dark:text-primary-400 font-bold' : ($isCompleted ? 'text-text-primary' : 'text-text-muted') }}">
                            {{ $s['label'] }}
                        </span>
                        @if ($num < $totalSteps)
                            <div class="w-4 h-px bg-border-default mx-1"></div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Alerts --}}
        @if ($errors->any())
            <x-alert type="error" title="Action Required" class="mb-6" :dismissible="false">
                <ul class="list-disc list-inside space-y-1 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        @if (session('warning_switch'))
            <x-alert type="warning" title="Database Switch Confirmation" class="mb-6" :dismissible="false">
                <p class="text-xs">{{ session('warning_switch') }}</p>
            </x-alert>
        @endif

        {{-- Main Step Content --}}
        <div class="bg-surface-card border border-border-default rounded-xl p-6 sm:p-8 shadow-sm">
            {{ $slot }}
        </div>

        {{-- Footer Utilities --}}
        <div class="mt-6 flex items-center justify-between text-xs text-text-muted">
            <div class="flex items-center gap-4">
                <a href="{{ route('install.diagnostics') }}" class="hover:text-primary-600 underline flex items-center gap-1" target="_blank">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    System Diagnostics
                </a>
            </div>

            <form method="POST" action="{{ route('install.reset') }}" onsubmit="return confirm('Are you sure you want to restart the installation wizard? Existing databases will not be touched.');">
                @csrf
                <button type="submit" class="hover:text-danger-600 underline">
                    Restart Wizard
                </button>
            </form>
        </div>
    </div>
@endcomponent
