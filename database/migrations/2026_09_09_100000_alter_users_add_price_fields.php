<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extiende la tabla users de Laravel con campos del dominio PRICE.
 * users = usuarios del sistema PRICE (fiscalizadores, admins, consulta).
 *
 * Equivalencia Android: UsuarioEntity → tabla usuarios
 * Android campos: id, usuario, nombre, apellido, dni, rol, token
 * Decisión: se reutiliza users (tiene name, email, password ya).
 *           Se agrega: dni, rol, activo. El campo 'usuario' de Android
 *           equivale a 'email' de Laravel para el login.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('dni', 20)->nullable()->after('email');
            $table->enum('rol', ['ADMIN', 'FISCALIZADOR', 'CONSULTA'])
                  ->default('FISCALIZADOR')
                  ->after('dni');
            $table->boolean('activo')->default(true)->after('rol');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['dni', 'rol', 'activo']);
        });
    }
};
