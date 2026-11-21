<?php

namespace App\Livewire\Auth;

use App\Domain\Academies\Models\Academy;
use App\Domain\Students\Services\StudentManagementService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.auth')]
#[Title('Student Registration')]
class Register extends Component
{
    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $isRegistrationAllowed = true;

    protected function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:8'],
        ];
    }

    public function mount(TenantContext $tenantContext): void
    {
        $academy = $tenantContext->getAcademy() ?? Academy::current();
        $this->isRegistrationAllowed = $academy ? (bool) $academy->setting('allow_self_registration', true) : true;
    }

    public function register(TenantContext $tenantContext, StudentManagementService $studentService): void
    {
        $academy = $tenantContext->getAcademy() ?? Academy::current();

        if ($academy === null) {
            abort(404, 'No academy configured.');
        }

        // Registration throttling: maximum 3 attempts per minute per IP
        $throttleKey = 'register:'.request()->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Too many registration attempts. Please try again in {$seconds} seconds.",
            ]);
        }
        RateLimiter::hit($throttleKey, 60);

        if (! (bool) $academy->setting('allow_self_registration', true)) {
            throw ValidationException::withMessages([
                'email' => 'Student self-registration is currently disabled for this academy.',
            ]);
        }

        $this->validate();

        $user = $studentService->registerStudent($academy, [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]);

        Auth::login($user);
        session()->regenerate();

        $this->redirectRoute('student.dashboard', navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
