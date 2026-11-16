@component('install.layout', [
    'title' => 'Step 8 — First Administrator Account',
    'stepNumber' => 8,
    'totalSteps' => 9,
    'state' => $state,
])
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-bold text-text-primary">Establish Primary Administrator Credentials</h2>
            <p class="text-xs text-text-secondary mt-1">
                Create the super administrator account with full privileges across students, courses, settings, and grading.
            </p>
        </div>

        <form method="POST" action="{{ route('install.step.admin.store') }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">Full Name <span class="text-rose-500">*</span></label>
                    <input
                        type="text"
                        name="admin_name"
                        value="{{ old('admin_name', $state['admin_name'] ?? '') }}"
                        required
                        class="input input-sm w-full text-xs"
                        placeholder="e.g. Chief Administrator"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">Admin Email Address <span class="text-rose-500">*</span></label>
                    <input
                        type="email"
                        name="admin_email"
                        value="{{ old('admin_email', $state['admin_email'] ?? '') }}"
                        required
                        class="input input-sm w-full text-xs"
                        placeholder="admin@youracademy.com"
                    >
                    <p class="text-[11px] text-text-muted mt-1">Used for signing in and emergency system alerts.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">Password <span class="text-rose-500">*</span></label>
                    <input
                        type="password"
                        name="admin_password"
                        required
                        class="input input-sm w-full text-xs"
                        placeholder="Minimum 8 characters"
                        autocomplete="new-password"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-primary mb-1">Confirm Password <span class="text-rose-500">*</span></label>
                    <input
                        type="password"
                        name="admin_password_confirmation"
                        required
                        class="input input-sm w-full text-xs"
                        placeholder="Re-enter password"
                        autocomplete="new-password"
                    >
                </div>
            </div>

            <div class="p-4 rounded-lg bg-surface-elevated/40 border border-border-default text-xs text-text-secondary">
                <strong class="text-text-primary block mb-1">Security &amp; Role Guarantee:</strong>
                <p>
                    Passwords are securely hashed using bcrypt. The user will be assigned the <code>admin</code> role and granted access to all administrative modules immediately upon completion.
                </p>
            </div>

            <div class="pt-4 border-t border-border-default flex items-center justify-between">
                <a href="{{ route('install.step.academy') }}" class="text-xs text-text-muted hover:text-text-primary underline">
                    &larr; Back to Academy Setup
                </a>

                <button type="submit" class="btn btn-primary px-6 py-2.5 text-xs font-bold rounded-lg shadow-sm">
                    Create Administrator &amp; Review &rarr;
                </button>
            </div>
        </form>
    </div>
@endcomponent
