<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFiscalizacionIncumplimientoRequest;
use App\Http\Requests\UpdateFiscalizacionIncumplimientoRequest;
use App\Models\FiscalizacionIncumplimiento;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD REST de Incumplimientos — FASE 6.4.4.3
 *
 * Permisos por rol:
 *   ADMIN        → listar, ver, crear, actualizar, eliminar
 *   FISCALIZADOR → listar, ver, crear, actualizar
 *   CONSULTA     → listar, ver
 *
 * La autorización se delega al middleware 'role' registrado en las rutas.
 * Los incumplimientos están vinculados a una fiscalización y al catálogo de incumplimientos.
 * 
 * NOTA: Al eliminar un incumplimiento, se eliminan en cascada sus hechos verificados
 * (FK con cascadeOnDelete en la migración de hechos_verificados).
 */
class FiscalizacionIncumplimientoController extends Controller
{
    /**
     * GET /api/incumplimientos
     * Lista paginada de incumplimientos.
     * Roles: ADMIN, FISCALIZADOR, CONSULTA
     */
    public function index(): JsonResponse
    {
        $incumplimientos = FiscalizacionIncumplimiento::with(['fiscalizacion', 'incumplimientoCatalogo'])
            ->orderBy('id', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Lista de incumplimientos.',
            'data'    => $incumplimientos,
        ], Response::HTTP_OK);
    }

    /**
     * GET /api/incumplimientos/{id}
     * Consulta un incumplimiento por ID.
     * Roles: ADMIN, FISCALIZADOR, CONSULTA
     */
    public function show(int $id): JsonResponse
    {
        $incumplimiento = FiscalizacionIncumplimiento::with(['fiscalizacion', 'incumplimientoCatalogo', 'hechosVerificados'])->find($id);

        if (! $incumplimiento) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Incumplimiento encontrado.',
            'data'    => ['incumplimiento' => $incumplimiento],
        ], Response::HTTP_OK);
    }

    /**
     * POST /api/incumplimientos
     * Crea un nuevo incumplimiento.
     * Roles: ADMIN, FISCALIZADOR
     */
    public function store(StoreFiscalizacionIncumplimientoRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $incumplimiento = FiscalizacionIncumplimiento::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Incumplimiento creado correctamente.',
            'data'    => ['incumplimiento' => $incumplimiento->load(['fiscalizacion', 'incumplimientoCatalogo'])],
        ], Response::HTTP_CREATED);
    }

    /**
     * PUT /api/incumplimientos/{id}
     * Actualiza un incumplimiento existente.
     * Roles: ADMIN, FISCALIZADOR
     */
    public function update(UpdateFiscalizacionIncumplimientoRequest $request, int $id): JsonResponse
    {
        $incumplimiento = FiscalizacionIncumplimiento::find($id);

        if (! $incumplimiento) {
            return $this->notFound();
        }

        $validated = $request->validated();

        $incumplimiento->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Incumplimiento actualizado correctamente.',
            'data'    => ['incumplimiento' => $incumplimiento->fresh()->load(['fiscalizacion', 'incumplimientoCatalogo'])],
        ], Response::HTTP_OK);
    }

    /**
     * DELETE /api/incumplimientos/{id}
     * Elimina un incumplimiento existente.
     * Roles: ADMIN
     * 
     * NOTA: Esta eliminación es física y elimina en cascada los hechos verificados
     * asociados (FK con cascadeOnDelete en hechos_verificados).
     */
    public function destroy(int $id): JsonResponse
    {
        $incumplimiento = FiscalizacionIncumplimiento::find($id);

        if (! $incumplimiento) {
            return $this->notFound();
        }

        $incumplimiento->delete();

        return response()->json([
            'success' => true,
            'message' => 'Incumplimiento eliminado correctamente.',
        ], Response::HTTP_OK);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Incumplimiento no encontrado.',
        ], Response::HTTP_NOT_FOUND);
    }
}
