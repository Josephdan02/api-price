<?php

namespace Database\Factories;

use App\Models\Firma;
use App\Models\Fiscalizacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Firma>
 */
class FirmaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fiscalizacion_id' => Fiscalizacion::factory(),
            'tipo_firma'       => fake()->randomElement(['FISCALIZADOR', 'AGENTE']),
            'nombre_completo'  => fake()->optional(0.8)->name(),
            'dni'              => fake()->optional(0.7)->numerify('########'),
            'relacion_agente'  => fake()->optional(0.3)->randomElement(['Propietario', 'Administrador', 'Encargado', 'Representante']),
            'imagen_firma'     => fake()->optional(0.4)->text(),
            'fecha_firma'      => fake()->optional(0.7)->date(),
        ];
    }

    public function fiscalizador(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_firma' => 'FISCALIZADOR',
        ]);
    }

    public function agente(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_firma'      => 'AGENTE',
            'relacion_agente' => 'Propietario',
        ]);
    }

    public function sinImagen(): static
    {
        return $this->state(fn (array $attributes) => [
            'imagen_firma' => null,
        ]);
    }
}
