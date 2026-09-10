<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
        | FASE SEGURIDAD — Rate limiting del único endpoint público (POST /api/login).
        |
        | Límite: 5 intentos por minuto por combinación de DNI + IP.
        | El identificador del login en PRICE es el DNI (LoginRequest), por lo que
        | la clave del limiter usa el DNI enviado en el intento junto con la IP
        | del cliente. Al agotarse el límite, ThrottleRequests responde HTTP 429.
        |
        | No limita al usuario legítimo de la demo académica: si el cliente acierta
        | credenciales dentro de la ventana, el login sigue respondiendo 200.
        */
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(($request->input('dni') ?: 'anon').'|'.$request->ip());
        });
    }
}
