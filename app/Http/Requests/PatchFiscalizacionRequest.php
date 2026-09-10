<?php

namespace App\Http\Requests;

use App\Models\Fiscalizacion;

/**
 * Valida la actualización parcial (PATCH) de una fiscalización — FASE 6.4.7
 *
 * Reutiliza TODOS los campos, reglas y mensajes de UpdateFiscalizacionRequest
 * mediante herencia ('sometimes' → actualización parcial, igual que PUT).
 *
 * Diferencia con PUT:
 * El action del controlador recibe `int $id` (no `$fiscalizacion`), por lo que
 * Laravel NO resuelve route-model binding para `{fiscalizacion}` y
 * `$this->route('fiscalizacion')` devuelve un string. Por eso aquí se resuelve
 * el modelo explícitamente (mismo patrón que UpdateObservacionRequest /
 * UpdatePrecioRequest / UpdateVerificacionRequest) para poder aplicar las
 * reglas de negocio de transición de estados y de fiscalizador responsable
 * ya definidas en el proyecto.
 */
class PatchFiscalizacionRequest extends UpdateFiscalizacionRequest
{
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $fiscalizacion = $this->resolverFiscalizacion();

            if (! $fiscalizacion) {
                return;
            }

            $estado = $this->input('estado');
            $userId = $this->input('user_id') ?? $fiscalizacion->user_id;

            // No permitir finalizar sin fiscalizador responsable
            if (in_array($estado, [Fiscalizacion::ESTADO_FINALIZADA, Fiscalizacion::ESTADO_ACTA_GENERADA], true) && empty($userId)) {
                $validator->errors()->add('user_id', 'No se puede finalizar una fiscalización sin fiscalizador responsable.');
            }

            // Validar transiciones de estado lógicas (solo si se envía un estado diferente)
            if ($estado && $estado !== $fiscalizacion->estado) {
                $transicionesValidas = [
                    Fiscalizacion::ESTADO_BORRADOR   => [Fiscalizacion::ESTADO_EN_PROCESO],
                    Fiscalizacion::ESTADO_EN_PROCESO => [Fiscalizacion::ESTADO_FINALIZADA],
                    Fiscalizacion::ESTADO_FINALIZADA => [Fiscalizacion::ESTADO_ACTA_GENERADA],
                ];

                $estadoActual = $fiscalizacion->estado;

                if (isset($transicionesValidas[$estadoActual]) && ! in_array($estado, $transicionesValidas[$estadoActual], true)) {
                    $validator->errors()->add('estado', 'Transición de estado no permitida.');
                }
            }
        });
    }

    /**
     * Resuelve la fiscalización del parámetro de ruta (string ID o modelo).
     */
    private function resolverFiscalizacion(): ?Fiscalizacion
    {
        $parametro = $this->route('fiscalizacion');

        if ($parametro instanceof Fiscalizacion) {
            return $parametro;
        }

        if (is_string($parametro) && $parametro !== '') {
            return Fiscalizacion::find($parametro);
        }

        if (is_numeric($parametro)) {
            return Fiscalizacion::find((int) $parametro);
        }

        return null;
    }
}
