<div>
    <div class="mb-6">
        <h2 class="text-xl font-bold text-text-primary">Sign in to your account</h2>
        <p class="mt-1 text-sm text-text-secondary">
            Enter your credentials to access your SEO Academy portal.
        </p>
    </div>

    @if (session('status'))
        <x-alert type="success" class="mb-5" :dismissible="false">
            {{ session('status') }}
        </x-alert>
    @endif

    @if ($errors->any())
        <x-alert type="error" class="mb-5" :dismissible="false">
            {{ $errors->first() }}
        </x-alert>
    @endif

    <form method="POST" action="{{ route('login.attempt') }}" wire:submit="authenticate" class="space-y-4">
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

        <x-input
            name="password"
            label="Password"
            type="password"
            wire:model="password"
            placeholder="••••••••"
            autocomplete="current-password"
            required
        />

        <div class="flex items-center justify-between">
            <x-checkbox
                name="remember"
                label="Remember me"
                wire:model="remember"
            />

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700 hover:underline">
                    Forgot password?
                </a>
            @endif
        </div>

        <div class="pt-2">
            <x-button
                type="submit"
                variant="primary"
                class="w-full"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="authenticate">Sign In</span>
                <span wire:loading wire:target="authenticate" class="inline-flex items-center gap-2">
                    <x-loading-spinner size="sm" />
                    Signing in...
                </span>
            </x-button>
        </div>
    </form>

    @if (Route::has('register'))
        <div class="mt-6 text-center text-sm text-text-secondary border-t border-border-default/60 pt-4">
            Don't have an account?
            <a href="{{ route('register') }}" class="font-semibold text-primary-600 hover:text-primary-700 hover:underline">
                Create student account
            </a>
        </div>
    @endif
</div>
