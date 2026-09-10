<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la actualización de un precio.
 *
 * Usa 'sometimes' para que los campos no enviados no sean validados
 * (soporte para PATCH semántico aunque el verbo sea PUT).
 *
 * Reglas de negocio:
 * - No permite precios negativos
 * - No permite duplicar producto en la misma fiscalización (ignorando el registro actual)
 * - Si tiene_descuento es true, debe tener precio_descuento
 */
class UpdatePrecioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización por rol se maneja en la ruta
    }

    public function rules(): array
    {
        $id = $this->route('precio');

        return [
            'fiscalizacion_id' => ['sometimes', 'required', 'exists:fiscalizaciones,id'],
            'producto_id'      => ['sometimes', 'required', 'exists:productos,id'],
            'precio_price'     => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999.9999'],
            'precio_publicado' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999.9999'],
            'precio_surtidor'  => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999.9999'],
            'precio_descuento' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999.9999'],
            'tiene_descuento'  => ['sometimes', 'boolean'],
            'observacion'      => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $precio = $this->route('precio');
            
            // Si el precio no existe, no continuar con validaciones adicionales
            if (! $precio || is_string($precio)) {
                return;
            }

            $fiscalizacionId = $this->input('fiscalizacion_id') ?? $precio->fiscalizacion_id;
            $productoId = $this->input('producto_id') ?? $precio->producto_id;
            $tieneDescuento = $this->input('tiene_descuento');
            $precioDescuento = $this->input('precio_descuento');

            // Validar que no se repita producto en la misma fiscalización (ignorando el registro actual)
            if ($fiscalizacionId && $productoId) {
                $exists = \App\Models\Precio::where('fiscalizacion_id', $fiscalizacionId)
                    ->where('producto_id', $productoId)
                    ->where('id', '!=', $precio->id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('producto_id', 'Este producto ya está registrado en esta fiscalización.');
                }
            }

            // Si tiene_descuento es true, debe tener precio_descuento
            if ($tieneDescuento === true && empty($precioDescuento)) {
                $validator->errors()->add('precio_descuento', 'Debe especificar el precio con descuento cuando tiene_descuento es true.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'fiscalizacion_id.required'     => 'La fiscalización es obligatoria.',
            'fiscalizacion_id.exists'       => 'La fiscalización seleccionada no existe.',
            'producto_id.required'          => 'El producto es obligatorio.',
            'producto_id.exists'            => 'El producto seleccionado no existe.',
            'precio_price.numeric'           => 'El precio PRICE debe ser numérico.',
            'precio_price.min'               => 'El precio PRICE no puede ser negativo.',
            'precio_price.max'               => 'El precio PRICE no puede superar 99999.9999.',
            'precio_publicado.numeric'       => 'El precio publicado debe ser numérico.',
            'precio_publicado.min'           => 'El precio publicado no puede ser negativo.',
            'precio_publicado.max'           => 'El precio publicado no puede superar 99999.9999.',
            'precio_surtidor.numeric'        => 'El precio surtidor debe ser numérico.',
            'precio_surtidor.min'            => 'El precio surtidor no puede ser negativo.',
            'precio_surtidor.max'            => 'El precio surtidor no puede superar 99999.9999.',
            'precio_descuento.numeric'       => 'El precio descuento debe ser numérico.',
            'precio_descuento.min'           => 'El precio descuento no puede ser negativo.',
            'precio_descuento.max'           => 'El precio descuento no puede superar 99999.9999.',
            'tiene_descuento.boolean'        => 'El campo tiene_descuento debe ser booleano.',
        ];
    }
}
