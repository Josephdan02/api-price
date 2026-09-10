<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la actualización de una observación.
 *
 * Usa 'sometimes' para que los campos no enviados no sean validados
 * (soporte para PATCH semántico aunque el verbo sea PUT).
 *
 * Reglas de negocio:
 * - No permite cambiar fiscalizacion_id a una que ya tenga observación (ignorando el registro actual)
 * - Validación de tipos según esquema
 */
class UpdateObservacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización por rol se maneja en la ruta
    }

    public function rules(): array
    {
        return [
            'fiscalizacion_id'           => ['sometimes', 'required', 'exists:fiscalizaciones,id'],
            'otras_ocurrencias'           => ['sometimes', 'nullable', 'string'],
            'documentacion_recabada'      => ['sometimes', 'nullable', 'string'],
            'manifestaciones_agente'      => ['sometimes', 'nullable', 'string'],
            'negativa_identificacion'     => ['sometimes', 'boolean'],
            'negativa_suscripcion'        => ['sometimes', 'boolean'],
            'negativa_recepcion'          => ['sometimes', 'boolean'],
            'observaciones_generales'     => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $id = $this->route('observacion');
            
            // Si es un string (ID), necesitamos cargar el modelo
            if (is_string($id)) {
                $observacion = \App\Models\Observacion::find($id);
            } else {
                $observacion = $id;
            }
            
            // Si la observación no existe, no continuar con validaciones adicionales
            if (! $observacion) {
                return;
            }

            $fiscalizacionId = $this->input('fiscalizacion_id') ?? $observacion->fiscalizacion_id;

            // Validar que no se cambie a una fiscalización que ya tenga observación (ignorando el registro actual)
            if ($fiscalizacionId) {
                $exists = \App\Models\Observacion::where('fiscalizacion_id', $fiscalizacionId)
                    ->where('id', '!=', $observacion->id)
                    ->exists();

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
