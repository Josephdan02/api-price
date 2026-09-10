<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la creación de un incumplimiento.
 *
 * Campos obligatorios: fiscalizacion_id, incumplimiento_catalogo_id
 * Reglas de negocio:
 * - No permite duplicar el mismo incumplimiento de catálogo en la misma fiscalización
 * - Validación de tipos según esquema
 */
class StoreFiscalizacionIncumplimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización por rol se maneja en la ruta
    }

    public function rules(): array
    {
        return [
            'fiscalizacion_id'          => ['required', 'exists:fiscalizaciones,id'],
            'incumplimiento_catalogo_id' => ['required', 'exists:incumplimientos_catalogo,id'],
            'seleccionado'               => ['boolean'],
            'observacion'                => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $fiscalizacionId = $this->input('fiscalizacion_id');
            $incumplimientoCatalogoId = $this->input('incumplimiento_catalogo_id');

            // Validar que no se repita el mismo incumplimiento en la misma fiscalización
            if ($fiscalizacionId && $incumplimientoCatalogoId) {
                $exists = \App\Models\FiscalizacionIncumplimiento::where('fiscalizacion_id', $fiscalizacionId)
                    ->where('incumplimiento_catalogo_id', $incumplimientoCatalogoId)
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
