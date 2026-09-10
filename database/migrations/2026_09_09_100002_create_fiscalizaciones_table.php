<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equivalencia Android: FiscalizacionEntity → tabla fiscalizaciones
 * Campos Android (camelCase → snake_case):
 *   id, establecimientoId→establecimiento_id, numeroExpediente, agenteFiscalizado,
 *   codigoOsinergmin, registroHidrocarburos, fechaDiligencia, horaApertura,
 *   horaCierre, direccion, distrito, provincia, departamento, rucDni,
 *   telefonoFax, fiscalizadorResponsableId→user_id, estado, fechaCreacion,
 *   fechaActualizacion
 *
 * Nota: agenteFiscalizado, codigoOsinergmin, direccion, etc. son copias
 * desnormalizadas del establecimiento al momento de la fiscalización
 * (comportamiento verificado en FiscalizacionRepository Android).
 *
 * Estado → ENUM: BORRADOR | EN_PROCESO | FINALIZADA | ACTA_GENERADA
 * Android FK: establecimientoId ON DELETE CASCADE
 *             fiscalizadorResponsableId ON DELETE NO_ACTION
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscalizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')
                  ->constrained('establecimientos')
                  ->cascadeOnDelete();
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->restrictOnDelete();
            $table->string('numero_expediente', 100)->nullable()->index();
            // Copia desnormalizada del establecimiento al momento de la diligencia
            $table->string('agente_fiscalizado', 200)->nullable();
            $table->string('codigo_osinergmin', 20)->nullable();
            $table->string('registro_hidrocarburos', 50)->nullable();
            $table->date('fecha_diligencia');
            $table->time('hora_apertura');
            $table->time('hora_cierre')->nullable();
            $table->string('direccion', 300)->nullable();
            $table->string('distrito', 100)->nullable();
            $table->string('provincia', 100)->nullable();
            $table->string('departamento', 100)->nullable();
            $table->string('ruc_dni', 20)->nullable();
            $table->string('telefono_fax', 50)->nullable();
            $table->enum('estado', ['BORRADOR', 'EN_PROCESO', 'FINALIZADA', 'ACTA_GENERADA'])
                  ->default('BORRADOR');
            $table->timestamps();

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscalizaciones');
    }
};
