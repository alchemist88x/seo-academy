<?php

namespace App\Http\Middleware;

use App\Domain\Academies\Models\Academy;
use App\Domain\Tenants\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveAcademyContext
{
    public function __construct(
        protected TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $academy = Academy::current();

        if ($academy !== null) {
            $this->tenantContext->setAcademy($academy);
        } else {
            $this->tenantContext->clear();
        }

        return $next($request);
    }
}
