<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la creación de un hecho verificado.
 *
 * Campos obligatorios: fiscalizacion_id, fiscalizacion_incumplimiento_id, descripcion
 * Campos opcionales: user_id, fecha_registro
 *
 * Un hecho verificado documenta evidencia específica de un incumplimiento
 * dentro de una fiscalización.
 */
class StoreHechoVerificadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización por rol se maneja en la ruta
    }

    public function rules(): array
    {
        return [
            'fiscalizacion_id'                  => ['required', 'exists:fiscalizaciones,id'],
            'fiscalizacion_incumplimiento_id'   => ['required', 'exists:fiscalizacion_incumplimientos,id'],
            'user_id'                           => ['nullable', 'exists:users,id'],
            'descripcion'                       => ['required', 'string'],
            'fecha_registro'                   => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'fiscalizacion_id.required'                     => 'La fiscalización es obligatoria.',
            'fiscalizacion_id.exists'                       => 'La fiscalización seleccionada no existe.',
            'fiscalizacion_incumplimiento_id.required'      => 'El incumplimiento es obligatorio.',
            'fiscalizacion_incumplimiento_id.exists'        => 'El incumplimiento seleccionado no existe.',
            'user_id.exists'                                => 'El usuario seleccionado no existe.',
            'descripcion.required'                          => 'La descripción es obligatoria.',
            'descripcion.string'                            => 'La descripción debe ser texto.',
            'fecha_registro.date'                           => 'La fecha de registro debe ser una fecha válida.',
        ];
    }
}
