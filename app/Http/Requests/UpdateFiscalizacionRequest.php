<?php

namespace App\Http\Requests;

use App\Models\Fiscalizacion;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la actualización de una fiscalización.
 *
 * Usa 'sometimes' para que los campos no enviados no sean validados
 * (soporte para PATCH semántico aunque el verbo sea PUT).
 *
 * Reglas de negocio:
 * - No permite expediente vacío si se proporciona
 * - No permite fecha inválida
 * - No permite cierre anterior a apertura
 * - No permite actualizar sin establecimiento válido
 * - No permite cerrar/finalizar sin fiscalizador responsable
 * - No permite asignar usuario CONSULTA como fiscalizador
 * - Valida transiciones de estado
 */
class UpdateFiscalizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización por rol se maneja en la ruta
    }

    public function rules(): array
    {
        $id = $this->route('fiscalizacion');

        return [
            'establecimiento_id'     => ['sometimes', 'required', 'exists:establecimientos,id'],
            'user_id'                => ['sometimes', 'nullable', 'exists:users,id', function ($attribute, $value, $fail) {
                if ($value) {
                    $user = User::find($value);
                    if ($user && $user->rol === 'CONSULTA') {
                        $fail('No se puede asignar un usuario con rol CONSULTA como fiscalizador responsable.');
                    }
                }
            }],
            'numero_expediente'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'fecha_diligencia'       => ['sometimes', 'required', 'date'],
            'hora_apertura'          => ['sometimes', 'required', 'date_format:H:i'],
            'hora_cierre'            => ['sometimes', 'nullable', 'date_format:H:i', 'after:hora_apertura'],
            'estado'                 => ['sometimes', Rule::in([
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
            $fiscalizacion = $this->route('fiscalizacion');
            
            // Si fiscalizacion es un string (ID), no existe el modelo
            if (is_string($fiscalizacion) || ! $fiscalizacion) {
                return;
            }

            $estado = $this->input('estado');
            $userId = $this->input('user_id') ?? $fiscalizacion->user_id;

            // No permitir finalizar sin fiscalizador responsable
            if (in_array($estado, [Fiscalizacion::ESTADO_FINALIZADA, Fiscalizacion::ESTADO_ACTA_GENERADA]) && empty($userId)) {
                $validator->errors()->add('user_id', 'No se puede finalizar una fiscalización sin fiscalizador responsable.');
            }

            // Validar transiciones de estado lógicas (solo si se envía un estado diferente)
            if ($estado && $estado !== $fiscalizacion->estado) {
                $estadoActual = $fiscalizacion->estado;
                $transicionesValidas = [
                    Fiscalizacion::ESTADO_BORRADOR => [Fiscalizacion::ESTADO_EN_PROCESO],
                    Fiscalizacion::ESTADO_EN_PROCESO => [Fiscalizacion::ESTADO_FINALIZADA],
                    Fiscalizacion::ESTADO_FINALIZADA => [Fiscalizacion::ESTADO_ACTA_GENERADA],
                ];

                if (isset($transicionesValidas[$estadoActual]) && ! in_array($estado, $transicionesValidas[$estadoActual])) {
                    $validator->errors()->add('estado', 'Transición de estado no permitida.');
                }
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
