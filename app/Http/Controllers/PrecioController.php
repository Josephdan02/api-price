<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePrecioRequest;
use App\Http\Requests\UpdatePrecioRequest;
use App\Models\Precio;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD REST de Precios — FASE 6.4.4.1
 *
 * Permisos por rol:
 *   ADMIN        → listar, ver, crear, actualizar
 *   FISCALIZADOR → listar, ver, crear, actualizar
 *   CONSULTA     → listar, ver
 *
 * La autorización se delega al middleware 'role' registrado en las rutas.
 * Los precios están vinculados a una fiscalización existente.
 */
class PrecioController extends Controller
{
    /**
     * GET /api/precios
     * Lista paginada de precios.
     * Roles: ADMIN, FISCALIZADOR, CONSULTA
     */
    public function index(): JsonResponse
    {
        $precios = Precio::with(['fiscalizacion', 'producto'])
            ->orderBy('id', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Lista de precios.',
            'data'    => $precios,
        ], Response::HTTP_OK);
    }

    /**
     * GET /api/precios/{id}
     * Consulta un precio por ID.
     * Roles: ADMIN, FISCALIZADOR, CONSULTA
     */
    public function show(int $id): JsonResponse
    {
        $precio = Precio::with(['fiscalizacion', 'producto'])->find($id);

        if (! $precio) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'message' => 'Precio encontrado.',
            'data'    => ['precio' => $precio],
        ], Response::HTTP_OK);
    }

    /**
     * POST /api/precios
     * Crea un nuevo precio.
     * Roles: ADMIN, FISCALIZADOR
     */
    public function store(StorePrecioRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Si tiene_descuento es true pero no se proporciona precio_descuento, calcularlo
        if (isset($validated['tiene_descuento']) && $validated['tiene_descuento'] && empty($validated['precio_descuento'])) {
            if (isset($validated['precio_price'])) {
                $validated['precio_descuento'] = $validated['precio_price'] * 0.9; // 10% de descuento por defecto
            }
        }

        $precio = Precio::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Precio creado correctamente.',
            'data'    => ['precio' => $precio->load(['fiscalizacion', 'producto'])],
        ], Response::HTTP_CREATED);
    }

    /**
     * PUT /api/precios/{id}
     * Actualiza un precio existente.
     * Roles: ADMIN, FISCALIZADOR
     */
    public function update(UpdatePrecioRequest $request, int $id): JsonResponse
    {
        $precio = Precio::find($id);

        if (! $precio) {
            return $this->notFound();
        }

        $validated = $request->validated();

        // Si tiene_descuento es true pero no se proporciona precio_descuento, calcularlo
        if (isset($validated['tiene_descuento']) && $validated['tiene_descuento'] && empty($validated['precio_descuento'])) {
            if (isset($validated['precio_price'])) {
                $validated['precio_descuento'] = $validated['precio_price'] * 0.9;
            }
        }

        $precio->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Precio actualizado correctamente.',
            'data'    => ['precio' => $precio->fresh()->load(['fiscalizacion', 'producto'])],
        ], Response::HTTP_OK);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Precio no encontrado.',
        ], Response::HTTP_NOT_FOUND);
    }
}
