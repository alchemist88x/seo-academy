@component('layouts.auth', ['title' => 'Accept Invitation'])
    @if ($invalid)
        <div class="text-center space-y-4">
            <div class="mx-auto h-12 w-12 rounded-full bg-red-100 dark:bg-red-950/60 text-red-600 dark:text-red-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-text-primary">Invalid or Expired Invitation</h2>
            <p class="text-sm text-text-secondary">
                This invitation link is invalid, has expired, or has already been used or revoked. Please contact your academy administrator for a new invitation.
            </p>
            <div class="pt-2">
                <a href="{{ route('login') }}" class="btn btn-primary btn-md w-full">
                    Go to Sign In
                </a>
            </div>
        </div>
    @else
        <div class="space-y-5">
            <div>
                <h2 class="text-xl font-bold text-text-primary">You're Invited!</h2>
                <p class="mt-1 text-sm text-text-secondary">
                    You have been invited to join
                    <strong class="text-text-primary">{{ $invitation->academy?->name ?? 'SEO Academy' }}</strong>
                    as <strong class="text-text-primary">{{ ucfirst(str_replace('_', ' ', $invitation->role)) }}</strong>.
                </p>
            </div>

            <div class="p-3 rounded-lg bg-surface-bg border border-border-default text-xs text-text-secondary space-y-1">
                <div><span class="font-medium text-text-primary">Invited Email:</span> {{ $invitation->email }}</div>
                <div><span class="font-medium text-text-primary">Expires:</span> {{ $invitation->expires_at?->format('M d, Y H:i') }}</div>
            </div>

            @if ($errors->any())
                <x-alert type="error" :dismissible="false">
                    {{ $errors->first() }}
                </x-alert>
            @endif

            @if ($emailMismatch)
                <x-alert type="warning" :dismissible="false">
                    You are currently signed in as <strong>{{ auth()->user()->email }}</strong>, which does not match <strong>{{ $invitation->email }}</strong>. Please sign out first to accept this invitation.
                </x-alert>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-button type="submit" variant="secondary" class="w-full">
                        Sign Out
                    </x-button>
                </form>
            @elseif ($existingUser !== null)
                <form method="POST" action="{{ route('invitations.accept.store', ['token' => $token]) }}" class="space-y-4">
                    @csrf
                    <p class="text-sm text-text-secondary">
                        An account already exists for <strong class="text-text-primary">{{ $existingUser->email }}</strong>. Confirm below to join this academy.
                    </p>
                    <x-button type="submit" variant="primary" class="w-full">
                        Accept Invitation &amp; Join Academy
                    </x-button>
                </form>
            @else
                <form method="POST" action="{{ route('invitations.accept.store', ['token' => $token]) }}" class="space-y-4">
                    @csrf
                    <x-input
                        name="name"
                        label="Full Name"
                        :value="old('name', $invitation->name)"
                        required
                    />

                    <x-input
                        name="phone"
                        label="Phone (Optional)"
                        :value="old('phone')"
                    />

                    <x-input
                        name="password"
                        label="Create Password"
                        type="password"
                        required
                        hint="Minimum 8 characters."
                    />

                    <x-button type="submit" variant="primary" class="w-full">
                        Create Account &amp; Accept Invitation
                    </x-button>
                </form>
            @endif
        </div>
    @endif
@endcomponent
