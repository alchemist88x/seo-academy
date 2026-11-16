<?php

namespace App\Http\Middleware;

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

        if ($this->tenantContext->getAcademy() === null) {
            abort(403, 'No academy has been configured for this installation.');
        }

        return $next($request);
    }
}
