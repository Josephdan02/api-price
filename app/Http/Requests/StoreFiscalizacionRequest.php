<?php

namespace App\Http\Requests;

use App\Models\Fiscalizacion;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la creación de una fiscalización.
 *
 * Campos obligatorios: establecimiento_id, fecha_diligencia, hora_apertura
 * Reglas de negocio:
 * - No permite expediente vacío si se proporciona
 * - No permite fecha inválida
 * - No permite cierre anterior a apertura
 * - No permite crear sin establecimiento válido
 * - No permite cerrar/finalizar sin fiscalizador responsable
 * - No permite asignar usuario CONSULTA como fiscalizador
 */
class StoreFiscalizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización por rol se maneja en la ruta
    }

    public function rules(): array
    {
        return [
            'establecimiento_id'     => ['required', 'exists:establecimientos,id'],
            'user_id'                => ['nullable', 'exists:users,id', function ($attribute, $value, $fail) {
                if ($value) {
                    $user = User::find($value);
                    if ($user && $user->rol === 'CONSULTA') {
                        $fail('No se puede asignar un usuario con rol CONSULTA como fiscalizador responsable.');
                    }
                }
            }],
            'numero_expediente'      => ['nullable', 'string', 'max:100', 'unique:fiscalizaciones,numero_expediente'],
            'fecha_diligencia'       => ['required', 'date'],
            'hora_apertura'          => ['required', 'date_format:H:i'],
            'hora_cierre'            => ['nullable', 'date_format:H:i', 'after:hora_apertura'],
            'estado'                 => ['nullable', Rule::in([
                Fiscalizacion::ESTADO_BORRADOR,
                Fiscalizacion::ESTADO_EN_PROCESO,
                Fiscalizacion::ESTADO_FINALIZADA,
                Fiscalizacion::ESTADO_ACTA_GENERADA,
            ])],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $estado = $this->input('estado');
            $userId = $this->input('user_id');

            // No permitir finalizar sin fiscalizador responsable
            if (in_array($estado, [Fiscalizacion::ESTADO_FINALIZADA, Fiscalizacion::ESTADO_ACTA_GENERADA]) && empty($userId)) {
                $validator->errors()->add('user_id', 'No se puede finalizar una fiscalización sin fiscalizador responsable.');
            }

            // No permitir transición de estado inválida desde BORRADOR
            if ($estado === Fiscalizacion::ESTADO_FINALIZADA || $estado === Fiscalizacion::ESTADO_ACTA_GENERADA) {
                $validator->errors()->add('estado', 'No se puede crear una fiscalización directamente en estado FINALIZADA o ACTA_GENERADA.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'establecimiento_id.required'     => 'El establecimiento es obligatorio.',
            'establecimiento_id.exists'       => 'El establecimiento seleccionado no existe.',
            'user_id.exists'                  => 'El fiscalizador seleccionado no existe.',
            'numero_expediente.max'           => 'El número de expediente no puede superar los 100 caracteres.',
            'numero_expediente.unique'        => 'Ya existe una fiscalización con ese número de expediente.',
            'fecha_diligencia.required'       => 'La fecha de diligencia es obligatoria.',
            'fecha_diligencia.date'           => 'La fecha de diligencia debe ser una fecha válida.',
            'hora_apertura.required'          => 'La hora de apertura es obligatoria.',
            'hora_apertura.date_format'       => 'La hora de apertura debe tener formato HH:MM.',
            'hora_cierre.date_format'         => 'La hora de cierre debe tener formato HH:MM.',
            'hora_cierre.after'               => 'La hora de cierre debe ser posterior a la hora de apertura.',
            'estado.in'                       => 'El estado debe ser uno de: BORRADOR, EN_PROCESO, FINALIZADA, ACTA_GENERADA.',
        ];
    }
}
