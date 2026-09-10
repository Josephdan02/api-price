# FASE 6.4.4.6 — CRUD REST DE FIRMAS

## OBJETIVO

Implementar el CRUD REST de firmas, que registra las firmas del acta:
fiscalizador responsable y agente fiscalizado (o su representante).
Una fiscalizacion puede tener multiples firmas (relacion 1:N).

## ENDPOINTS

| Metodo | Endpoint | Descripcion |
|--------|-----------|-------------|
| GET | `/api/firmas` | Lista paginada de firmas |
| GET | `/api/firmas/{id}` | Consulta una firma por ID |
| POST | `/api/firmas` | Crea una nueva firma |
| PUT | `/api/firmas/{id}` | Actualiza una firma existente |
| DELETE | `/api/firmas/{id}` | Elimina una firma existente |

## AUTORIZACION

Los endpoints utilizan `auth:sanctum` y el middleware de rol existente:

| Rol | GET index/show | POST | PUT | DELETE |
|-----|----------------|------|-----|--------|
| ADMIN | OK | OK | OK | OK |
| FISCALIZADOR | OK | OK | OK | NO 403 |
| CONSULTA | OK | NO 403 | NO 403 | NO 403 |
| Sin autenticacion | NO 401 | NO 401 | NO 401 | NO 401 |

## ESTRUCTURA DE REQUEST

### Crear firma (POST)

```json
{
  "fiscalizacion_id": 1,
  "tipo_firma": "FISCALIZADOR",
  "nombre_completo": "Juan Perez Fiscalizador",
  "dni": "12345678",
  "relacion_agente": null,
  "imagen_firma": null,
  "fecha_firma": "2024-01-15"
}
```

### Actualizar firma (PUT)

```json
{
  "tipo_firma": "AGENTE",
  "nombre_completo": "Agente Dos",
  "relacion_agente": "Propietario"
}
```

Campos opcionales en update: se pueden enviar solo los campos a modificar
(`sometimes`).

## ESTRUCTURA DE RESPONSE

Lista, individual, creacion/actualizacion y eliminacion siguen el envelope
`{success, message, data}` y paginacion de 15 por pagina, igual que 6.4.4.5.

## VALIDACIONES

### StoreFirmaRequest

- `fiscalizacion_id`: required, exists:fiscalizaciones,id
- `tipo_firma`: required, in:FISCALIZADOR,AGENTE
- `nombre_completo`: nullable, string, max:200
- `dni`: nullable, string, max:20
- `relacion_agente`: nullable, string, max:100
- `imagen_firma`: nullable, string
- `fecha_firma`: nullable, date

### UpdateFirmaRequest

- Mismos campos con `sometimes` (+ `nullable` en opcionales).
- No hay regla de unicidad: el esquema NO tiene UNIQUE.

## REGLA DE NEGOCIO 1:N

La tabla `firmas` NO tiene UNIQUE sobre `fiscalizacion_id` ni sobre
`(fiscalizacion_id, tipo_firma)`:

- Una fiscalizacion puede tener N firmas.
- Caso tipico: 1 firma FISCALIZADOR + 1 firma AGENTE.
- Incluso se permite repetir el mismo `tipo_firma` en la misma
  fiscalizacion (sin restriccion de BD, no se inventa validacion).
- FK `fiscalizacion_id -> fiscalizaciones` con `cascadeOnDelete`.

## RELACIONES

- `Firma::fiscalizacion()`: BelongsTo -> Fiscalizacion
- `Fiscalizacion::firmas()`: HasMany -> Firma
- Eager loading en `index/show/store/update`: `fiscalizacion`.

## CAMPOS DE LA TABLA

`firmas`:
- `id`, `fiscalizacion_id` FK cascade, `tipo_firma` enum, `nombre_completo`
  200 nullable, `dni` 20 nullable, `relacion_agente` 100 nullable,
  `imagen_firma` longText nullable, `fecha_firma` date nullable, timestamps.

## TESTS REALIZADOS

28 tests en `tests/Feature/FirmaTest.php` (83 assertions).
Incluye prueba 1:N: 3 POST a la misma fiscalizacion (FISCALIZADOR, AGENTE,
FISCALIZADOR) todos 201 y `assertCount(3)`.

## ARCHIVOS CREADOS

1. `app/Http/Controllers/FirmaController.php`
2. `app/Http/Requests/StoreFirmaRequest.php`
3. `app/Http/Requests/UpdateFirmaRequest.php`
4. `database/factories/FirmaFactory.php`
5. `tests/Feature/FirmaTest.php`
6. `docs/FASE_6_4_4_6_FIRMAS.md`

## ARCHIVOS MODIFICADOS

1. `routes/api.php` — import + bloque de rutas de firmas.

## VERIFICACIONES FINALES

- `php artisan route:list --path=api/firmas`: 5 rutas.
- `php artisan migrate:status`: sin pendientes.
- `php artisan test --filter=FirmaTest`: 28 passed (83 assertions).
- `php artisan test`: 215 passed (678 assertions).

FASE 6.4.4.6 TERMINADA — NO se implemento ninguna fase posterior.
