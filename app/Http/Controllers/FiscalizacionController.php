<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFiscalizacionRequest;
use App\Http\Requests\UpdateFiscalizacionRequest;
use App\Models\Fiscalizacion;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD REST de Fiscalizaciones — FASE 6.4.3
 *
 * Permisos por rol:
 *   ADMIN        → listar, ver, crear, actualizar
 *   FISCALIZADOR → listar, ver, crear, actualizar
 *   CONSULTA     → listar, ver
 *
 * La autorización se delega al middleware 'role' registrado en las rutas.
 */
class FiscalizacionController extends Controller
{
    /**
     * GET /api/fiscalizaciones
     * Lista paginada de fiscalizaciones.
     * Roles: ADMIN, FISCALIZADOR, CONSULTA
     */
    public function index(): JsonResponse
    {
        $fiscalizaciones = Fiscalizacion::with(['establecimiento', 'user'])
            ->orderBy('fecha_diligencia', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Lista de fiscalizaciones.',
            'data'    => $fiscalizaciones,
        ], Response::HTTP_OK);
    }

    /**
     * GET /api/fiscalizaciones/{id}
     * Consulta una fiscalización por ID con sus relaciones.
     * Roles: ADMIN, FISCALIZADOR, CONSULTA
     */
    public function show(int $id): JsonResponse
    {
        $fiscalizacion = Fiscalizacion::with([
            'establecimiento',
            'user',
            'precios',
            'verificacion',
            'fiscalizacionIncumplimientos.incumplimientoCatalogo',
            'hechosVerificados',
            'observacion',
            'firmas',
            'documento'
        ])->find($id);

        if (! $fiscalizacion) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Fiscalización encontrada.',
            'data'    => ['fiscalizacion' => $fiscalizacion],
        ], Response::HTTP_OK);
    }

    /**
     * POST /api/fiscalizaciones
     * Crea una nueva fiscalización.
     * Roles: ADMIN, FISCALIZADOR
     */
    public function store(StoreFiscalizacionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Si se proporciona establecimiento, sincronizar datos desnormalizados
        if (isset($validated['establecimiento_id'])) {
            $establecimiento = \App\Models\Establecimiento::find($validated['establecimiento_id']);
            if ($establecimiento) {
                $validated['agente_fiscalizado'] = $establecimiento->razon_social;
                $validated['codigo_osinergmin'] = $establecimiento->codigo_osinergmin;
                $validated['registro_hidrocarburos'] = $establecimiento->registro_hidrocarburos;
                $validated['direccion'] = $establecimiento->direccion;
                $validated['distrito'] = $establecimiento->distrito;
                $validated['provincia'] = $establecimiento->provincia;
                $validated['departamento'] = $establecimiento->departamento;
                $validated['ruc_dni'] = $establecimiento->ruc_dni;
                $validated['telefono_fax'] = $establecimiento->telefono;
            }
        }

        $fiscalizacion = Fiscalizacion::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Fiscalización creada correctamente.',
            'data'    => ['fiscalizacion' => $fiscalizacion->load(['establecimiento', 'user'])],
        ], Response::HTTP_CREATED);
    }

    /**
     * PUT /api/fiscalizaciones/{id}
     * Actualiza una fiscalización existente.
     * Roles: ADMIN, FISCALIZADOR
     */
    public function update(UpdateFiscalizacionRequest $request, int $id): JsonResponse
    {
        $fiscalizacion = Fiscalizacion::find($id);

        if (! $fiscalizacion) {
            return $this->notFound();
        }

        $validated = $request->validated();

        // Si cambia el establecimiento, actualizar datos desnormalizados
        if (isset($validated['establecimiento_id']) && $validated['establecimiento_id'] != $fiscalizacion->establecimiento_id) {
            $establecimiento = \App\Models\Establecimiento::find($validated['establecimiento_id']);
            if ($establecimiento) {
                $validated['agente_fiscalizado'] = $establecimiento->razon_social;
                $validated['codigo_osinergmin'] = $establecimiento->codigo_osinergmin;
                $validated['registro_hidrocarburos'] = $establecimiento->registro_hidrocarburos;
                $validated['direccion'] = $establecimiento->direccion;
                $validated['distrito'] = $establecimiento->distrito;
                $validated['provincia'] = $establecimiento->provincia;
                $validated['departamento'] = $establecimiento->departamento;
                $validated['ruc_dni'] = $establecimiento->ruc_dni;
                $validated['telefono_fax'] = $establecimiento->telefono;
            }
        }

        $fiscalizacion->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Fiscalización actualizada correctamente.',
            'data'    => ['fiscalizacion' => $fiscalizacion->fresh()->load(['establecimiento', 'user'])],
        ], Response::HTTP_OK);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Fiscalización no encontrada.',
        ], Response::HTTP_NOT_FOUND);
    }
}
