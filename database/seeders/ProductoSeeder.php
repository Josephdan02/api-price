<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carga el catálogo de 11 productos/combustibles PRICE con IDs fijos.
 * IDs 1-11 son usados de forma hardcodeada en la app Android
 * (PrecioRepository.initializeCatalog). NO cambiar los IDs.
 */
class ProductoSeeder extends Seeder
{
    public function run(): void
    {
        $productos = [
            ['id' => 1,  'nombre' => 'Diesel B5 / B5 S-50',       'categoria' => 'Líquidos', 'unidad' => 'Galón',  'activo' => true],
            ['id' => 2,  'nombre' => 'G-84 / Gasohol 84 Plus',    'categoria' => 'Líquidos', 'unidad' => 'Galón',  'activo' => true],
            ['id' => 3,  'nombre' => 'Regular / Gasohol Regular', 'categoria' => 'Líquidos', 'unidad' => 'Galón',  'activo' => true],
            ['id' => 4,  'nombre' => 'Premium / Gasohol Premium', 'categoria' => 'Líquidos', 'unidad' => 'Galón',  'activo' => true],
            ['id' => 5,  'nombre' => 'GLP Automotor',             'categoria' => 'GLP',      'unidad' => 'Litro',  'activo' => true],
            ['id' => 6,  'nombre' => 'Otro / Marca',              'categoria' => 'Otros',    'unidad' => 'N/A',    'activo' => true],
            ['id' => 7,  'nombre' => 'GLP cilindro 3 kg',         'categoria' => 'Envasado', 'unidad' => '3 kg',   'activo' => true],
            ['id' => 8,  'nombre' => 'GLP cilindro 5 kg',         'categoria' => 'Envasado', 'unidad' => '5 kg',   'activo' => true],
            ['id' => 9,  'nombre' => 'GLP cilindro 10 kg',        'categoria' => 'Envasado', 'unidad' => '10 kg',  'activo' => true],
            ['id' => 10, 'nombre' => 'GLP cilindro 15 kg',        'categoria' => 'Envasado', 'unidad' => '15 kg',  'activo' => true],
            ['id' => 11, 'nombre' => 'GLP cilindro 45 kg',        'categoria' => 'Envasado', 'unidad' => '45 kg',  'activo' => true],
        ];

        foreach ($productos as $producto) {
            DB::table('productos')->updateOrInsert(
                ['id' => $producto['id']],
                array_merge($producto, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
