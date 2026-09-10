<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la actualización de una firma.
 *
 * Usa 'sometimes' para que los campos no enviados no sean validados
 * (soporte para PATCH semántico aunque el verbo sea PUT).
 *
 * NOTA: La tabla firmas NO tiene restricción UNIQUE (relación 1:N),
 * por lo que NO se valida duplicación de fiscalizacion_id + tipo_firma.
 */
class UpdateFirmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización por rol se maneja en la ruta
    }

    public function rules(): array
    {
        return [
            'fiscalizacion_id' => ['sometimes', 'required', 'exists:fiscalizaciones,id'],
            'tipo_firma'       => ['sometimes', 'required', Rule::in(['FISCALIZADOR', 'AGENTE'])],
            'nombre_completo'  => ['sometimes', 'nullable', 'string', 'max:200'],
            'dni'              => ['sometimes', 'nullable', 'string', 'max:20'],
            'relacion_agente'  => ['sometimes', 'nullable', 'string', 'max:100'],
            'imagen_firma'     => ['sometimes', 'nullable', 'string'],
            'fecha_firma'      => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'fiscalizacion_id.required' => 'La fiscalización es obligatoria.',
            'fiscalizacion_id.exists'   => 'La fiscalización seleccionada no existe.',
            'tipo_firma.required'       => 'El tipo de firma es obligatorio.',
            'tipo_firma.in'             => 'El tipo de firma debe ser FISCALIZADOR o AGENTE.',
            'nombre_completo.string'    => 'El nombre completo debe ser texto.',
            'nombre_completo.max'       => 'El nombre completo no puede superar 200 caracteres.',
            'dni.string'                => 'El DNI debe ser texto.',
            'dni.max'                   => 'El DNI no puede superar 20 caracteres.',
            'relacion_agente.string'    => 'La relación con el agente debe ser texto.',
            'relacion_agente.max'       => 'La relación con el agente no puede superar 100 caracteres.',
            'imagen_firma.string'       => 'La imagen de la firma debe ser texto.',
            'fecha_firma.date'          => 'La fecha de firma debe ser una fecha válida.',
        ];
    }
}
