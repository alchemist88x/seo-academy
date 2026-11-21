<div>
    <div class="mb-6">
        <h2 class="text-xl font-bold text-text-primary">Create student account</h2>
        <p class="mt-1 text-sm text-text-secondary">
            Enter your details to register as a student at SEO Academy.
        </p>
    </div>

    @if (! $isRegistrationAllowed)
        <x-alert type="warning" class="mb-5" :dismissible="false">
            <div class="space-y-1">
                <p class="font-semibold">Self-registration is currently disabled</p>
                <p class="text-xs">Direct account registration has been disabled by the administrator. Please contact your academy administrator to request an account.</p>
            </div>
        </x-alert>
        <div class="pt-2">
            <a href="{{ route('login') }}" class="btn btn-secondary w-full text-center block">
                Back to Sign In
            </a>
        </div>
    @else
        @if ($errors->any())
            <x-alert type="error" class="mb-5" :dismissible="false">
                {{ $errors->first() }}
            </x-alert>
        @endif

        <form method="POST" action="{{ route('register.attempt') }}" wire:submit="register" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <x-input
                    name="first_name"
                    label="First name"
                    wire:model="first_name"
                    placeholder="John"
                    autocomplete="given-name"
                    required
                />

                <x-input
                    name="last_name"
                    label="Last name"
                    wire:model="last_name"
                    placeholder="Doe"
                    autocomplete="family-name"
                />
            </div>

            <x-input
                name="email"
                label="Email address"
                type="email"
                wire:model="email"
                placeholder="you@example.com"
                autocomplete="email"
                required
            />

            <x-input
                name="password"
                label="Password"
                type="password"
                wire:model="password"
                placeholder="••••••••"
                autocomplete="new-password"
                hint="Minimum 8 characters."
                required
            />

            <x-input
                name="password_confirmation"
                label="Confirm password"
                type="password"
                wire:model="password_confirmation"
                placeholder="••••••••"
                autocomplete="new-password"
                required
            />

            <div class="pt-2">
                <x-button
                    type="submit"
                    variant="primary"
                    class="w-full"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="register">Create Student Account</span>
                    <span wire:loading wire:target="register" class="inline-flex items-center gap-2">
                        <x-loading-spinner size="sm" />
                        Creating account...
                    </span>
                </x-button>
            </div>
        </form>

        <div class="mt-6 text-center text-xs text-text-secondary">
            Already have an account?
            <a href="{{ route('login') }}" class="font-semibold text-primary-600 hover:text-primary-500 underline ml-1">
                Sign in
            </a>
        </div>
    @endif
</div>
