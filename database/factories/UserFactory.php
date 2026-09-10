<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'               => fake()->name(),
            'email'              => fake()->unique()->safeEmail(),
            'email_verified_at'  => now(),
            'password'           => static::$password ??= Hash::make('password'),
            'remember_token'     => Str::random(10),
            'dni'                => fake()->numerify('########'),
            'rol'                => 'FISCALIZADOR',
            'activo'             => true,
        ];
    }

    /** Usuario con rol ADMIN */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['rol' => 'ADMIN']);
    }

    /** Usuario con rol CONSULTA */
    public function consulta(): static
    {
        return $this->state(fn (array $attributes) => ['rol' => 'CONSULTA']);
    }

    /** Usuario inactivo */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
