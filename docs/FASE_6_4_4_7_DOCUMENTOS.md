# FASE 6.4.4.7 — CRUD REST DE DOCUMENTOS

## OBJETIVO

CRUD REST de documentos del acta (PDF generado). Relacion 1-a-1 con
fiscalizacion (UNIQUE en `fiscalizacion_id` + `HasOne`).

## ARCHIVOS CREADOS

1. `app/Http/Controllers/DocumentoController.php`
2. `app/Http/Requests/StoreDocumentoRequest.php`
3. `app/Http/Requests/UpdateDocumentoRequest.php`
4. `database/factories/DocumentoFactory.php`
5. `tests/Feature/DocumentoTest.php`
6. `docs/FASE_6_4_4_7_DOCUMENTOS.md`

## ARCHIVOS MODIFICADOS

1. `routes/api.php` — import + bloque Documentos.

## ENDPOINTS

| Metodo | Endpoint | Auth |
|---|---|---|
| GET | `/api/documentos` | ADMIN, FISCALIZADOR, CONSULTA |
| GET | `/api/documentos/{id}` | ADMIN, FISCALIZADOR, CONSULTA |
| POST | `/api/documentos` | ADMIN, FISCALIZADOR |
| PUT | `/api/documentos/{id}` | ADMIN, FISCALIZADOR |
| DELETE | `/api/documentos/{id}` | ADMIN |

Ejemplo POST:

```json
{
  "fiscalizacion_id": 1,
  "nombre_archivo": "acta-0001.pdf",
  "ruta_archivo": "documentos/acta-0001.pdf",
  "fecha_generacion": "2024-01-15 10:30:00",
  "numero_paginas": 5,
  "estado": "GENERADO"
}
```

Respuesta 201:

```json
{"success": true, "message": "Documento creado correctamente.",
 "data": {"documento": {"id": 1, "fiscalizacion_id": 1, "...": "..."}}}
```

## VALIDACIONES

Store: `fiscalizacion_id` required/exists/unique + `withValidator` amistoso
1-a-1; `nombre_archivo` max:300; `ruta_archivo` max:500; `fecha_generacion`
date; `numero_paginas` integer min:0; `estado` max:50. Todo opcional nullable.
Update: `sometimes` + `Rule::unique(...)->ignore($id)` + `withValidator`
que ignora el propio registro (maneja route param string/ID como en
Observacion/Verificacion). `estado` queda string(50) libre, sin ENUM
inventado.

## RELACION 1:1

`documentos.fiscalizacion_id` UNIQUE + cascadeOnDelete.
`Fiscalizacion::documento(): HasOne`, `Documento::fiscalizacion(): BelongsTo`.
Eager `fiscalizacion` en index/show/store/update. Paginate 15, order id desc.

## TESTS

27 tests, 81 assertions: index x4, show x2, post x8 (incluye rechazo 2do
documento 422 + assertCount 1), put x6 (incluye conserva propio ID y unique
contra otro), delete x4, eager + cascade (borrar fiscalizacion elimina
documento).

## RESULTADOS

- DocumentoTest: 27 passed (81 assertions).
- Suite: 242 passed (759 assertions).
- route:list --path=api/documentos: 5 rutas.
- migrate:status: 17 Ran, 0 pendientes.

## PROBLEMAS

1. Factory con `fake()->optional()->dateTime()->format()` devuelve null->format
   (Error). Solucion: ternario con `fake()->boolean(70)`.
2. Test combinado delete 404+401 en uno fallaba: tras `actingAs(admin)` el
   contexto persistia y el delete sin token daba 200. Solucion: separar en
   `delete_inexistente_404` y `delete_sin_autenticacion`.
3. Sin inconsistencias de esquema: migracion/modelo/pedido coinciden.

FASE 6.4.4.7 TERMINADA — NO se implemento FASE 6.4.5 ni PDF.
