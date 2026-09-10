<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la creación de un establecimiento.
 *
 * Campo obligatorio: razon_social (único NOT NULL en la migración).
 * Protección contra duplicados: si se proporciona codigo_osinergmin,
 * debe ser único (ignorando registros donde sea NULL).
 */
class StoreEstablecimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización por rol se maneja en la ruta
    }

    public function rules(): array
    {
        return [
            'razon_social'           => ['required', 'string', 'max:200'],
            'codigo_osinergmin'      => ['nullable', 'string', 'max:20', 'unique:establecimientos,codigo_osinergmin'],
            'registro_hidrocarburos' => ['nullable', 'string', 'max:50'],
            'nombre_comercial'       => ['nullable', 'string', 'max:200'],
            'ruc_dni'                => ['nullable', 'string', 'max:20'],
            'telefono'               => ['nullable', 'string', 'max:30'],
            'fax'                    => ['nullable', 'string', 'max:30'],
            'direccion'              => ['nullable', 'string', 'max:300'],
            'distrito'               => ['nullable', 'string', 'max:100'],
            'provincia'              => ['nullable', 'string', 'max:100'],
            'departamento'           => ['nullable', 'string', 'max:100'],
            'latitud'                => ['nullable', 'numeric', 'between:-90,90'],
            'longitud'               => ['nullable', 'numeric', 'between:-180,180'],
            'activo'                 => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'razon_social.required'          => 'La razón social es obligatoria.',
            'razon_social.max'               => 'La razón social no puede superar los 200 caracteres.',
            'codigo_osinergmin.unique'        => 'Ya existe un establecimiento con ese código Osinergmin.',
            'codigo_osinergmin.max'           => 'El código Osinergmin no puede superar los 20 caracteres.',
            'registro_hidrocarburos.max'      => 'El registro de hidrocarburos no puede superar los 50 caracteres.',
            'ruc_dni.max'                     => 'El RUC/DNI no puede superar los 20 caracteres.',
            'telefono.max'                    => 'El teléfono no puede superar los 30 caracteres.',
            'latitud.between'                 => 'La latitud debe estar entre -90 y 90.',
            'longitud.between'                => 'La longitud debe estar entre -180 y 180.',
        ];
    }
}
