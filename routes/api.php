<?php

use App\Http\Controllers\ActaFiscalizacionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\EstablecimientoController;
use App\Http\Controllers\FiscalizacionController;
use App\Http\Controllers\FirmaController;
use App\Http\Controllers\FiscalizacionIncumplimientoController;
use App\Http\Controllers\HechoVerificadoController;
use App\Http\Controllers\ObservacionController;
use App\Http\Controllers\PrecioController;
use App\Http\Controllers\VerificacionController;
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
        Route::get('/establecimientos/{establecimiento}/fiscalizaciones', [EstablecimientoController::class, 'fiscalizaciones']);
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
        Route::get('/fiscalizaciones/{fiscalizacion}/acta/pdf', [ActaFiscalizacionController::class, 'pdf']);
    });

    // Escritura: ADMIN y FISCALIZADOR
    Route::middleware('role:ADMIN,FISCALIZADOR')->group(function () {
        Route::post('/fiscalizaciones',                  [FiscalizacionController::class, 'store']);
        Route::put('/fiscalizaciones/{fiscalizacion}', [FiscalizacionController::class, 'update']);
        Route::patch('/fiscalizaciones/{fiscalizacion}', [FiscalizacionController::class, 'patch']);
    });

    // ── Precios ───────────────────────────────────────────────────────────────

    // Lectura: todos los roles autenticados
    Route::middleware('role:ADMIN,FISCALIZADOR,CONSULTA')->group(function () {
        Route::get('/precios',        [PrecioController::class, 'index']);
        Route::get('/precios/{precio}', [PrecioController::class, 'show']);
    });

    // Escritura: ADMIN y FISCALIZADOR
    Route::middleware('role:ADMIN,FISCALIZADOR')->group(function () {
        Route::post('/precios',                  [PrecioController::class, 'store']);
        Route::put('/precios/{precio}', [PrecioController::class, 'update']);
    });

    // ── Verificaciones ─────────────────────────────────────────────────────────

    // Lectura: todos los roles autenticados
    Route::middleware('role:ADMIN,FISCALIZADOR,CONSULTA')->group(function () {
        Route::get('/verificaciones',        [VerificacionController::class, 'index']);
        Route::get('/verificaciones/{verificacion}', [VerificacionController::class, 'show']);
    });

    // Escritura: ADMIN y FISCALIZADOR
    Route::middleware('role:ADMIN,FISCALIZADOR')->group(function () {
        Route::post('/verificaciones',                  [VerificacionController::class, 'store']);
        Route::put('/verificaciones/{verificacion}', [VerificacionController::class, 'update']);
    });

    // ── Incumplimientos ───────────────────────────────────────────────────────

    // Lectura: todos los roles autenticados
    Route::middleware('role:ADMIN,FISCALIZADOR,CONSULTA')->group(function () {
        Route::get('/incumplimientos',        [FiscalizacionIncumplimientoController::class, 'index']);
        Route::get('/incumplimientos/{incumplimiento}', [FiscalizacionIncumplimientoController::class, 'show']);
    });

    // Escritura: ADMIN y FISCALIZADOR
    Route::middleware('role:ADMIN,FISCALIZADOR')->group(function () {
        Route::post('/incumplimientos',                  [FiscalizacionIncumplimientoController::class, 'store']);
        Route::put('/incumplimientos/{incumplimiento}', [FiscalizacionIncumplimientoController::class, 'update']);
    });

    // Eliminación: solo ADMIN
    Route::middleware('role:ADMIN')->group(function () {
        Route::delete('/incumplimientos/{incumplimiento}', [FiscalizacionIncumplimientoController::class, 'destroy']);
    });

    // ── Hechos Verificados ───────────────────────────────────────────────────

    // Lectura: todos los roles autenticados
    Route::middleware('role:ADMIN,FISCALIZADOR,CONSULTA')->group(function () {
        Route::get('/hechos-verificados',           [HechoVerificadoController::class, 'index']);
        Route::get('/hechos-verificados/{hecho}', [HechoVerificadoController::class, 'show']);
    });

    // Escritura: ADMIN y FISCALIZADOR
    Route::middleware('role:ADMIN,FISCALIZADOR')->group(function () {
        Route::post('/hechos-verificados',                  [HechoVerificadoController::class, 'store']);
        Route::put('/hechos-verificados/{hecho}', [HechoVerificadoController::class, 'update']);
    });

    // Eliminación: solo ADMIN
    Route::middleware('role:ADMIN')->group(function () {
        Route::delete('/hechos-verificados/{hecho}', [HechoVerificadoController::class, 'destroy']);
    });

    // ── Observaciones ───────────────────────────────────────────────────────

    // Lectura: todos los roles autenticados
    Route::middleware('role:ADMIN,FISCALIZADOR,CONSULTA')->group(function () {
        Route::get('/observaciones',            [ObservacionController::class, 'index']);
        Route::get('/observaciones/{observacion}', [ObservacionController::class, 'show']);
    });

    // Escritura: ADMIN y FISCALIZADOR
    Route::middleware('role:ADMIN,FISCALIZADOR')->group(function () {
        Route::post('/observaciones',                  [ObservacionController::class, 'store']);
        Route::put('/observaciones/{observacion}', [ObservacionController::class, 'update']);
    });

    // Eliminación: solo ADMIN
    Route::middleware('role:ADMIN')->group(function () {
        Route::delete('/observaciones/{observacion}', [ObservacionController::class, 'destroy']);
    });

    // ── Firmas ────────────────────────────────────────────────────────────

    // Lectura: todos los roles autenticados
    Route::middleware('role:ADMIN,FISCALIZADOR,CONSULTA')->group(function () {
        Route::get('/firmas',            [FirmaController::class, 'index']);
        Route::get('/firmas/{firma}', [FirmaController::class, 'show']);
    });

    // Escritura: ADMIN y FISCALIZADOR
    Route::middleware('role:ADMIN,FISCALIZADOR')->group(function () {
        Route::post('/firmas',                  [FirmaController::class, 'store']);
        Route::put('/firmas/{firma}', [FirmaController::class, 'update']);
    });

    // Eliminación: solo ADMIN
    Route::middleware('role:ADMIN')->group(function () {
        Route::delete('/firmas/{firma}', [FirmaController::class, 'destroy']);
    });

    // ── Documentos ────────────────────────────────────────────────────────

    // Lectura: todos los roles autenticados
    Route::middleware('role:ADMIN,FISCALIZADOR,CONSULTA')->group(function () {
        Route::get('/documentos',            [DocumentoController::class, 'index']);
        Route::get('/documentos/{documento}', [DocumentoController::class, 'show']);
    });

    // Escritura: ADMIN y FISCALIZADOR
    Route::middleware('role:ADMIN,FISCALIZADOR')->group(function () {
        Route::post('/documentos',                  [DocumentoController::class, 'store']);
        Route::put('/documentos/{documento}', [DocumentoController::class, 'update']);
    });

    // Eliminación: solo ADMIN
    Route::middleware('role:ADMIN')->group(function () {
        Route::delete('/documentos/{documento}', [DocumentoController::class, 'destroy']);
    });
});
