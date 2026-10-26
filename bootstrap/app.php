<?php

use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\EnsureTiendaOnlineHabilitada;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'module' => EnsureModuleAccess::class,
            'tienda.enabled' => EnsureTiendaOnlineHabilitada::class,
        ]);

        // Mercado Pago y Tiendanube llaman a estos endpoints sin token CSRF.
        $middleware->validateCsrfTokens(except: [
            'mp/webhook',
            'tiendanube/webhook',
            'tienda/mp/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Sin SENTRY_LARAVEL_DSN en el .env, Sentry queda inactivo (no manda
        // nada) — pero esta llamada solo es segura si el paquete
        // sentry/sentry-laravel está instalado en el vendor/ de ESTE hosting
        // puntual. No todos los despliegues lo tienen (se agregó en un commit
        // posterior al último deploy de alguno de ellos), y sin este guard
        // tira "Class not found" en cada request, incluso con Sentry inactivo.
        if (class_exists(Integration::class)) {
            Integration::handles($exceptions);
        }
    })->create();
