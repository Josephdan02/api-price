<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la creación de una observación.
 *
 * Campos obligatorios: fiscalizacion_id
 * Reglas de negocio:
 * - No permite duplicar observación en la misma fiscalización (relación 1-a-1)
 * - Validación de tipos según esquema
 */
class StoreObservacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización por rol se maneja en la ruta
    }

    public function rules(): array
    {
        return [
            'fiscalizacion_id'           => ['required', 'exists:fiscalizaciones,id'],
            'otras_ocurrencias'           => ['nullable', 'string'],
            'documentacion_recabada'      => ['nullable', 'string'],
            'manifestaciones_agente'      => ['nullable', 'string'],
            'negativa_identificacion'     => ['boolean'],
            'negativa_suscripcion'        => ['boolean'],
            'negativa_recepcion'          => ['boolean'],
            'observaciones_generales'     => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $fiscalizacionId = $this->input('fiscalizacion_id');

            // Validar que no exista ya una observación para esta fiscalización (relación 1-a-1)
            if ($fiscalizacionId) {
                $exists = \App\Models\Observacion::where('fiscalizacion_id', $fiscalizacionId)->exists();

                if ($exists) {
                    $validator->errors()->add('fiscalizacion_id', 'Esta fiscalización ya tiene una observación registrada.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'fiscalizacion_id.required'           => 'La fiscalización es obligatoria.',
            'fiscalizacion_id.exists'             => 'La fiscalización seleccionada no existe.',
            'negativa_identificacion.boolean'     => 'El campo negativa_identificación debe ser booleano.',
            'negativa_suscripcion.boolean'        => 'El campo negativa_suscripción debe ser booleano.',
            'negativa_recepcion.boolean'          => 'El campo negativa_recepción debe ser booleano.',
        ];
    }
}
