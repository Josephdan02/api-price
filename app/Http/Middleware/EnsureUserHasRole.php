<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de autorización por rol para el sistema PRICE.
 *
 * Uso en rutas:
 *   ->middleware('role:ADMIN')
 *   ->middleware('role:ADMIN,FISCALIZADOR')
 *
 * Roles válidos: ADMIN | FISCALIZADOR | CONSULTA
 *
 * Requisito previo: el usuario debe estar autenticado (auth:sanctum).
 * Este middleware solo evalúa el rol, no la autenticación.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Doble verificación: usuario autenticado y activo
        if (! $user || ! $user->activo) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! in_array($user->rol, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos para realizar esta acción.',
                'rol_requerido' => $roles,
                'rol_actual'    => $user->rol,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
