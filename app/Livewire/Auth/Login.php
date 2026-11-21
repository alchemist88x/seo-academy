<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.auth')]
#[Title('Sign In')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->validate();

        $email = Str::lower(trim($this->email));
        $throttleKey = 'login:'.$email.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        if (! Auth::attempt(['email' => $email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        $user = Auth::user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Your account has been suspended. Please contact your administrator.',
            ]);
        }

        session()->regenerate();
        $redirectTo = match (true) {
            $user && method_exists($user, 'hasRole') && $user->hasRole('admin') => route('admin.dashboard'),
            $user && method_exists($user, 'hasRole') && $user->hasRole('trainer') => route('trainer.dashboard'),
            $user && method_exists($user, 'hasRole') && $user->hasRole('support_staff') => route('support.dashboard'),
            default => route('student.dashboard'),
        };

        $this->redirectIntended($redirectTo, navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
