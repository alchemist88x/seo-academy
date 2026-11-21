<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Academies\Models\Academy;
use App\Domain\Students\Services\StudentManagementService;
use App\Domain\Tenants\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function showRegistration(TenantContext $tenantContext): View
    {
        $academy = $tenantContext->getAcademy() ?? Academy::current();
        $isRegistrationAllowed = $academy ? (bool) $academy->setting('allow_self_registration', true) : true;

        return view('auth.register', [
            'isRegistrationAllowed' => $isRegistrationAllowed,
        ]);
    }

    public function register(
        Request $request,
        TenantContext $tenantContext,
        StudentManagementService $studentService
    ): RedirectResponse {
        $academy = $tenantContext->getAcademy() ?? Academy::current();

        if ($academy === null) {
            abort(404, 'No academy configured.');
        }

        // Registration throttling: maximum 3 attempts per minute per IP
        $throttleKey = 'register:'.$request->ip();
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

        $user = $studentService->registerStudent($academy, $request->only([
            'first_name',
            'last_name',
            'email',
            'password',
            'password_confirmation',
        ]));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('student.dashboard');
    }
}
