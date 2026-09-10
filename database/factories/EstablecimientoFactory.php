<?php

namespace Database\Factories;

use App\Models\Establecimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Establecimiento>
 */
class EstablecimientoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo_osinergmin'      => strtoupper(fake()->bothify('##??###')),
            'registro_hidrocarburos' => strtoupper(fake()->bothify('DRH-####')),
            'razon_social'           => fake()->company() . ' S.A.C.',
            'nombre_comercial'       => fake()->optional()->company(),
            'ruc_dni'                => fake()->numerify('##########'),
            'telefono'               => fake()->phoneNumber(),
            'fax'                    => fake()->optional()->phoneNumber(),
            'direccion'              => fake()->streetAddress(),
            'distrito'               => fake()->city(),
            'provincia'              => fake()->city(),
            'departamento'           => fake()->randomElement([
                'Lima', 'Arequipa', 'Cusco', 'La Libertad', 'Piura',
                'Lambayeque', 'Junín', 'Ica', 'Tacna', 'Puno',
            ]),
            'latitud'  => fake()->latitude(-18.0, -2.0),
            'longitud' => fake()->longitude(-81.0, -68.0),
            'activo'   => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }
}
