# FASE 6.4.4.4 — CRUD REST DE HECHOS VERIFICADOS

## OBJETIVO

Implementar el CRUD REST completo del recurso "Hechos Verificados", que documenta evidencia específica de un incumplimiento dentro de una fiscalización.

Un hecho verificado está relacionado con:
- Una fiscalización (`fiscalizacion_id`)
- Un incumplimiento específico en esa fiscalización (`fiscalizacion_incumplimiento_id`)
- Opcionalmente, el usuario que lo registró (`user_id`)

## ENDPOINTS

| Método | Endpoint | Descripción |
|--------|-----------|-------------|
| GET | `/api/hechos-verificados` | Lista paginada de hechos verificados |
| GET | `/api/hechos-verificados/{id}` | Consulta un hecho verificado por ID |
| POST | `/api/hechos-verificados` | Crea un nuevo hecho verificado |
| PUT | `/api/hechos-verificados/{id}` | Actualiza un hecho verificado existente |
| DELETE | `/api/hechos-verificados/{id}` | Elimina un hecho verificado existente |

## AUTORIZACIÓN

Los endpoints utilizan `auth:sanctum` y el middleware de rol existente:

| Rol | GET index/show | POST | PUT | DELETE |
|-----|----------------|------|-----|--------|
| ADMIN | ✅ | ✅ | ✅ | ✅ |
| FISCALIZADOR | ✅ | ✅ | ✅ | ❌ |
| CONSULTA | ✅ | ❌ | ❌ | ❌ |
| Sin autenticación | ❌ | ❌ | ❌ | ❌ |

## ESTRUCTURA DE REQUEST

### Crear hecho verificado (POST)

```json
{
  "fiscalizacion_id": 1,
  "fiscalizacion_incumplimiento_id": 1,
  "user_id": 2,
  "descripcion": "Evidencia del incumplimiento",
  "fecha_registro": "2024-01-15"
}
```

### Actualizar hecho verificado (PUT)

```json
{
  "fiscalizacion_id": 1,
  "fiscalizacion_incumplimiento_id": 1,
  "user_id": 2,
  "descripcion": "Descripción actualizada",
  "fecha_registro": "2024-01-20"
}
```

Campos opcionales: se pueden enviar solo los campos a modificar.

## ESTRUCTURA DE RESPONSE

### Lista (GET /api/hechos-verificados)

```json
{
  "success": true,
  "message": "Lista de hechos verificados.",
  "data": {
    "current_page": 1,
    "data": [...],
    "total": 10,
    ...
  }
}
```

### Individual (GET /api/hechos-verificados/{id})

```json
{
  "success": true,
  "message": "Hecho verificado encontrado.",
  "data": {
    "hecho_verificado": {
      "id": 1,
      "fiscalizacion_id": 1,
      "fiscalizacion_incumplimiento_id": 1,
      "user_id": 2,
      "descripcion": "Evidencia del incumplimiento",
      "fecha_registro": "2024-01-15",
      "created_at": "...",
      "updated_at": "...",
      "fiscalizacion": {...},
      "fiscalizacion_incumplimiento": {...},
      "user": {...}
    }
  }
}
```

### Creación/Actualización (POST/PUT)

```json
{
  "success": true,
  "message": "Hecho verificado creado correctamente.",
  "data": {
    "hecho_verificado": {...}
  }
}
```

### Eliminación (DELETE)

```json
{
  "success": true,
  "message": "Hecho verificado eliminado correctamente."
}
```

### Errores

- 401: No autenticado
- 403: Rol no autorizado
- 404: Recurso no encontrado
- 422: Errores de validación

## VALIDACIONES

### StoreHechoVerificadoRequest

- `fiscalizacion_id`: requerido, debe existir en `fiscalizaciones`
- `fiscalizacion_incumplimiento_id`: requerido, debe existir en `fiscalizacion_incumplimientos`
- `user_id`: nullable, debe existir en `users`
- `descripcion`: requerido, string
- `fecha_registro`: nullable, date (formato Y-m-d)

### UpdateHechoVerificadoRequest

- Todos los campos son opcionales (`sometimes`)
- Se pueden enviar solo los campos a modificar
- Mantiene las mismas validaciones que el store

## REGLAS DE NEGOCIO

### Relación con Fiscalización

Un hecho verificado pertenece a una fiscalización existente. La FK tiene `cascadeOnDelete`, por lo que al eliminar una fiscalización se eliminan en cascada todos sus hechos verificados.

### Relación con FiscalizacionIncumplimiento

Un hecho verificado está asociado a un incumplimiento específico dentro de una fiscalización. La FK tiene `cascadeOnDelete`, por lo que al eliminar un incumplimiento se eliminan en cascada todos sus hechos verificados.

### Relación con Usuario

El usuario que registró el hecho es opcional (`nullable`). La FK tiene `nullOnDelete`, por lo que al eliminar un usuario no se eliminan los hechos verificados asociados.

### No hay restricción UNIQUE

El esquema NO tiene restricción única en `hechos_verificados`. Por lo tanto:
- Una fiscalización puede tener múltiples hechos verificados
- Un incumplimiento puede tener múltiples hechos verificados
- No se valida duplicación en la aplicación (puede haber hechos con misma descripción, fecha, etc.)

## RELACIONES

### HechoVerificado

