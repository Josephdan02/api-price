<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFirmaRequest;
use App\Http\Requests\UpdateFirmaRequest;
use App\Models\Firma;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD REST de Firmas — FASE 6.4.4.6
 *
 * Permisos por rol:
 *   ADMIN        → listar, ver, crear, actualizar, eliminar
 *   FISCALIZADOR → listar, ver, crear, actualizar
 *   CONSULTA     → listar, ver
 *
 * La autorización se delega al middleware 'role' registrado en las rutas.
 * Las firmas están vinculadas N-a-1 a una fiscalización existente
 * (una fiscalización puede tener múltiples firmas: FISCALIZADOR + AGENTE).
 *
 * NOTA: Al eliminar una fiscalización, se eliminan en cascada sus firmas
 * (FK con cascadeOnDelete en la migración).
 * NOTA: No existe restricción UNIQUE en el esquema, por lo que no se
 * valida duplicación de fiscalizacion_id + tipo_firma.
 */
class FirmaController extends Controller
{
    /**
     * GET /api/firmas
     * Lista paginada de firmas.
     * Roles: ADMIN, FISCALIZADOR, CONSULTA
     */
    public function index(): JsonResponse
    {
        $firmas = Firma::with(['fiscalizacion'])
            ->orderBy('id', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Lista de firmas.',
            'data'    => $firmas,
        ], Response::HTTP_OK);
    }

    /**
     * GET /api/firmas/{id}
     * Consulta una firma por ID.
     * Roles: ADMIN, FISCALIZADOR, CONSULTA
     */
    public function show(int $id): JsonResponse
    {
        $firma = Firma::with(['fiscalizacion'])->find($id);

        if (! $firma) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Firma encontrada.',
            'data'    => ['firma' => $firma],
        ], Response::HTTP_OK);
    }

    /**
     * POST /api/firmas
     * Crea una nueva firma.
     * Roles: ADMIN, FISCALIZADOR
     */
    public function store(StoreFirmaRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $firma = Firma::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Firma creada correctamente.',
            'data'    => ['firma' => $firma->load(['fiscalizacion'])],
        ], Response::HTTP_CREATED);
    }

    /**
     * PUT /api/firmas/{id}
     * Actualiza una firma existente.
     * Roles: ADMIN, FISCALIZADOR
     */
    public function update(UpdateFirmaRequest $request, int $id): JsonResponse
    {
        $firma = Firma::find($id);

        if (! $firma) {
            return $this->notFound();
        }

        $validated = $request->validated();

        $firma->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Firma actualizada correctamente.',
            'data'    => ['firma' => $firma->fresh()->load(['fiscalizacion'])],
        ], Response::HTTP_OK);
    }

    /**
     * DELETE /api/firmas/{id}
     * Elimina una firma existente.
     * Roles: ADMIN
     *
     * NOTA: Esta eliminación es física.
     */
    public function destroy(int $id): JsonResponse
    {
        $firma = Firma::find($id);

        if (! $firma) {
            return $this->notFound();
        }

        $firma->delete();

        return response()->json([
            'success' => true,
            'message' => 'Firma eliminada correctamente.',
        ], Response::HTTP_OK);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Firma no encontrada.',
        ], Response::HTTP_NOT_FOUND);
    }
}
