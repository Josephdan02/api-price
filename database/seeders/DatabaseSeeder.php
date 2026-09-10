<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Siembra datos estructurales del sistema PRICE.
     *
     * Orden obligatorio por dependencias:
     * 1. Datos de catálogo independientes (productos, incumplimientos)
     *
     * NO incluye usuarios de prueba aquí — usar UserFactory en tests.
     * NO incluye establecimientos/fiscalizaciones — son datos operativos.
     */
    public function run(): void
    {
        $this->call([
            ProductoSeeder::class,
            IncumplimientoCatalogoSeeder::class,
            UserSeeder::class,
        ]);
    }
}
