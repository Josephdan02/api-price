<?php

namespace Database\Factories;

use App\Models\Establecimiento;
use App\Models\Fiscalizacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fiscalizacion>
 */
class FiscalizacionFactory extends Factory
{
    public function definition(): array
    {
        $establecimiento = Establecimiento::factory()->create();

        return [
            'establecimiento_id'     => $establecimiento->id,
            'user_id'                => User::factory(),
            'numero_expediente'      => strtoupper(fake()->bothify('EXP-####-####')),
            'agente_fiscalizado'     => $establecimiento->razon_social,
            'codigo_osinergmin'      => $establecimiento->codigo_osinergmin,
            'registro_hidrocarburos' => $establecimiento->registro_hidrocarburos,
            'fecha_diligencia'       => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'hora_apertura'          => fake()->time('H:i'),
            'hora_cierre'            => fake()->optional(0.7)->time('H:i'),
            'direccion'              => $establecimiento->direccion,
            'distrito'               => $establecimiento->distrito,
            'provincia'              => $establecimiento->provincia,
            'departamento'           => $establecimiento->departamento,
            'ruc_dni'                => $establecimiento->ruc_dni,
            'telefono_fax'           => $establecimiento->telefono,
            'estado'                 => Fiscalizacion::ESTADO_BORRADOR,
        ];
    }

    public function enProceso(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Fiscalizacion::ESTADO_EN_PROCESO,
        ]);
    }

    public function finalizada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Fiscalizacion::ESTADO_FINALIZADA,
        ]);
    }

    public function actaGenerada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Fiscalizacion::ESTADO_ACTA_GENERADA,
        ]);
    }
}
