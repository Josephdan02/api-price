<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHechoVerificadoRequest;
use App\Http\Requests\UpdateHechoVerificadoRequest;
use App\Models\HechoVerificado;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD REST de Hechos Verificados — FASE 6.4.4.4
 *
 * Permisos por rol:
 *   ADMIN        → listar, ver, crear, actualizar, eliminar
 *   FISCALIZADOR → listar, ver, crear, actualizar
 *   CONSULTA     → listar, ver
 *
 * La autorización se delega al middleware 'role' registrado en las rutas.
 * Los hechos verificados documentan evidencia específica de un incumplimiento
 * dentro de una fiscalización.
 * 
 * NOTA: Al eliminar una fiscalización o un incumplimiento, se eliminan en cascada
 * sus hechos verificados (FK con cascadeOnDelete en la migración).
 */
class HechoVerificadoController extends Controller
{
    /**
     * GET /api/hechos-verificados
     * Lista paginada de hechos verificados.
     * Roles: ADMIN, FISCALIZADOR, CONSULTA
     */
    public function index(): JsonResponse
    {
        $hechos = HechoVerificado::with(['fiscalizacion', 'fiscalizacionIncumplimiento', 'user'])
            ->orderBy('id', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Lista de hechos verificados.',
            'data'    => $hechos,
        ], Response::HTTP_OK);
    }

    /**
     * GET /api/hechos-verificados/{id}
     * Consulta un hecho verificado por ID.
     * Roles: ADMIN, FISCALIZADOR, CONSULTA
     */
    public function show(int $id): JsonResponse
    {
        $hecho = HechoVerificado::with(['fiscalizacion', 'fiscalizacionIncumplimiento', 'user'])->find($id);

        if (! $hecho) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Hecho verificado encontrado.',
            'data'    => ['hecho_verificado' => $hecho],
        ], Response::HTTP_OK);
    }

    /**
     * POST /api/hechos-verificados
     * Crea un nuevo hecho verificado.
     * Roles: ADMIN, FISCALIZADOR
     */
    public function store(StoreHechoVerificadoRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $hecho = HechoVerificado::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Hecho verificado creado correctamente.',
            'data'    => ['hecho_verificado' => $hecho->load(['fiscalizacion', 'fiscalizacionIncumplimiento', 'user'])],
        ], Response::HTTP_CREATED);
    }

    /**
     * PUT /api/hechos-verificados/{id}
     * Actualiza un hecho verificado existente.
     * Roles: ADMIN, FISCALIZADOR
     */
    public function update(UpdateHechoVerificadoRequest $request, int $id): JsonResponse
    {
        $hecho = HechoVerificado::find($id);

        if (! $hecho) {
            return $this->notFound();
        }

        $validated = $request->validated();

        $hecho->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Hecho verificado actualizado correctamente.',
            'data'    => ['hecho_verificado' => $hecho->fresh()->load(['fiscalizacion', 'fiscalizacionIncumplimiento', 'user'])],
        ], Response::HTTP_OK);
    }

    /**
     * DELETE /api/hechos-verificados/{id}
     * Elimina un hecho verificado existente.
     * Roles: ADMIN
     * 
     * NOTA: Esta eliminación es física.
     */
    public function destroy(int $id): JsonResponse
    {
        $hecho = HechoVerificado::find($id);

        if (! $hecho) {
            return $this->notFound();
        }

        $hecho->delete();

        return response()->json([
            'success' => true,
            'message' => 'Hecho verificado eliminado correctamente.',
        ], Response::HTTP_OK);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Hecho verificado no encontrado.',
        ], Response::HTTP_NOT_FOUND);
    }
}
