<?php

namespace App\Http\Middleware;

use App\Domain\Students\Models\StudentProfile;
use App\Domain\Tenants\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAcademyAccess
{
    public function __construct(
        protected TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403, 'Authentication required.');
        }

        if (! $user->is_active) {
            abort(403, 'Your account has been suspended.');
        }

        $academy = $this->tenantContext->getAcademy();
        if ($academy === null) {
            abort(403, 'No academy has been configured for this installation.');
        }

        if ($user->hasRole('student')) {
            $studentProfile = StudentProfile::query()
                ->where('academy_id', $academy->getKey())
                ->where('user_id', $user->getKey())
                ->first();

            if ($studentProfile !== null && ! $studentProfile->active) {
                abort(403, 'Your student account has been suspended.');
            }
        }

        return $next($request);
    }
}
