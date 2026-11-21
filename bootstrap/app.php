<?php

use App\Http\Middleware\EnsureAcademyAccess;
use App\Http\Middleware\EnsureApplicationInstalled;
use App\Http\Middleware\PreventWritesDuringRestore;
use App\Http\Middleware\ResolveAcademyContext;
use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'api/webhooks/*',
        ]);

        $middleware->web(prepend: [
            EnsureApplicationInstalled::class,
            PreventWritesDuringRestore::class,
        ], append: [
            SecurityHeadersMiddleware::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'academy.context' => ResolveAcademyContext::class,
            'academy.access' => EnsureAcademyAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