- `fiscalizacion()`: BelongsTo → Fiscalizacion
- `fiscalizacionIncumplimiento()`: BelongsTo → FiscalizacionIncumplimiento
- `user()`: BelongsTo → User

### Eager Loading

Los endpoints `index` y `show` cargan las relaciones con eager loading:
- `index`: `fiscalizacion`, `fiscalizacionIncumplimiento`, `user`
- `show`: `fiscalizacion`, `fiscalizacionIncumplimiento`, `user`

## CAMPOS DE LA TABLA

`hechos_verificados`:
- `id`: primary key
- `fiscalizacion_id`: FK a `fiscalizaciones` (cascadeOnDelete)
- `fiscalizacion_incumplimiento_id`: FK a `fiscalizacion_incumplimientos` (cascadeOnDelete)
- `user_id`: FK a `users` (nullable, nullOnDelete)
- `descripcion`: text, required
- `fecha_registro`: date, nullable
- `created_at`, `updated_at`: timestamps

## ESTRATEGIA DE ELIMINACIÓN

**Eliminación física**: DELETE elimina físicamente el registro de `hechos_verificados`.

**Cascada**: La tabla tiene FKs con `cascadeOnDelete`:
- `fiscalizacion_id`: al eliminar una fiscalización, se eliminan sus hechos verificados
- `fiscalizacion_incumplimiento_id`: al eliminar un incumplimiento, se eliminan sus hechos verificados

## TESTS REALIZADOS

Se crearon 27 tests en `tests/Feature/HechoVerificadoTest.php`:

1. ✅ index requiere autenticación
2. ✅ show requiere autenticación
3. ✅ store requiere autenticación
4. ✅ update requiere autenticación
5. ✅ destroy requiere autenticación
6. ✅ admin puede listar
7. ✅ fiscalizador puede listar
8. ✅ consulta puede listar
9. ✅ admin puede crear
10. ✅ fiscalizador puede crear
11. ✅ consulta no puede crear
12. ✅ admin puede actualizar
13. ✅ fiscalizador puede actualizar
14. ✅ consulta no puede actualizar
15. ✅ admin puede eliminar
16. ✅ fiscalizador no puede eliminar
17. ✅ consulta no puede eliminar
18. ✅ fiscalizacion_id inválido es rechazado
19. ✅ fiscalizacion_incumplimiento_id inválido es rechazado
20. ✅ datos obligatorios inválidos son rechazados
21. ✅ registro inexistente devuelve 404
22. ✅ eager loading carga relaciones correctamente
23. ✅ campos opcionales pueden ser null
24. ✅ fecha_registro acepta formato válido
25. ✅ fecha_registro inválida es rechazada
26. ✅ update permite campos opcionales
27. ✅ user_id inválido es rechazado

## RESULTADO FINAL

**Tests específicos**: 27 passed (71 assertions)
**Suite completa**: 162 passed (526 assertions)
**Sin fallos**: 0 failures, 0 errors

## ARCHIVOS CREADOS

1. `app/Http/Controllers/HechoVerificadoController.php`
2. `app/Http/Requests/StoreHechoVerificadoRequest.php`
3. `app/Http/Requests/UpdateHechoVerificadoRequest.php`
4. `database/factories/HechoVerificadoFactory.php`
5. `tests/Feature/HechoVerificadoTest.php`
6. `docs/FASE_6_4_4_4_HECHOS_VERIFICADOS.md`

## ARCHIVOS MODIFICADOS

1. `routes/api.php` — agregadas rutas de hechos verificados

## VERIFICACIONES FINALES

```bash
php artisan route:list
```
✅ Rutas registradas correctamente:
- GET /api/hechos-verificados
- GET /api/hechos-verificados/{hecho}
- POST /api/hechos-verificados
- PUT /api/hechos-verificados/{hecho}
- DELETE /api/hechos-verificados/{hecho}

```bash
php artisan migrate:status
```
✅ Todas las migraciones ejecutadas (sin pendientes)

```bash
php artisan test
```
✅ 162 passed (526 assertions)

## PROBLEMAS ENCONTRADOS Y SOLUCIONES

### 1. Tests de autenticación esperaban 403
**Problema**: Los tests iniciales esperaban status 403 para peticiones sin autenticación, pero Sanctum devuelve 401 cuando no hay token.

**Solución**: Cambiar los tests para esperar 401 en lugar de 403 para peticiones sin autenticación.

### 2. No hay restricción UNIQUE en el esquema
**Problema**: Inicialmente se consideró validar duplicación, pero la migración no tiene restricción UNIQUE.

**Solución**: No agregar validación de duplicación. Seguir el esquema existente y permitir múltiples hechos verificados con misma descripción, fecha, etc.

## CONCLUSIÓN

La FASE 6.4.4.4 se ha completado exitosamente. El CRUD de hechos verificados está funcionando correctamente con:
- Validaciones de FKs (fiscalización, incumplimiento, usuario)
- Autorización por rol
- Eager loading de relaciones
- Estrategia de eliminación física con cascada en fiscalización e incumplimiento
- Suite de tests completa y pasando
- Documentación completa

La implementación respeta la arquitectura existente y sigue los patrones establecidos en las fases anteriores. Se mantuvo la consistencia con los controladores de fases anteriores (FiscalizacionIncumplimientoController, VerificacionController, etc.).

No se modificó el esquema de la base de datos, respetando las FKs y restricciones existentes.
