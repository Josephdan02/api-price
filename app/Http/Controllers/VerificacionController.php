<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVerificacionRequest;
use App\Http\Requests\UpdateVerificacionRequest;
use App\Models\Verificacion;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD REST de Verificaciones — FASE 6.4.4.2
 *
 * Permisos por rol:
 *   ADMIN        → listar, ver, crear, actualizar
 *   FISCALIZADOR → listar, ver, crear, actualizar
 *   CONSULTA     → listar, ver
 *
 * La autorización se delega al middleware 'role' registrado en las rutas.
 * Las verificaciones están vinculadas 1-a-1 a una fiscalización existente.
 */
class VerificacionController extends Controller
{
    /**
     * GET /api/verificaciones
     * Lista paginada de verificaciones.
     * Roles: ADMIN, FISCALIZADOR, CONSULTA
     */
    public function index(): JsonResponse
    {
        $verificaciones = Verificacion::with(['fiscalizacion'])
            ->orderBy('id', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Lista de verificaciones.',
            'data'    => $verificaciones,
        ], Response::HTTP_OK);
    }

    /**
     * GET /api/verificaciones/{id}
     * Consulta una verificación por ID.
     * Roles: ADMIN, FISCALIZADOR, CONSULTA
     */
    public function show(int $id): JsonResponse
    {
        $verificacion = Verificacion::with(['fiscalizacion'])->find($id);

        if (! $verificacion) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Verificación encontrada.',
            'data'    => ['verificacion' => $verificacion],
        ], Response::HTTP_OK);
    }

    /**
     * POST /api/verificaciones
     * Crea una nueva verificación.
     * Roles: ADMIN, FISCALIZADOR
     */
    public function store(StoreVerificacionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $verificacion = Verificacion::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Verificación creada correctamente.',
            'data'    => ['verificacion' => $verificacion->load(['fiscalizacion'])],
        ], Response::HTTP_CREATED);
    }

    /**
     * PUT /api/verificaciones/{id}
     * Actualiza una verificación existente.
     * Roles: ADMIN, FISCALIZADOR
     */
    public function update(UpdateVerificacionRequest $request, int $id): JsonResponse
    {
        $verificacion = Verificacion::find($id);

        if (! $verificacion) {
            return $this->notFound();
        }

        $validated = $request->validated();

        $verificacion->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Verificación actualizada correctamente.',
            'data'    => ['verificacion' => $verificacion->fresh()->load(['fiscalizacion'])],
        ], Response::HTTP_OK);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Verificación no encontrada.',
        ], Response::HTTP_NOT_FOUND);
    }
}
