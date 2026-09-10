<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EstablecimientoController;
use App\Http\Controllers\FiscalizacionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Sistema PRICE
|--------------------------------------------------------------------------
|
| Rutas públicas:
|   POST /api/login
|
| Rutas protegidas (auth:sanctum):
|   GET  /api/user
|   POST /api/logout
|
| Establecimientos (auth:sanctum + role):
|   GET    /api/establecimientos          → ADMIN, FISCALIZADOR, CONSULTA
|   GET    /api/establecimientos/{id}     → ADMIN, FISCALIZADOR, CONSULTA
|   POST   /api/establecimientos          → ADMIN, FISCALIZADOR
|   PUT    /api/establecimientos/{id}     → ADMIN, FISCALIZADOR
|   DELETE /api/establecimientos/{id}     → ADMIN
|
*/

// ── Autenticación pública ─────────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);

// ── Rutas protegidas por Sanctum ──────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::get('/user',    [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ── Establecimientos ──────────────────────────────────────────────────────

    // Lectura: todos los roles autenticados
    Route::middleware('role:ADMIN,FISCALIZADOR,CONSULTA')->group(function () {
        Route::get('/establecimientos',        [EstablecimientoController::class, 'index']);
        Route::get('/establecimientos/{establecimiento}', [EstablecimientoController::class, 'show']);
    });

    // Escritura: ADMIN y FISCALIZADOR
    Route::middleware('role:ADMIN,FISCALIZADOR')->group(function () {
        Route::post('/establecimientos',                  [EstablecimientoController::class, 'store']);
        Route::put('/establecimientos/{establecimiento}', [EstablecimientoController::class, 'update']);
    });

    // Eliminación: solo ADMIN
    Route::middleware('role:ADMIN')->group(function () {
        Route::delete('/establecimientos/{establecimiento}', [EstablecimientoController::class, 'destroy']);
    });

    // ── Fiscalizaciones ───────────────────────────────────────────────────────

    // Lectura: todos los roles autenticados
    Route::middleware('role:ADMIN,FISCALIZADOR,CONSULTA')->group(function () {
        Route::get('/fiscalizaciones',        [FiscalizacionController::class, 'index']);
        Route::get('/fiscalizaciones/{fiscalizacion}', [FiscalizacionController::class, 'show']);
    });

    // Escritura: ADMIN y FISCALIZADOR
    Route::middleware('role:ADMIN,FISCALIZADOR')->group(function () {
        Route::post('/fiscalizaciones',                  [FiscalizacionController::class, 'store']);
        Route::put('/fiscalizaciones/{fiscalizacion}', [FiscalizacionController::class, 'update']);
    });
});
