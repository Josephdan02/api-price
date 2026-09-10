<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equivalencia Android: FirmaEntity → tabla firmas
 * Campos Android (camelCase → snake_case):
 *   id, fiscalizacionId→fiscalizacion_id,
 *   tipoFirma→tipo_firma ("FISCALIZADOR", "AGENTE"),
 *   nombreCompleto→nombre_completo, dni,
 *   relacionAgente→relacion_agente,
 *   imagenFirma→imagen_firma (base64 o path → LONGTEXT),
 *   fechaFirma→fecha_firma
 *
 * Android FK: fiscalizacionId ON DELETE CASCADE
 * Una fiscalización puede tener múltiples firmas (fiscalizador + agente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firmas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscalizacion_id')
                  ->constrained('fiscalizaciones')
                  ->cascadeOnDelete();
            $table->enum('tipo_firma', ['FISCALIZADOR', 'AGENTE']);
            $table->string('nombre_completo', 200)->nullable();
            $table->string('dni', 20)->nullable();
            $table->string('relacion_agente', 100)->nullable();
            $table->longText('imagen_firma')->nullable();
            $table->date('fecha_firma')->nullable();
            $table->timestamps();

            $table->index('fiscalizacion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firmas');
    }
};
