<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equivalencia Android: IncumplimientoCatalogoEntity → tabla incumplimientos_catalogo
 * Catálogo institucional de 6 tipos de incumplimiento (I-01 a I-06).
 * Campos Android: id, codigo, descripcion, baseLegal→base_legal, activo
 *
 * Nota: los textos reales de base_legal son placeholders en Android
 * ("Art. X", "Art. Y"…). El seeder cargará los textos reales normativos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incumplimientos_catalogo', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();   // I-01 … I-06
            $table->text('descripcion');
            $table->string('base_legal', 300)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incumplimientos_catalogo');
    }
};
