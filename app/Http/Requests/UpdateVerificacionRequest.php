<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la actualización de una verificación.
 *
 * Usa 'sometimes' para que los campos no enviados no sean validados
 * (soporte para PATCH semántico aunque el verbo sea PUT).
 *
 * Reglas de negocio:
 * - Una fiscalización solo puede tener una verificación (ignorando el registro actual)
 * - Validación de tipos y longitudes según esquema
 */
class UpdateVerificacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización por rol se maneja en la ruta
    }

    public function rules(): array
    {
        $id = $this->route('verificacion');

        return [
            'fiscalizacion_id'         => ['sometimes', 'required', 'exists:fiscalizaciones,id', Rule::unique('verificaciones', 'fiscalizacion_id')->ignore($id)],
            'telefono_publicado'        => ['sometimes', 'nullable', 'string', 'max:50'],
            'telefono_actualizado_price' => ['sometimes', 'nullable', 'string', 'max:50'],
            'horario_publicado'         => ['sometimes', 'nullable', 'string', 'max:200'],
            'observaciones'             => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $verificacion = $this->route('verificacion');
            
            // Si la verificación no existe, no continuar con validaciones adicionales
            if (! $verificacion || is_string($verificacion)) {
                return;
            }

            $fiscalizacionId = $this->input('fiscalizacion_id') ?? $verificacion->fiscalizacion_id;

            // Validar que la fiscalización no tenga ya una verificación (ignorando el registro actual)
            if ($fiscalizacionId) {
                $exists = \App\Models\Verificacion::where('fiscalizacion_id', $fiscalizacionId)
                    ->where('id', '!=', $verificacion->id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('fiscalizacion_id', 'Esta fiscalización ya tiene una verificación registrada.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'fiscalizacion_id.required'           => 'La fiscalización es obligatoria.',
            'fiscalizacion_id.exists'             => 'La fiscalización seleccionada no existe.',
            'fiscalizacion_id.unique'             => 'Esta fiscalización ya tiene una verificación registrada.',
            'telefono_publicado.max'              => 'El teléfono publicado no puede superar los 50 caracteres.',
            'telefono_actualizado_price.max'      => 'El teléfono actualizado en PRICE no puede superar los 50 caracteres.',
            'horario_publicado.max'               => 'El horario publicado no puede superar los 200 caracteres.',
        ];
    }
}
