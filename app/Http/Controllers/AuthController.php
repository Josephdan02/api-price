<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controlador de autenticación del sistema PRICE.
 *
 * Endpoints:
 *   POST /api/login   — público
 *   GET  /api/user    — protegido (auth:sanctum)
 *   POST /api/logout  — protegido (auth:sanctum)
 *
 * Identificador de login: DNI (no email).
 * Decisión: usuario inactivo recibe HTTP 403 (autenticado pero prohibido),
 *           no HTTP 401, para distinguirlo de credenciales incorrectas.
 */
class AuthController extends Controller
{
    /**
     * POST /api/login
     * Autentica al usuario por DNI + password y devuelve un token Sanctum.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('dni', $request->dni)->first();

        // DNI no encontrado o password incorrecta → 401
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Usuario inactivo → 403
        if (! $user->activo) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario inactivo. Contacte al administrador.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Revocar tokens anteriores del mismo usuario (sesión única)
        $user->tokens()->delete();

        $token = $user->createToken('price-api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión correcto.',
            'data'    => [
                'token' => $token,
                'user'  => $this->formatUser($user),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * GET /api/user
     * Devuelve la información del usuario autenticado.
     * Ruta protegida por auth:sanctum.
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Usuario autenticado.',
            'data'    => [
                'user' => $this->formatUser($request->user()),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * POST /api/logout
     * Revoca el token actual del usuario autenticado.
     * Ruta protegida por auth:sanctum.
     */
    public function logout(Request $request): JsonResponse
    {
        // Revocar únicamente el token usado en esta petición
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente.',
        ], Response::HTTP_OK);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Formatea el usuario para la respuesta JSON.
     * Nunca expone password ni remember_token.
     */
    private function formatUser(User $user): array
    {
        return [
            'id'     => $user->id,
            'name'   => $user->name,
            'dni'    => $user->dni,
            'email'  => $user->email,
            'rol'    => $user->rol,
            'activo' => $user->activo,
        ];
    }
}
