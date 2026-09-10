<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Modelo de usuario del sistema PRICE.
 * Equivalencia Android: UsuarioEntity (tabla usuarios)
 * La tabla users de Laravel actúa como tabla de usuarios PRICE.
 *
 * Campos PRICE añadidos: dni, rol, activo
 * Roles: ADMIN | FISCALIZADOR | CONSULTA
 *
 * @property int    $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $dni
 * @property string $rol
 * @property bool   $activo
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'dni',
        'rol',
        'activo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'activo'            => 'boolean',
        ];
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    /** Una persona puede ser responsable de muchas fiscalizaciones */
    public function fiscalizaciones(): HasMany
    {
        return $this->hasMany(Fiscalizacion::class, 'user_id');
    }

    /** Hechos verificados registrados por este usuario */
    public function hechosVerificados(): HasMany
    {
        return $this->hasMany(HechoVerificado::class, 'user_id');
    }
}
