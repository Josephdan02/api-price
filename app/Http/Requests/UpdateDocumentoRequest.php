<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la actualización de un documento.
 *
 * Usa 'sometimes' para que los campos no enviados no sean validados
 * (soporte para PATCH semántico aunque el verbo sea PUT).
 *
 * Reglas de negocio:
 * - No permite cambiar fiscalizacion_id a una que ya tenga documento
 *   (ignorando el registro actual, relación 1-a-1).
 * - Validación de tipos y longitudes según esquema.
 */
class UpdateDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización por rol se maneja en la ruta
    }

    public function rules(): array
    {
        $id = $this->route('documento');

        return [
            'fiscalizacion_id' => ['sometimes', 'required', 'exists:fiscalizaciones,id', Rule::unique('documentos', 'fiscalizacion_id')->ignore($id)],
            'nombre_archivo'   => ['sometimes', 'nullable', 'string', 'max:300'],
            'ruta_archivo'     => ['sometimes', 'nullable', 'string', 'max:500'],
            'fecha_generacion' => ['sometimes', 'nullable', 'date'],
            'numero_paginas'   => ['sometimes', 'nullable', 'integer', 'min:0'],
            'estado'           => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $id = $this->route('documento');

            // Si es un string (ID), necesitamos cargar el modelo
            if (is_string($id)) {
                $documento = \App\Models\Documento::find($id);
            } else {
                $documento = $id;
            }

            // Si el documento no existe, no continuar con validaciones adicionales
            if (! $documento) {
                return;
            }

            $fiscalizacionId = $this->input('fiscalizacion_id') ?? $documento->fiscalizacion_id;

            // Validar que no se cambie a una fiscalización que ya tenga documento (ignorando el registro actual)
            if ($fiscalizacionId) {
                $exists = \App\Models\Documento::where('fiscalizacion_id', $fiscalizacionId)
                    ->where('id', '!=', $documento->id)
                    ->exists();

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
