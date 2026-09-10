<?php

namespace Database\Factories;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 * Usado en tests cuando se necesita un producto arbitrario.
 * Para datos reales usar ProductoSeeder (IDs 1-11 fijos).
 */
class ProductoFactory extends Factory
{
    private static array $productos = [
        ['nombre' => 'Diesel B5 / B5 S-50',         'categoria' => 'Líquidos', 'unidad' => 'Galón'],
        ['nombre' => 'G-84 / Gasohol 84 Plus',       'categoria' => 'Líquidos', 'unidad' => 'Galón'],
        ['nombre' => 'Regular / Gasohol Regular',    'categoria' => 'Líquidos', 'unidad' => 'Galón'],
        ['nombre' => 'Premium / Gasohol Premium',    'categoria' => 'Líquidos', 'unidad' => 'Galón'],
        ['nombre' => 'GLP Automotor',                'categoria' => 'GLP',      'unidad' => 'Litro'],
        ['nombre' => 'Otro / Marca',                 'categoria' => 'Otros',    'unidad' => 'N/A'],
        ['nombre' => 'GLP cilindro 3 kg',            'categoria' => 'Envasado', 'unidad' => '3 kg'],
        ['nombre' => 'GLP cilindro 5 kg',            'categoria' => 'Envasado', 'unidad' => '5 kg'],
        ['nombre' => 'GLP cilindro 10 kg',           'categoria' => 'Envasado', 'unidad' => '10 kg'],
        ['nombre' => 'GLP cilindro 15 kg',           'categoria' => 'Envasado', 'unidad' => '15 kg'],
        ['nombre' => 'GLP cilindro 45 kg',           'categoria' => 'Envasado', 'unidad' => '45 kg'],
    ];

    public function definition(): array
    {
        $p = fake()->randomElement(self::$productos);

        return [
            'nombre'    => $p['nombre'] . ' (test-' . fake()->numerify('###') . ')',
            'categoria' => $p['categoria'],
            'unidad'    => $p['unidad'],
            'activo'    => true,
        ];
    }
}
