<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEstablecimientoRequest;
use App\Http\Requests\UpdateEstablecimientoRequest;
use App\Models\Establecimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * Lista paginada de establecimientos con búsqueda y filtros opcionales.
     * Roles: ADMIN, FISCALIZADOR, CONSULTA
     *
     * Query params (todos opcionales):
     *   search       → LIKE en razon_social, nombre_comercial, codigo_osinergmin, ruc_dni
     *   distrito     → LIKE en distrito
     *   departamento → LIKE en departamento
     *   activo       → 1/true/0/false (filter_var BOOLEAN)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Establecimiento::query();

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('razon_social', 'like', "%{$search}%")
                    ->orWhere('nombre_comercial', 'like', "%{$search}%")
                    ->orWhere('codigo_osinergmin', 'like', "%{$search}%")
                    ->orWhere('ruc_dni', 'like', "%{$search}%");
            });
        }

        $distrito = trim((string) $request->query('distrito', ''));
        if ($distrito !== '') {
            $query->where('distrito', 'like', "%{$distrito}%");
        }

        $departamento = trim((string) $request->query('departamento', ''));
        if ($departamento !== '') {
            $query->where('departamento', 'like', "%{$departamento}%");
        }

        if ($request->query('activo') !== null && $request->query('activo') !== '') {
            $activo = filter_var($request->query('activo'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($activo !== null) {
                $query->where('activo', $activo);
            }
        }

        $establecimientos = $query->orderBy('razon_social')
            ->paginate(15)
            ->appends($request->query());

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

    /**
     * GET /api/establecimientos/{id}/fiscalizaciones
     * Fiscalizaciones del establecimiento (paginadas).
     * Roles: ADMIN, FISCALIZADOR, CONSULTA
     */
    public function fiscalizaciones(int $id): JsonResponse
    {
        $establecimiento = Establecimiento::find($id);

        if (! $establecimiento) {
            return $this->notFound();
        }

        $fiscalizaciones = $establecimiento->fiscalizaciones()
            ->with(['establecimiento', 'user'])
            ->orderBy('fecha_diligencia', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Fiscalizaciones del establecimiento.',
            'data'    => $fiscalizaciones,
        ], Response::HTTP_OK);
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Establecimiento no encontrado.',
        ], Response::HTTP_NOT_FOUND);
    }
}
