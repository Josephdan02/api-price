<?php

namespace Database\Factories;

use App\Models\Fiscalizacion;
use App\Models\FiscalizacionIncumplimiento;
use App\Models\HechoVerificado;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HechoVerificado>
 */
class HechoVerificadoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fiscalizacion_id' => Fiscalizacion::factory(),
            'fiscalizacion_incumplimiento_id' => function () {
                return FiscalizacionIncumplimiento::inRandomOrder()->first()->id ?? FiscalizacionIncumplimiento::factory()->create()->id;
            },
            'user_id' => User::factory(),
            'descripcion' => fake()->sentence(),
            'fecha_registro' => fake()->optional(0.7)->date(),
        ];
    }

    public function sinUsuario(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }

    public function sinFecha(): static
    {
        return $this->state(fn (array $attributes) => [
            'fecha_registro' => null,
        ]);
    }
}
