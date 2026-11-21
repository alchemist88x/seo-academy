<div>
    <div class="mb-6">
        <h2 class="text-xl font-bold text-text-primary">Set new password</h2>
        <p class="mt-1 text-sm text-text-secondary">
            Please enter your email address and your new secure password.
        </p>
    </div>

    @if ($errors->any())
        <x-alert type="error" class="mb-5" :dismissible="false">
            {{ $errors->first() }}
        </x-alert>
    @endif

    <form method="POST" action="{{ route('password.update') }}" wire:submit="resetPassword" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

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
            label="New password"
            type="password"
            wire:model="password"
            placeholder="••••••••"
            autocomplete="new-password"
            hint="Minimum 8 characters."
            required
        />

        <x-input
            name="password_confirmation"
            label="Confirm new password"
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
                <span wire:loading.remove wire:target="resetPassword">Reset Password</span>
                <span wire:loading wire:target="resetPassword" class="inline-flex items-center gap-2">
                    <x-loading-spinner size="sm" />
                    Resetting password...
                </span>
            </x-button>
        </div>
    </form>

    <div class="mt-6 text-center text-sm text-text-secondary border-t border-border-default/60 pt-4">
        <a href="{{ route('login') }}" class="font-semibold text-primary-600 hover:text-primary-700 hover:underline">
            Back to sign in
        </a>
    </div>
</div>
