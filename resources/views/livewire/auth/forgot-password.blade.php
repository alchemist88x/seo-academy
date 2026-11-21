<div>
    <div class="mb-6">
        <h2 class="text-xl font-bold text-text-primary">Reset your password</h2>
        <p class="mt-1 text-sm text-text-secondary">
            Enter your email address and we will send you a password reset link.
        </p>
    </div>

    @if (session('status') || $status)
        <x-alert type="success" class="mb-5" :dismissible="false">
            {{ session('status') ?? $status }}
        </x-alert>
    @endif

    @if ($errors->any())
        <x-alert type="error" class="mb-5" :dismissible="false">
            {{ $errors->first() }}
        </x-alert>
    @endif

    <form method="POST" action="{{ route('password.email') }}" wire:submit="sendResetLink" class="space-y-4">
        @csrf
        <x-input
            name="email"
            label="Email address"
            type="email"
            wire:model="email"
            placeholder="you@example.com"
            autocomplete="email"
            required
        />

        <div class="pt-2">
            <x-button
                type="submit"
                variant="primary"
                class="w-full"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="sendResetLink">Send Reset Link</span>
                <span wire:loading wire:target="sendResetLink" class="inline-flex items-center gap-2">
                    <x-loading-spinner size="sm" />
                    Sending link...
                </span>
            </x-button>
        </div>
    </form>

    <div class="mt-6 text-center text-sm text-text-secondary border-t border-border-default/60 pt-4">
        Remember your password?
        <a href="{{ route('login') }}" class="font-semibold text-primary-600 hover:text-primary-700 hover:underline">
            Back to sign in
        </a>
    </div>
</div>
