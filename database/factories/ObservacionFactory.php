<?php

namespace Database\Factories;

use App\Models\Fiscalizacion;
use App\Models\Observacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Observacion>
 */
class ObservacionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fiscalizacion_id' => Fiscalizacion::factory(),
            'otras_ocurrencias' => fake()->optional(0.3)->text(),
            'documentacion_recabada' => fake()->optional(0.4)->text(),
            'manifestaciones_agente' => fake()->optional(0.3)->text(),
            'negativa_identificacion' => fake()->boolean(10), // 10% true
            'negativa_suscripcion' => fake()->boolean(10),
            'negativa_recepcion' => fake()->boolean(10),
            'observaciones_generales' => fake()->optional(0.3)->text(),
        ];
    }

    public function conNegativas(): static
    {
        return $this->state(fn (array $attributes) => [
            'negativa_identificacion' => true,
            'negativa_suscripcion' => true,
            'negativa_recepcion' => true,
        ]);
    }
}
