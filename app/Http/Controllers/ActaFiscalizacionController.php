<?php

namespace App\Http\Controllers;

use App\Models\Fiscalizacion;
use App\Services\ActaFiscalizacionPdfService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Acta de Fiscalización en PDF — FASE 6.4.5.
 *
 * GET /api/fiscalizaciones/{fiscalizacion}/acta/pdf
 * Roles: ADMIN, FISCALIZADOR, CONSULTA (solo lectura, no muta datos salvo Documento).
 *
 * No modifica el estado de la fiscalización.
 */
class ActaFiscalizacionController extends Controller
{
    /**
     * Genera (o regenera de forma idempotente) el acta en PDF.
     */
    public function pdf(int $fiscalizacion, ActaFiscalizacionPdfService $service): Response
    {
        $f = Fiscalizacion::find($fiscalizacion);

        if (! $f) {
            return response()->json([
                'success' => false,
                'message' => 'Fiscalización no encontrada.',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $resultado = $service->generar($f);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo generar el acta en PDF.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response($resultado['bytes'], Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $resultado['filename'] . '"',
            'Content-Length' => (string) strlen($resultado['bytes']),
            'X-Acta-Pages' => (string) $resultado['pages'],
            'X-Acta-Documento-Id' => (string) $resultado['documento']->id,
        ]);
    }
}
