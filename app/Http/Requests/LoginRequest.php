<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida las credenciales de inicio de sesión.
 * El sistema PRICE usa DNI como identificador de usuario (no email).
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ruta pública — cualquiera puede intentar login
    }

    public function rules(): array
    {
        return [
            'dni'      => ['required', 'string', 'min:8', 'max:20'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'dni.required'      => 'El DNI es obligatorio.',
            'dni.min'           => 'El DNI debe tener al menos 8 caracteres.',
            'dni.max'           => 'El DNI no puede superar los 20 caracteres.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min'      => 'La contraseña debe tener al menos 6 caracteres.',
        ];
    }
}
