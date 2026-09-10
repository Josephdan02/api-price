<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equivalencia Android: FiscalizacionIncumplimientoEntity → tabla fiscalizacion_incumplimientos
 * Tabla pivote Many-to-Many entre fiscalizaciones e incumplimientos_catalogo.
 * Campos Android (camelCase → snake_case):
 *   id, fiscalizacionId→fiscalizacion_id,
 *   incumplimientoCatalogoId→incumplimiento_catalogo_id,
 *   seleccionado, observacion
 *
 * Android FK: fiscalizacionId ON DELETE CASCADE
 *             incumplimientoCatalogoId ON DELETE NO_ACTION
 *
 * Nota: Android borra TODOS los registros de una fiscalización y los
 * reinserta al editar la selección de incumplimientos (ver IncumplimientoRepository).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscalizacion_incumplimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscalizacion_id')
                  ->constrained('fiscalizaciones')
                  ->cascadeOnDelete();
            $table->foreignId('incumplimiento_catalogo_id')
                  ->constrained('incumplimientos_catalogo')
                  ->restrictOnDelete();
            $table->boolean('seleccionado')->default(true);
            $table->text('observacion')->nullable();
            $table->timestamps();

            // Un incumplimiento no puede repetirse en la misma fiscalización
            $table->unique(['fiscalizacion_id', 'incumplimiento_catalogo_id'], 'fi_unique_fisc_incump');
            $table->index('fiscalizacion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscalizacion_incumplimientos');
    }
};
