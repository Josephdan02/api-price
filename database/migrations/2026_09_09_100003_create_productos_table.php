<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equivalencia Android: ProductoEntity → tabla productos
 * Catálogo de 11 combustibles/productos PRICE hardcodeado en Android.
 * IDs fijos 1-11 usados por PrecioRepository.initializeCatalog().
 * Campos: id, nombre, categoria, unidad, activo
 *
 * Categorías Android: "Líquidos", "GLP", "Envasado", "Otros"
 * Unidades Android: "Galón", "Litro", "3 kg"…"45 kg", "N/A"
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->enum('categoria', ['Líquidos', 'GLP', 'Envasado', 'Otros']);
            $table->string('unidad', 20);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
