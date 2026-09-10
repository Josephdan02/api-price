<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la actualización de un establecimiento.
 *
 * Usa 'sometimes' para que los campos no enviados no sean validados
 * (soporte para PATCH semántico aunque el verbo sea PUT).
 *
 * El unique de codigo_osinergmin ignora el registro actual para
 * permitir actualizaciones sin falso positivo de duplicado.
 */
class UpdateEstablecimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('establecimiento');

        return [
            'razon_social'           => ['sometimes', 'required', 'string', 'max:200'],
            'codigo_osinergmin'      => [
                'sometimes', 'nullable', 'string', 'max:20',
                Rule::unique('establecimientos', 'codigo_osinergmin')->ignore($id),
            ],
            'registro_hidrocarburos' => ['sometimes', 'nullable', 'string', 'max:50'],
            'nombre_comercial'       => ['sometimes', 'nullable', 'string', 'max:200'],
            'ruc_dni'                => ['sometimes', 'nullable', 'string', 'max:20'],
            'telefono'               => ['sometimes', 'nullable', 'string', 'max:30'],
            'fax'                    => ['sometimes', 'nullable', 'string', 'max:30'],
            'direccion'              => ['sometimes', 'nullable', 'string', 'max:300'],
            'distrito'               => ['sometimes', 'nullable', 'string', 'max:100'],
            'provincia'              => ['sometimes', 'nullable', 'string', 'max:100'],
            'departamento'           => ['sometimes', 'nullable', 'string', 'max:100'],
            'latitud'                => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitud'               => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'activo'                 => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'razon_social.required'      => 'La razón social es obligatoria.',
            'razon_social.max'           => 'La razón social no puede superar los 200 caracteres.',
            'codigo_osinergmin.unique'   => 'Ya existe un establecimiento con ese código Osinergmin.',
            'codigo_osinergmin.max'      => 'El código Osinergmin no puede superar los 20 caracteres.',
            'latitud.between'            => 'La latitud debe estar entre -90 y 90.',
            'longitud.between'           => 'La longitud debe estar entre -180 y 180.',
        ];
    }
}
