<?php

namespace Database\Factories;

use App\Models\Fiscalizacion;
use App\Models\FiscalizacionIncumplimiento;
use App\Models\IncumplimientoCatalogo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalizacionIncumplimiento>
 */
class FiscalizacionIncumplimientoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fiscalizacion_id' => Fiscalizacion::factory(),
            'incumplimiento_catalogo_id' => function () {
                return IncumplimientoCatalogo::inRandomOrder()->first()->id ?? IncumplimientoCatalogo::factory()->create()->id;
            },
            'seleccionado' => true,
            'observacion' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function noSeleccionado(): static
    {
        return $this->state(fn (array $attributes) => [
            'seleccionado' => false,
        ]);
    }
}
