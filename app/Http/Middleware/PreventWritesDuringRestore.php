<?php

namespace App\Http\Middleware;

use App\Domain\Platform\Services\BackupService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventWritesDuringRestore
{
    /**
     * Handle an incoming request.
     *
     * If a backup restoration is currently in progress, reject any state-modifying requests
     * (POST, PUT, PATCH, DELETE) with a 503 Service Unavailable error immediately,
     * protecting active database and files from concurrent writes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (BackupService::isRestorationInProgress()) {
            if (in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'message' => 'System restoration in progress. State-modifying requests are temporarily blocked.',
                    ], 503);
                }

                return response(
                    '<h1>503 Service Unavailable</h1><p>System restoration is currently in progress. Database and file writes are temporarily locked. Please retry in a few moments.</p>',
                    503,
                    ['Retry-After' => '30']
                );
            }
        }

        return $next($request);
    }
}
