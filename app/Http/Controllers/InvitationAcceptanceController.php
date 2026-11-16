<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Services\InvitationService;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InvitationAcceptanceController extends Controller
{
    public function show(string $token, InvitationService $invitationService): View
    {
        $invitation = $invitationService->findValidByToken($token);

        if ($invitation === null) {
            return view('invitations.accept', [
                'invalid' => true,
                'invitation' => null,
                'token' => $token,
                'existingUser' => null,
                'emailMismatch' => false,
            ]);
        }

        $authUser = Auth::user();
        $emailMismatch = $authUser !== null
            && Str::lower($authUser->email) !== Str::lower($invitation->email);

        $existingUser = $authUser === null
            ? User::where('email', Str::lower($invitation->email))->first()
            : $authUser;

        return view('invitations.accept', [
            'invalid' => false,
            'invitation' => $invitation,
            'token' => $token,
            'existingUser' => $existingUser,
            'emailMismatch' => $emailMismatch,
        ]);
    }

    public function store(
        Request $request,
        string $token,
        InvitationService $invitationService,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $invitation = $invitationService->findValidByToken($token);

        $user = $invitationService->accept(
            $token,
            $request->only(['name', 'password', 'phone']),
            Auth::user(),
        );

        if (! Auth::check()) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        if ($invitation?->academy !== null) {
            $tenantContext->setAcademy($invitation->academy);
        }

        $targetRoute = match ($invitation?->role) {
            'admin' => 'admin.dashboard',
            'trainer' => 'trainer.dashboard',
            'support_staff' => 'support.dashboard',
            default => 'student.dashboard',
        };

        return redirect()->route($targetRoute);
    }
}
