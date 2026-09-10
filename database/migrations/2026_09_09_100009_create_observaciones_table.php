<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equivalencia Android: ObservacionEntity → tabla observaciones
 * Relación 1-a-1 con fiscalización.
 * Campos Android (camelCase → snake_case):
 *   id, fiscalizacionId→fiscalizacion_id,
 *   otrasOcurrencias→otras_ocurrencias,
 *   documentacionRecabada→documentacion_recabada,
 *   manifestacionesAgente→manifestaciones_agente,
 *   negativaIdentificacion→negativa_identificacion,
 *   negativaSuscripcion→negativa_suscripcion,
 *   negativaRecepcion→negativa_recepcion,
 *   observacionesGenerales→observaciones_generales
 *
 * Android FK: fiscalizacionId ON DELETE CASCADE
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscalizacion_id')
                  ->unique()   // 1-a-1 con fiscalización
                  ->constrained('fiscalizaciones')
                  ->cascadeOnDelete();
            $table->text('otras_ocurrencias')->nullable();
            $table->text('documentacion_recabada')->nullable();
            $table->text('manifestaciones_agente')->nullable();
            $table->boolean('negativa_identificacion')->default(false);
            $table->boolean('negativa_suscripcion')->default(false);
            $table->boolean('negativa_recepcion')->default(false);
            $table->text('observaciones_generales')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observaciones');
    }
};
