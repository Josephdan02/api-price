<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equivalencia Android: EstablecimientoEntity → tabla establecimientos
 * Campos Android (camelCase → snake_case):
 *   id, codigoOsinergmin, registroHidrocarburos, razonSocial, nombreComercial,
 *   rucDni, telefono, fax, direccion, distrito, provincia, departamento,
 *   latitud, longitud
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establecimientos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_osinergmin', 20)->nullable()->index();
            $table->string('registro_hidrocarburos', 50)->nullable();
            $table->string('razon_social', 200);
            $table->string('nombre_comercial', 200)->nullable();
            $table->string('ruc_dni', 20)->nullable()->index();
            $table->string('telefono', 30)->nullable();
            $table->string('fax', 30)->nullable();
            $table->string('direccion', 300)->nullable();
            $table->string('distrito', 100)->nullable();
            $table->string('provincia', 100)->nullable();
            $table->string('departamento', 100)->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establecimientos');
    }
};
