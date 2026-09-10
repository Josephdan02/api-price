<?php

namespace Database\Factories;

use App\Models\Fiscalizacion;
use App\Models\Precio;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Precio>
 */
class PrecioFactory extends Factory
{
    public function definition(): array
    {
        $base = fake()->randomFloat(4, 5.00, 25.00);

        return [
            'fiscalizacion_id' => Fiscalizacion::factory(),
            'producto_id'      => Producto::factory(),
            'precio_price'     => $base,
            'precio_publicado' => $base + fake()->randomFloat(4, 0, 0.5),
            'precio_surtidor'  => $base + fake()->randomFloat(4, 0, 0.3),
            'precio_descuento' => null,
            'tiene_descuento'  => false,
            'observacion'      => null,
        ];
    }

    public function conDescuento(): static
    {
        return $this->state(function (array $attributes) {
            $descuento = $attributes['precio_price'] - fake()->randomFloat(4, 0.10, 1.00);
            return [
                'precio_descuento' => max(0, $descuento),
                'tiene_descuento'  => true,
            ];
        });
    }
}
