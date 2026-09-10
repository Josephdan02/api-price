# FASE 6.4.5 — ACTA DE FISCALIZACION EN PDF

## Objetivo

Generar el Acta de Fiscalizacion PRICE en PDF desde una fiscalizacion
existente, integrando precios, verificacion, incumplimientos, hechos,
observaciones y firmas; persistiendo el resultado en Documento (1-a-1).

## Endpoint

GET /api/fiscalizaciones/{fiscalizacion}/acta/pdf
Auth: auth:sanctum. Roles: ADMIN, FISCALIZADOR, CONSULTA.
404 JSON si no existe. 200 application/pdf si genera.
500 JSON generico si falla la generacion (sin stack trace).

## Archivos creados

1. app/Services/MinimalPdf.php — escritor PDF 1.4 propio.
2. app/Services/ActaFiscalizacionPdfService.php — carga relaciones,
   construye contenido, guarda archivo, upsert Documento.
3. app/Http/Controllers/ActaFiscalizacionController.php — metodo pdf().
4. tests/Feature/ActaFiscalizacionPdfTest.php — 9 tests.
5. docs/FASE_6_4_5_ACTA_PDF.md — este archivo.

## Archivos modificados

1. routes/api.php — import + ruta acta/pdf en grupo lectura.

## Libreria PDF

Sin dependencias nuevas: no hay red para composer y vendor no trae
dompdf/tcpdf/mpdf. Se implemento MinimalPdf (PDF 1.4, Helvetica base,
streams sin comprimir, A4 multipagina, texto ASCII transliterado).
Si a futuro hay red: barryvdh/laravel-dompdf + Blade seria la via.

## Flujo

1. Controller busca Fiscalizacion, 404 si no existe.
2. Servicio loadMissing de 8 relaciones + orden de precios.
3. Construye cabecera/I/precios/verificacion/II/III/firmas/base legal.
4. Render PDF, filename acta-{expediente}.pdf, path actas/... .
5. Storage::disk(public)->put; borra anterior si cambio de ruta.
6. Documento::updateOrCreate por fiscalizacion_id (idempotente);
   setea nombre/ruta/fecha/numero_paginas, NO toca estado ni
   estado de fiscalizacion. Devuelve PDF inline + headers X-Acta-*.

## Storage

Disco public (storage/app/public/actas), ruta relativa en BD.
Tests usan Storage::fake(public). Regeneracion reemplaza archivo
y reutiliza Documento (sin huerfanos).

## Tests

9 tests / 31 assertions: 401 sin token (getJson), 200 x3 roles,
404 inexistente, %PDF- + Content-Type + expediente + FISCALIZACION,
minima sin relaciones, sin user_id, estado BORRADOR intacto,
CONSULTA no muta precios + crea 1 Documento, idempotencia mismo ID.
Suite: 251 passed (790 assertions). migrate:status 17 Ran.

## Problemas

1. Servicio quedo corrupto por inserts solapados; reconstruido, php -l OK.
2. Test 401 con get() daba 500 Route [login]; fix getJson().
