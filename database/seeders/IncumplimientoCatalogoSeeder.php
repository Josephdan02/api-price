<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carga el catálogo de 6 tipos de incumplimiento del sistema PRICE.
 * Códigos I-01 a I-06 hardcodeados en la app Android
 * (IncumplimientoRepository.initializeCatalog).
 *
 * Nota: los textos de base_legal son placeholders en Android.
 * Se cargan con las referencias normativas estructurales pendientes
 * de confirmación institucional (Osinergmin).
 */
class IncumplimientoCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $incumplimientos = [
            [
                'id'          => 1,
                'codigo'      => 'I-01',
                'descripcion' => 'Precios no registrados/actualizados en el PRICE o precios con/sin descuento.',
                'base_legal'  => 'Pendiente de confirmación institucional (Osinergmin)',
                'activo'      => true,
            ],
            [
                'id'          => 2,
                'codigo'      => 'I-02',
                'descripcion' => 'Incumplimiento de la obligación de exhibir la lista de precios vigente.',
                'base_legal'  => 'Pendiente de confirmación institucional (Osinergmin)',
                'activo'      => true,
            ],
            [
                'id'          => 3,
                'codigo'      => 'I-03',
                'descripcion' => 'Ubicación y/o teléfono no registrado/actualizado.',
                'base_legal'  => 'Pendiente de confirmación institucional (Osinergmin)',
                'activo'      => true,
            ],
            [
                'id'          => 4,
                'codigo'      => 'I-04',
                'descripcion' => 'Incumplimiento de la obligación de exhibir visiblemente el horario de atención y/o teléfono.',
                'base_legal'  => 'Pendiente de confirmación institucional (Osinergmin)',
                'activo'      => true,
            ],
            [
                'id'          => 5,
                'codigo'      => 'I-05',
                'descripcion' => 'Incumplimiento de la obligación de utilizar el galón como unidad de medida.',
                'base_legal'  => 'Pendiente de confirmación institucional (Osinergmin)',
                'activo'      => true,
            ],
            [
                'id'          => 6,
                'codigo'      => 'I-06',
                'descripcion' => 'Incumplimiento de la obligación de contar con un rótulo visible que indique el precio por galón o unidad equivalente de energía.',
                'base_legal'  => 'Pendiente de confirmación institucional (Osinergmin)',
                'activo'      => true,
            ],
        ];

        foreach ($incumplimientos as $item) {
            DB::table('incumplimientos_catalogo')->updateOrInsert(
                ['id' => $item['id']],
                array_merge($item, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
