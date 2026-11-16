<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => __('auth.failed')])
                ->onlyInput('email');
        }

        $user = Auth::user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Your account has been suspended. Please contact your administrator.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        $redirectTo = match (true) {
            $user && method_exists($user, 'hasRole') && $user->hasRole('admin') => route('admin.dashboard'),
            $user && method_exists($user, 'hasRole') && $user->hasRole('trainer') => route('trainer.dashboard'),
            $user && method_exists($user, 'hasRole') && $user->hasRole('support_staff') => route('support.dashboard'),
            default => route('student.dashboard'),
        };

        return redirect()->intended($redirectTo);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
