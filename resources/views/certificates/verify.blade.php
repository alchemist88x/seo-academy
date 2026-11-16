<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate Verification — {{ config('app.name', 'SEO Academy') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 flex flex-col justify-between p-4 sm:p-6 lg:p-8">
    <header class="max-w-3xl mx-auto w-full flex items-center justify-between pb-6 border-b border-slate-200 dark:border-slate-800">
        <a href="{{ url('/') }}" class="text-xl font-bold tracking-tight text-primary-600 dark:text-primary-400">
            {{ $publicData['academy_name'] ?? config('app.name', 'SEO Academy') }}
        </a>
        <span class="text-xs font-semibold px-2.5 py-1 rounded bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
            Credential Verification
        </span>
    </header>

    <main class="max-w-xl mx-auto w-full my-8 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
        @if ($verificationStatus === 'not_found' || ! $certificate)
            {{-- Invalid / Not Found --}}
            <div class="p-8 text-center space-y-4">
                <div class="w-16 h-16 mx-auto rounded-full bg-rose-100 dark:bg-rose-900/40 text-rose-600 flex items-center justify-center">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <div class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-200">
                    NOT FOUND
                </div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Certificate Not Found</h1>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    We could not find any official certificate record matching the verification identifier <code>{{ $code }}</code>.
                </p>
                <p class="text-xs text-slate-400">
                    Please ensure the verification link or code is entered accurately.
                </p>
            </div>
        @elseif ($verificationStatus === 'under_audit')
            {{-- Under Academic Audit --}}
            <div class="p-8 text-center space-y-4">
                <div class="w-16 h-16 mx-auto rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-600 flex items-center justify-center">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">
                    UNDER ACADEMIC REVIEW
                </div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Certificate Under Academic Review</h1>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    This credential is currently undergoing formal administrative and academic review. Its validity cannot be verified at this time.
                </p>
                <div class="pt-4 border-t border-slate-100 dark:border-slate-700 text-xs text-slate-500 space-y-2 text-left">
                    <div><strong>Certificate Number:</strong> <span class="font-mono">{{ $publicData['certificate_number'] }}</span></div>
                    <div><strong>Student Name:</strong> {{ $publicData['masked_student_name'] }}</div>
                    <div><strong>Course:</strong> {{ $publicData['course_title'] }}</div>
                    <div><strong>Original Issue Date:</strong> {{ $publicData['issued_at'] }}</div>
                </div>
            </div>
        @elseif ($verificationStatus === 'revoked')
            {{-- Revoked Certificate --}}
            <div class="p-8 text-center space-y-4">
                <div class="w-16 h-16 mx-auto rounded-full bg-rose-100 dark:bg-rose-900/40 text-rose-600 flex items-center justify-center">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-200">
                    CERTIFICATE REVOKED
                </div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">This Certificate Has Been Revoked</h1>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    This certificate is no longer valid. It was officially revoked on {{ $publicData['revoked_at'] ?? 'record' }} by academic administration.
                </p>
                <div class="pt-4 border-t border-slate-100 dark:border-slate-700 text-xs text-slate-500 space-y-1">
                    <div><strong>Certificate Number:</strong> <span class="font-mono">{{ $publicData['certificate_number'] }}</span></div>
                    <div><strong>Original Issue Date:</strong> {{ $publicData['issued_at'] }}</div>
                    @if ($publicData['revoked_at'])
                        <div><strong>Revocation Date:</strong> {{ $publicData['revoked_at'] }}</div>
                    @endif
                </div>
            </div>
        @elseif ($verificationStatus === 'superseded')
            {{-- Superseded / Reissued Certificate --}}
            <div class="p-8 text-center space-y-4">
                <div class="w-16 h-16 mx-auto rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </div>
                <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-200">
                    SUPERSEDED / REISSUED
                </div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">This Certificate Has Been Superseded</h1>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    This certificate version was superseded by a reissued credential. While this historical record remains authentic, it is no longer the active valid certificate.
                </p>
                <div class="pt-4 border-t border-slate-100 dark:border-slate-700 text-xs text-slate-500 space-y-2 text-left">
                    <div><strong>Certificate Number:</strong> <span class="font-mono">{{ $publicData['certificate_number'] }}</span></div>
                    <div><strong>Student Name:</strong> {{ $publicData['student_name'] ?? $publicData['masked_student_name'] }}</div>
                    <div><strong>Course:</strong> {{ $publicData['course_title'] }}</div>
                    <div><strong>Original Issue Date:</strong> {{ $publicData['issued_at'] }}</div>
                </div>
            </div>
        @elseif ($verificationStatus === 'expired')
            {{-- Expired Certificate --}}
            <div class="p-8 text-center space-y-4">
                <div class="w-16 h-16 mx-auto rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-600 flex items-center justify-center">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">
                    CREDENTIAL EXPIRED
                </div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">This Certificate Has Expired</h1>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    This credential was valid from {{ $publicData['issued_at'] }} until {{ $publicData['expires_at'] ?? 'expiration date' }} and is no longer active.
                </p>
                <div class="pt-4 border-t border-slate-100 dark:border-slate-700 text-xs text-slate-500 space-y-1 text-left">
                    <div><strong>Certificate Number:</strong> <span class="font-mono">{{ $publicData['certificate_number'] }}</span></div>
                    <div><strong>Student Name:</strong> {{ $publicData['student_name'] ?? $publicData['masked_student_name'] }}</div>
                    <div><strong>Course:</strong> {{ $publicData['course_title'] }}</div>
                </div>
            </div>
        @elseif ($verificationStatus === 'reinstated')
            {{-- Reinstated Certificate --}}
            <div class="p-8 space-y-6">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">
                            VALID (REINSTATED)
                        </div>
                        <h1 class="text-xl font-bold text-slate-900 dark:text-white mt-1">Verified Reinstated Certificate</h1>
                    </div>
                </div>

                <div class="bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-lg p-3 text-xs text-emerald-800 dark:text-emerald-300">
                    This certificate was formally reviewed and reinstated on {{ $publicData['reinstated_at'] }} by academic administration.
                </div>

                <div class="space-y-4 pt-2 border-t border-slate-100 dark:border-slate-700">
                    <div>
                        <label class="text-xs font-medium text-slate-500 uppercase tracking-wider">Student Name</label>
                        <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ $publicData['student_name'] ?? $publicData['masked_student_name'] }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-medium text-slate-500 uppercase tracking-wider">Course Completed</label>
                        <p class="text-base font-medium text-slate-900 dark:text-white">{{ $publicData['course_title'] }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-medium text-slate-500 uppercase tracking-wider">Certificate Number</label>
                            <p class="text-sm font-mono font-medium text-slate-700 dark:text-slate-300">{{ $publicData['certificate_number'] }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-500 uppercase tracking-wider">Date Issued</label>
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $publicData['issued_at'] }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-medium text-slate-500 uppercase tracking-wider">Issuing Academy</label>
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $publicData['academy_name'] }}</p>
                    </div>
                </div>
            </div>
        @else
            {{-- Valid Certificate --}}
            <div class="p-8 space-y-6">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div>
                        <div class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">
                            VALID CREDENTIAL
                        </div>
                        <h1 class="text-xl font-bold text-slate-900 dark:text-white mt-1">Verified Certificate of Completion</h1>
                    </div>
                </div>

                <div class="space-y-4 pt-2 border-t border-slate-100 dark:border-slate-700">
                    <div>
                        <label class="text-xs font-medium text-slate-500 uppercase tracking-wider">Student Name</label>
                        <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ $publicData['student_name'] ?? $publicData['masked_student_name'] }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-medium text-slate-500 uppercase tracking-wider">Course Completed</label>
                        <p class="text-base font-medium text-slate-900 dark:text-white">{{ $publicData['course_title'] }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-medium text-slate-500 uppercase tracking-wider">Certificate Number</label>
                            <p class="text-sm font-mono font-medium text-slate-700 dark:text-slate-300">{{ $publicData['certificate_number'] }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-500 uppercase tracking-wider">Date Issued</label>
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $publicData['issued_at'] }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-medium text-slate-500 uppercase tracking-wider">Issuing Academy</label>
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $publicData['academy_name'] }}</p>
                    </div>
                </div>
            </div>
        @endif
    </main>

    <footer class="max-w-3xl mx-auto w-full text-center text-xs text-slate-400 pt-6">
        &copy; {{ date('Y') }} {{ config('app.name', 'SEO Academy') }}. All rights reserved. Single-academy self-hosted LMS.
    </footer>
</body>
</html>
