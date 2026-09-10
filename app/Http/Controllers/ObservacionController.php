<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreObservacionRequest;
use App\Http\Requests\UpdateObservacionRequest;
use App\Models\Observacion;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD REST de Observaciones — FASE 6.4.4.5
 *
 * Permisos por rol:
 *   ADMIN        → listar, ver, crear, actualizar, eliminar
 *   FISCALIZADOR → listar, ver, crear, actualizar
 *   CONSULTA     → listar, ver
 *
 * La autorización se delega al middleware 'role' registrado en las rutas.
 * Las observaciones están vinculadas 1-a-1 a una fiscalización existente.
 * 
 * NOTA: Al eliminar una fiscalización, se elimina en cascada su observación
 * (FK con cascadeOnDelete en la migración).
 */
class ObservacionController extends Controller
{
    /**
     * GET /api/observaciones
     * Lista paginada de observaciones.
     * Roles: ADMIN, FISCALIZADOR, CONSULTA
     */
    public function index(): JsonResponse
    {
        $observaciones = Observacion::with(['fiscalizacion'])
            ->orderBy('id', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Lista de observaciones.',
            'data'    => $observaciones,
        ], Response::HTTP_OK);
    }

    /**
     * GET /api/observaciones/{id}
     * Consulta una observación por ID.
     * Roles: ADMIN, FISCALIZADOR, CONSULTA
     */
    public function show(int $id): JsonResponse
    {
        $observacion = Observacion::with(['fiscalizacion'])->find($id);

        if (! $observacion) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Observación encontrada.',
            'data'    => ['observacion' => $observacion],
        ], Response::HTTP_OK);
    }

    /**
     * POST /api/observaciones
     * Crea una nueva observación.
     * Roles: ADMIN, FISCALIZADOR
     */
    public function store(StoreObservacionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $observacion = Observacion::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Observación creada correctamente.',
            'data'    => ['observacion' => $observacion->load(['fiscalizacion'])],
        ], Response::HTTP_CREATED);
    }

    /**
     * PUT /api/observaciones/{id}
     * Actualiza una observación existente.
     * Roles: ADMIN, FISCALIZADOR
     */
    public function update(UpdateObservacionRequest $request, int $id): JsonResponse
    {
        $observacion = Observacion::find($id);

        if (! $observacion) {
            return $this->notFound();
        }

        $validated = $request->validated();

        $observacion->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Observación actualizada correctamente.',
            'data'    => ['observacion' => $observacion->fresh()->load(['fiscalizacion'])],
        ], Response::HTTP_OK);
    }

    /**
     * DELETE /api/observaciones/{id}
     * Elimina una observación existente.
     * Roles: ADMIN
     * 
     * NOTA: Esta eliminación es física.
     */
    public function destroy(int $id): JsonResponse
    {
        $observacion = Observacion::find($id);

        if (! $observacion) {
            return $this->notFound();
        }

        $observacion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Observación eliminada correctamente.',
        ], Response::HTTP_OK);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Observación no encontrada.',
        ], Response::HTTP_NOT_FOUND);
    }
}
