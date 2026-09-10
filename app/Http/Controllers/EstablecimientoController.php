<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEstablecimientoRequest;
use App\Http\Requests\UpdateEstablecimientoRequest;
use App\Models\Establecimiento;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD REST de Establecimientos — FASE 6.4.2
 *
 * Permisos por rol:
 *   ADMIN        → listar, ver, crear, actualizar, eliminar
 *   FISCALIZADOR → listar, ver, crear, actualizar
 *   CONSULTA     → listar, ver
 *
 * La autorización se delega al middleware 'role' registrado en las rutas.
 */
class EstablecimientoController extends Controller
{
    /**
     * GET /api/establecimientos
     * Lista paginada de establecimientos.
     * Roles: ADMIN, FISCALIZADOR, CONSULTA
     */
    public function index(): JsonResponse
    {
        $establecimientos = Establecimiento::orderBy('razon_social')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Lista de establecimientos.',
            'data'    => $establecimientos,
        ], Response::HTTP_OK);
    }

    /**
     * GET /api/establecimientos/{id}
     * Consulta un establecimiento por ID.
     * Roles: ADMIN, FISCALIZADOR, CONSULTA
     */
    public function show(int $id): JsonResponse
    {
        $establecimiento = Establecimiento::find($id);

        if (! $establecimiento) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Establecimiento encontrado.',
            'data'    => ['establecimiento' => $establecimiento],
        ], Response::HTTP_OK);
    }

    /**
     * POST /api/establecimientos
     * Crea un nuevo establecimiento.
     * Roles: ADMIN, FISCALIZADOR
     */
    public function store(StoreEstablecimientoRequest $request): JsonResponse
    {
        $establecimiento = Establecimiento::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Establecimiento creado correctamente.',
            'data'    => ['establecimiento' => $establecimiento],
        ], Response::HTTP_CREATED);
    }

    /**
     * PUT /api/establecimientos/{id}
     * Actualiza un establecimiento existente.
     * Roles: ADMIN, FISCALIZADOR
     */
    public function update(UpdateEstablecimientoRequest $request, int $id): JsonResponse
    {
        $establecimiento = Establecimiento::find($id);

        if (! $establecimiento) {
            return $this->notFound();
        }

        $establecimiento->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Establecimiento actualizado correctamente.',
            'data'    => ['establecimiento' => $establecimiento->fresh()],
        ], Response::HTTP_OK);
    }

    /**
     * DELETE /api/establecimientos/{id}
     * Elimina un establecimiento.
     * Roles: ADMIN
     */
    public function destroy(int $id): JsonResponse
    {
        $establecimiento = Establecimiento::find($id);

        if (! $establecimiento) {
            return $this->notFound();
        }

        $establecimiento->delete();

        return response()->json([
            'success' => true,
            'message' => 'Establecimiento eliminado correctamente.',
        ], Response::HTTP_OK);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Establecimiento no encontrado.',
        ], Response::HTTP_NOT_FOUND);
    }
}
