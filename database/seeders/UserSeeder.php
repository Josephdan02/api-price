<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Siembra usuarios de prueba para desarrollo y testing manual.
 * Idempotente: usa updateOrInsert por DNI.
 *
 * Credenciales de prueba (NO usar en producción):
 *
 *   ADMIN        DNI: 00000001  password: Admin1234!
 *   FISCALIZADOR DNI: 00000002  password: Fisc1234!
 *   CONSULTA     DNI: 00000003  password: Cons1234!
 *
 * IMPORTANTE: las contraseñas se almacenan con Hash::make() (bcrypt).
 * Nunca se guardan en texto plano.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            [
                'name'              => 'Administrador PRICE',
                'email'             => 'admin@price.local',
                'dni'               => '00000001',
                'rol'               => 'ADMIN',
                'activo'            => true,
                'password'          => Hash::make('Admin1234!'),
                'email_verified_at' => now(),
            ],
            [
                'name'              => 'Fiscalizador PRICE',
                'email'             => 'fiscalizador@price.local',
                'dni'               => '00000002',
                'rol'               => 'FISCALIZADOR',
                'activo'            => true,
                'password'          => Hash::make('Fisc1234!'),
                'email_verified_at' => now(),
            ],
            [
                'name'              => 'Consulta PRICE',
                'email'             => 'consulta@price.local',
                'dni'               => '00000003',
                'rol'               => 'CONSULTA',
                'activo'            => true,
                'password'          => Hash::make('Cons1234!'),
                'email_verified_at' => now(),
            ],
            [
                'name'              => 'Usuario Inactivo',
                'email'             => 'inactivo@price.local',
                'dni'               => '00000004',
                'rol'               => 'FISCALIZADOR',
                'activo'            => false,
                'password'          => Hash::make('Inac1234!'),
                'email_verified_at' => now(),
            ],
        ];

        foreach ($usuarios as $usuario) {
            DB::table('users')->updateOrInsert(
                ['dni' => $usuario['dni']],
                array_merge($usuario, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
