<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la actualización de un hecho verificado.
 *
 * Usa 'sometimes' para que los campos no enviados no sean validados
 * (soporte para PATCH semántico aunque el verbo sea PUT).
 *
 * No hay restricción UNIQUE en el esquema, por lo que no se valida duplicación.
 */
class UpdateHechoVerificadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización por rol se maneja en la ruta
    }

    public function rules(): array
    {
        return [
            'fiscalizacion_id'                  => ['sometimes', 'required', 'exists:fiscalizaciones,id'],
            'fiscalizacion_incumplimiento_id'   => ['sometimes', 'required', 'exists:fiscalizacion_incumplimientos,id'],
            'user_id'                           => ['sometimes', 'nullable', 'exists:users,id'],
            'descripcion'                       => ['sometimes', 'required', 'string'],
            'fecha_registro'                   => ['sometimes', 'nullable', 'date'],
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
