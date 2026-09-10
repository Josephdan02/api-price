<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equivalencia Android: PrecioEntity → tabla precios
 * Campos Android (camelCase → snake_case):
 *   id, fiscalizacionId→fiscalizacion_id, productoId→producto_id,
 *   precioPrice→precio_price, precioPublicado→precio_publicado,
 *   precioSurtidor→precio_surtidor, precioDescuento→precio_descuento,
 *   tieneDescuento→tiene_descuento, observacion
 *
 * Android FK: fiscalizacionId ON DELETE CASCADE
 *             productoId ON DELETE NO_ACTION
 *
 * Constraint: precios no negativos (CHECK via unsigned en DECIMAL)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscalizacion_id')
                  ->constrained('fiscalizaciones')
                  ->cascadeOnDelete();
            $table->foreignId('producto_id')
                  ->constrained('productos')
                  ->restrictOnDelete();
            $table->decimal('precio_price', 10, 4)->unsigned()->nullable();
            $table->decimal('precio_publicado', 10, 4)->unsigned()->nullable();
            $table->decimal('precio_surtidor', 10, 4)->unsigned()->nullable();
            $table->decimal('precio_descuento', 10, 4)->unsigned()->nullable();
            $table->boolean('tiene_descuento')->default(false);
            $table->text('observacion')->nullable();
            $table->timestamps();

            // Un producto no debe repetirse en la misma fiscalización
            $table->unique(['fiscalizacion_id', 'producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precios');
    }
};
