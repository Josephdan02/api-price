<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la creación de una verificación.
 *
 * Campos obligatorios: fiscalizacion_id
 * Reglas de negocio:
 * - Una fiscalización solo puede tener una verificación (unique constraint)
 * - Validación de tipos y longitudes según esquema
 */
class StoreVerificacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización por rol se maneja en la ruta
    }

    public function rules(): array
    {
        return [
            'fiscalizacion_id'         => ['required', 'exists:fiscalizaciones,id', 'unique:verificaciones,fiscalizacion_id'],
            'telefono_publicado'        => ['nullable', 'string', 'max:50'],
            'telefono_actualizado_price' => ['nullable', 'string', 'max:50'],
            'horario_publicado'         => ['nullable', 'string', 'max:200'],
            'observaciones'             => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $fiscalizacionId = $this->input('fiscalizacion_id');

            // Validar que la fiscalización no tenga ya una verificación
            if ($fiscalizacionId) {
                $exists = \App\Models\Verificacion::where('fiscalizacion_id', $fiscalizacionId)->exists();

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
