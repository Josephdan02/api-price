<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la actualización de un incumplimiento.
 *
 * Usa 'sometimes' para que los campos no enviados no sean validados
 * (soporte para PATCH semántico aunque el verbo sea PUT).
 *
 * Reglas de negocio:
 * - No permite duplicar el mismo incumplimiento de catálogo en la misma fiscalización (ignorando el registro actual)
 * - Validación de tipos según esquema
 */
class UpdateFiscalizacionIncumplimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización por rol se maneja en la ruta
    }

    public function rules(): array
    {
        $id = $this->route('incumplimiento');

        return [
            'fiscalizacion_id'          => ['sometimes', 'required', 'exists:fiscalizaciones,id'],
            'incumplimiento_catalogo_id' => ['sometimes', 'required', 'exists:incumplimientos_catalogo,id'],
            'seleccionado'               => ['sometimes', 'boolean'],
            'observacion'                => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $id = $this->route('incumplimiento');
            
            // Si es un string (ID), necesitamos cargar el modelo
            if (is_string($id)) {
                $incumplimiento = \App\Models\FiscalizacionIncumplimiento::find($id);
            } else {
                $incumplimiento = $id;
            }
            
            // Si el incumplimiento no existe, no continuar con validaciones adicionales
            if (! $incumplimiento) {
                return;
            }

            $fiscalizacionId = $this->input('fiscalizacion_id') ?? $incumplimiento->fiscalizacion_id;
            $incumplimientoCatalogoId = $this->input('incumplimiento_catalogo_id') ?? $incumplimiento->incumplimiento_catalogo_id;

            // Validar que no se repita el mismo incumplimiento en la misma fiscalización (ignorando el registro actual)
            if ($fiscalizacionId && $incumplimientoCatalogoId) {
                $exists = \App\Models\FiscalizacionIncumplimiento::where('fiscalizacion_id', $fiscalizacionId)
                    ->where('incumplimiento_catalogo_id', $incumplimientoCatalogoId)
                    ->where('id', '!=', $incumplimiento->id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('incumplimiento_catalogo_id', 'Este incumplimiento ya está registrado en esta fiscalización.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'fiscalizacion_id.required'           => 'La fiscalización es obligatoria.',
            'fiscalizacion_id.exists'             => 'La fiscalización seleccionada no existe.',
            'incumplimiento_catalogo_id.required'  => 'El incumplimiento del catálogo es obligatorio.',
            'incumplimiento_catalogo_id.exists'    => 'El incumplimiento seleccionado no existe en el catálogo.',
            'seleccionado.boolean'                => 'El campo seleccionado debe ser booleano.',
        ];
    }
}
