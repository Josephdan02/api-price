<?php

namespace Database\Factories;

use App\Models\Documento;
use App\Models\Fiscalizacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Documento>
 *
 * NOTA: fiscalizacion_id es UNIQUE (1-a-1). Cada llamada crea una
 * fiscalización nueva vía Fiscalizacion::factory() para no colisionar.
 */
class DocumentoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fiscalizacion_id' => Fiscalizacion::factory(),
            'nombre_archivo'   => 'acta-' . fake()->unique()->numerify('####') . '.pdf',
            'ruta_archivo'     => 'documentos/acta-' . fake()->numerify('####') . '-' . fake()->randomNumber(4) . '.pdf',
            'fecha_generacion' => fake()->boolean(70) ? fake()->dateTime()->format('Y-m-d H:i:s') : null,
            'numero_paginas'   => fake()->boolean(70) ? fake()->numberBetween(1, 50) : null,
            'estado'           => fake()->boolean(60) ? fake()->randomElement(['GENERADO', 'PENDIENTE', 'FIRMADO']) : null,
        ];
    }

    public function sinArchivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre_archivo'   => null,
            'ruta_archivo'     => null,
            'fecha_generacion' => null,
            'numero_paginas'   => null,
            'estado'           => null,
        ]);
    }
}
