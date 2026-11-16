<?php

namespace App\Http\Middleware;

use App\Domain\Platform\Services\DatabaseInstallerService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotInstalled
{
    public function __construct(
        private readonly DatabaseInstallerService $installer,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->installer->isInstalled()) {
            // Allow viewing the completion page immediately post-install if flagged in session and method is GET
            if ($request->isMethod('GET') && $request->routeIs('install.complete') && session('install_completed')) {
                return $next($request);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Application is already installed.',
                ], 403);
            }

            return redirect()->route('login');
        }

        return $next($request);
    }
}
