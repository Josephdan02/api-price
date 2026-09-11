<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias de middleware para autorización por rol PRICE
        // Uso: ->middleware('role:ADMIN') o ->middleware('role:ADMIN,FISCALIZADOR')
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);

        // Las rutas API no deben intentar redirigir a una ruta web "login".
        // Si no están autenticadas, deben responder 401 JSON.
        $middleware->redirectGuestsTo(function ($request) {
            return $request->is('api/*') ? null : route('login');
        });

        // Producción: detrás del proxy inverso de la plataforma (Render/etc.)
        // la IP real del cliente llega en X-Forwarded-For. Sin esto, Laravel
        // ve la IP del balanceador y el rate limiter de login
        // (AppServiceProvider: 5/min por DNI+IP) comparte una única cubeta
        // entre todos los usuarios. '*' = confiar en la IP que llama
        // directamente (comportamiento estándar en PaaS; middleware global,
        // aplica a API y web).
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function ($request) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })
    ->create();