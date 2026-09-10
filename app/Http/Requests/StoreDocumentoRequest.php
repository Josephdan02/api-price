<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la creación de un documento.
 *
 * Campos obligatorios: fiscalizacion_id
 * Campos opcionales: nombre_archivo, ruta_archivo, fecha_generacion, numero_paginas, estado
 *
 * Reglas de negocio:
 * - No permite duplicar documento en la misma fiscalización (relación 1-a-1,
 *   UNIQUE en fiscalizacion_id según la migración).
 * - Validación de tipos y longitudes según esquema.
 */
class StoreDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización por rol se maneja en la ruta
    }

    public function rules(): array
    {
        return [
            'fiscalizacion_id' => ['required', 'exists:fiscalizaciones,id', 'unique:documentos,fiscalizacion_id'],
            'nombre_archivo'   => ['nullable', 'string', 'max:300'],
            'ruta_archivo'     => ['nullable', 'string', 'max:500'],
            'fecha_generacion' => ['nullable', 'date'],
            'numero_paginas'   => ['nullable', 'integer', 'min:0'],
            'estado'           => ['nullable', 'string', 'max:50'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $fiscalizacionId = $this->input('fiscalizacion_id');

            // Validar que la fiscalización no tenga ya un documento (relación 1-a-1)
            if ($fiscalizacionId) {
                $exists = \App\Models\Documento::where('fiscalizacion_id', $fiscalizacionId)->exists();

                if ($exists) {
                    $validator->errors()->add('fiscalizacion_id', 'Esta fiscalización ya tiene un documento registrado.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'fiscalizacion_id.required' => 'La fiscalización es obligatoria.',
            'fiscalizacion_id.exists'   => 'La fiscalización seleccionada no existe.',
            'fiscalizacion_id.unique'   => 'Esta fiscalización ya tiene un documento registrado.',
            'nombre_archivo.string'     => 'El nombre del archivo debe ser texto.',
            'nombre_archivo.max'        => 'El nombre del archivo no puede superar 300 caracteres.',
            'ruta_archivo.string'       => 'La ruta del archivo debe ser texto.',
            'ruta_archivo.max'          => 'La ruta del archivo no puede superar 500 caracteres.',
            'fecha_generacion.date'     => 'La fecha de generación debe ser una fecha válida.',
            'numero_paginas.integer'    => 'El número de páginas debe ser un entero.',
            'numero_paginas.min'        => 'El número de páginas no puede ser negativo.',
            'estado.string'             => 'El estado debe ser texto.',
            'estado.max'                => 'El estado no puede superar 50 caracteres.',
        ];
    }
}
