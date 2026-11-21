<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.auth')]
#[Title('Forgot Password')]
class ForgotPassword extends Component
{
    public string $email = '';

    public ?string $status = null;

    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
        ];
    }

    public function sendResetLink(): void
    {
        $throttleKey = 'password.request:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Too many password reset requests. Please try again in {$seconds} seconds.",
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        $this->validate();

        Password::broker()->sendResetLink([
            'email' => $this->email,
        ]);

        $this->status = 'If an account matches that email address, we have sent a password reset link.';
        $this->email = '';
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
