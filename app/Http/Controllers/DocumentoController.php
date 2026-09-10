<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentoRequest;
use App\Http\Requests\UpdateDocumentoRequest;
use App\Models\Documento;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD REST de Documentos — FASE 6.4.4.7
 *
 * Permisos por rol:
 *   ADMIN        → listar, ver, crear, actualizar, eliminar
 *   FISCALIZADOR → listar, ver, crear, actualizar
 *   CONSULTA     → listar, ver
 *
 * La autorización se delega al middleware 'role' registrado en las rutas.
 * Los documentos están vinculados 1-a-1 a una fiscalización existente
 * (UNIQUE en fiscalizacion_id + hasOne desde Fiscalizacion).
 *
 * NOTA: Al eliminar una fiscalización, se elimina en cascada su documento
 * (FK con cascadeOnDelete en la migración).
 */
class DocumentoController extends Controller
{
    /**
     * GET /api/documentos
     * Lista paginada de documentos.
     * Roles: ADMIN, FISCALIZADOR, CONSULTA
     */
    public function index(): JsonResponse
    {
        $documentos = Documento::with(['fiscalizacion'])
            ->orderBy('id', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Lista de documentos.',
            'data'    => $documentos,
        ], Response::HTTP_OK);
    }

    /**
     * GET /api/documentos/{id}
     * Consulta un documento por ID.
     * Roles: ADMIN, FISCALIZADOR, CONSULTA
     */
    public function show(int $id): JsonResponse
    {
        $documento = Documento::with(['fiscalizacion'])->find($id);

        if (! $documento) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Documento encontrado.',
            'data'    => ['documento' => $documento],
        ], Response::HTTP_OK);
    }

    /**
     * POST /api/documentos
     * Crea un nuevo documento.
     * Roles: ADMIN, FISCALIZADOR
     */
    public function store(StoreDocumentoRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $documento = Documento::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Documento creado correctamente.',
            'data'    => ['documento' => $documento->load(['fiscalizacion'])],
        ], Response::HTTP_CREATED);
    }

    /**
     * PUT /api/documentos/{id}
     * Actualiza un documento existente.
     * Roles: ADMIN, FISCALIZADOR
     */
    public function update(UpdateDocumentoRequest $request, int $id): JsonResponse
    {
        $documento = Documento::find($id);

        if (! $documento) {
            return $this->notFound();
        }

        $validated = $request->validated();

        $documento->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Documento actualizado correctamente.',
            'data'    => ['documento' => $documento->fresh()->load(['fiscalizacion'])],
        ], Response::HTTP_OK);
    }

    /**
     * DELETE /api/documentos/{id}
     * Elimina un documento existente.
     * Roles: ADMIN
     *
     * NOTA: Esta eliminación es física.
     */
    public function destroy(int $id): JsonResponse
    {
        $documento = Documento::find($id);

        if (! $documento) {
            return $this->notFound();
        }

        $documento->delete();

        return response()->json([
            'success' => true,
            'message' => 'Documento eliminado correctamente.',
        ], Response::HTTP_OK);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Documento no encontrado.',
        ], Response::HTTP_NOT_FOUND);
    }
}
