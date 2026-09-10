<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la creación de una firma.
 *
 * Campos obligatorios: fiscalizacion_id, tipo_firma
 * Campos opcionales: nombre_completo, dni, relacion_agente, imagen_firma, fecha_firma
 *
 * NOTA: La tabla firmas NO tiene restricción UNIQUE (relación 1:N),
 * por lo que NO se valida duplicación de fiscalizacion_id + tipo_firma.
 */
class StoreFirmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización por rol se maneja en la ruta
    }

    public function rules(): array
    {
        return [
            'fiscalizacion_id' => ['required', 'exists:fiscalizaciones,id'],
            'tipo_firma'       => ['required', Rule::in(['FISCALIZADOR', 'AGENTE'])],
            'nombre_completo'  => ['nullable', 'string', 'max:200'],
            'dni'              => ['nullable', 'string', 'max:20'],
            'relacion_agente'  => ['nullable', 'string', 'max:100'],
            'imagen_firma'     => ['nullable', 'string'],
            'fecha_firma'      => ['nullable', 'date'],
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
