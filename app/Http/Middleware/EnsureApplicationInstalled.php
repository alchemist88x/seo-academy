<?php

namespace App\Http\Middleware;

use App\Domain\Platform\Services\DatabaseInstallerService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationInstalled
{
    public function __construct(
        private readonly DatabaseInstallerService $installer,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Allow installer routes and healthcheck to bypass installed verification
        if ($request->routeIs('install.*') || $request->is('install*') || $request->is('up')) {
            return $next($request);
        }

        // If the application is not installed, redirect directly to the installer wizard
        if (! $this->installer->isInstalled()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'SEO Academy has not been installed yet. Please complete the web installer.',
                    'installer_url' => route('install.index'),
                ], 503);
            }

            return redirect()->route('install.index');
        }

        return $next($request);
    }
}
