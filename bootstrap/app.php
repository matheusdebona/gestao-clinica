<?php

use App\Exceptions\PdfRenderException;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\ResolveCurrentClinic;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'clinic.resolve' => ResolveCurrentClinic::class,
            'password.changed' => EnsurePasswordChanged::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Resolve the clinic before route-model binding so {client}, {lead}, … are
        // looked up through the BelongsToClinic scope (other clinics' ids → 404).
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveCurrentClinic::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (PdfRenderException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    'pdf' => [$exception->getMessage()],
                ],
            ], 503);
        });
    })->create();
