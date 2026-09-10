<?php

namespace Database\Factories;

use App\Models\IncumplimientoCatalogo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncumplimientoCatalogo>
 */
class IncumplimientoCatalogoFactory extends Factory
{
    protected $model = IncumplimientoCatalogo::class;

    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->randomElement(['I-01', 'I-02', 'I-03', 'I-04', 'I-05', 'I-06']),
            'descripcion' => fake()->sentence(),
            'base_legal' => 'Pendiente de confirmación institucional (Osinergmin)',
            'activo' => true,
        ];
    }
}
