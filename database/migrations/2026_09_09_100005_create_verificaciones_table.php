<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equivalencia Android: VerificacionEntity → tabla verificaciones
 * Relación 1-a-1 con fiscalización (DAO usa LIMIT 1).
 * Campos Android (camelCase → snake_case):
 *   id, fiscalizacionId→fiscalizacion_id,
 *   telefonoPublicado→telefono_publicado,
 *   telefonoActualizadoPrice→telefono_actualizado_price,
 *   horarioPublicado→horario_publicado,
 *   observaciones
 *
 * Android FK: fiscalizacionId ON DELETE CASCADE
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscalizacion_id')
                  ->unique()   // 1-a-1 con fiscalización
                  ->constrained('fiscalizaciones')
                  ->cascadeOnDelete();
            $table->string('telefono_publicado', 50)->nullable();
            $table->string('telefono_actualizado_price', 50)->nullable();
            $table->string('horario_publicado', 200)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verificaciones');
    }
};
