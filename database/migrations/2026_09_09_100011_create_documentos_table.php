<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equivalencia Android: DocumentoEntity → tabla documentos
 * Campos Android (camelCase → snake_case):
 *   id, fiscalizacionId→fiscalizacion_id,
 *   nombreArchivo→nombre_archivo,
 *   rutaArchivo→ruta_archivo,
 *   fechaGeneracion→fecha_generacion,
 *   numeroPaginas→numero_paginas,
 *   estado
 *
 * Android FK: fiscalizacionId ON DELETE CASCADE
 * hasOne desde Fiscalizacion (una fiscalización → un documento de acta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscalizacion_id')
                  ->unique()   // hasOne: una fiscalización → un documento
                  ->constrained('fiscalizaciones')
                  ->cascadeOnDelete();
            $table->string('nombre_archivo', 300)->nullable();
            $table->string('ruta_archivo', 500)->nullable();
            $table->dateTime('fecha_generacion')->nullable();
            $table->unsignedInteger('numero_paginas')->nullable();
            $table->string('estado', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
