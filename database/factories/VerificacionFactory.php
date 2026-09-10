<?php

namespace Database\Factories;

use App\Models\Fiscalizacion;
use App\Models\Verificacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Verificacion>
 */
class VerificacionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fiscalizacion_id' => Fiscalizacion::factory(),
            'telefono_publicado' => fake()->phoneNumber(),
            'telefono_actualizado_price' => fake()->phoneNumber(),
            'horario_publicado' => fake()->randomElement(['Lun-Sab 08:00-20:00', 'Lun-Dom 07:00-21:00', 'Mar-Dom 08:00-18:00']),
            'observaciones' => fake()->optional(0.5)->sentence(),
        ];
    }
}
