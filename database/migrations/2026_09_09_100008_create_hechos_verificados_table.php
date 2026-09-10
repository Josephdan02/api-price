<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equivalencia Android: HechoVerificadoEntity → tabla hechos_verificados
 * Campos Android (camelCase → snake_case):
 *   id, fiscalizacionId→fiscalizacion_id,
 *   fiscalizacionIncumplimientoId→fiscalizacion_incumplimiento_id,
 *   descripcion, fechaRegistro→fecha_registro,
 *   usuarioId→user_id (sin FK declarada en Android, aquí sí se agrega),
 *   fechaActualizacion (manejado por timestamps)
 *
 * Android FK: fiscalizacionId ON DELETE CASCADE
 *             fiscalizacionIncumplimientoId ON DELETE CASCADE
 *
 * Nota: al borrar un FiscalizacionIncumplimiento se eliminan en cascada
 * sus hechos_verificados (comportamiento Android: deleteByFiscalizacion
 * borra hechos antes de borrar fiscalizacion_incumplimientos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hechos_verificados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscalizacion_id')
                  ->constrained('fiscalizaciones')
                  ->cascadeOnDelete();
            $table->foreignId('fiscalizacion_incumplimiento_id')
                  ->constrained('fiscalizacion_incumplimientos')
                  ->cascadeOnDelete();
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->text('descripcion');
            $table->date('fecha_registro')->nullable();
            $table->timestamps();

            $table->index('fiscalizacion_id');
            $table->index('fiscalizacion_incumplimiento_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hechos_verificados');
    }
};
