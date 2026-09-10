# FASE 6.4.2 — API REST CRUD de Establecimientos

**Proyecto:** API REST Laravel — Sistema PRICE  
**Fecha:** 2026-09-09  
**Estado:** ✅ Completada

---

## 1. Resumen

Se implementó el CRUD REST completo de Establecimientos con:

- 5 endpoints REST
- Autenticación Sanctum en todos los endpoints
- Autorización granular por rol (ADMIN / FISCALIZADOR / CONSULTA)
- Validaciones con FormRequests
- Protección contra establecimientos duplicados (por `codigo_osinergmin`)
- Paginación en el listado
- 19 tests Feature (17 requeridos + 2 bonus), todos pasando

---

## 2. Endpoints

| Método | Ruta | Roles permitidos | Descripción |
|---|---|---|---|
| `GET` | `/api/establecimientos` | ADMIN, FISCALIZADOR, CONSULTA | Listado paginado |
| `GET` | `/api/establecimientos/{id}` | ADMIN, FISCALIZADOR, CONSULTA | Consultar uno |
| `POST` | `/api/establecimientos` | ADMIN, FISCALIZADOR | Crear |
| `PUT` | `/api/establecimientos/{id}` | ADMIN, FISCALIZADOR | Actualizar |
| `DELETE` | `/api/establecimientos/{id}` | ADMIN | Eliminar |

---

## 3. Autenticación

Todos los endpoints requieren token Sanctum en el header:

```
Authorization: Bearer {token}
```

Sin token → HTTP 401

---

## 4. Autorización por roles

| Acción | ADMIN | FISCALIZADOR | CONSULTA |
|---|---|---|---|
| Listar | ✅ | ✅ | ✅ |
| Ver uno | ✅ | ✅ | ✅ |
| Crear | ✅ | ✅ | ❌ 403 |
| Actualizar | ✅ | ✅ | ❌ 403 |
| Eliminar | ✅ | ❌ 403 | ❌ 403 |

Implementado mediante el middleware `EnsureUserHasRole` con alias `role`.  
Configuración en `routes/api.php` usando grupos de middleware:

```php
Route::middleware('role:ADMIN,FISCALIZADOR,CONSULTA')->group(...); // lectura
Route::middleware('role:ADMIN,FISCALIZADOR')->group(...);          // escritura
Route::middleware('role:ADMIN')->group(...);                        // eliminación
```

---

## 5. Parámetros de request

### POST `/api/establecimientos`

```json
{
    "razon_social": "Grifo San Martín S.A.C.",
    "codigo_osinergmin": "DH12345",
    "registro_hidrocarburos": "DRH-0001",
    "nombre_comercial": "Grifo San Martín",
    "ruc_dni": "20123456789",
    "telefono": "01-2345678",
    "fax": "01-2345679",
    "direccion": "Av. Principal 123",
    "distrito": "Lima",
    "provincia": "Lima",
    "departamento": "Lima",
    "latitud": -12.0464,
    "longitud": -77.0428,
    "activo": true
}
```

### PUT `/api/establecimientos/{id}`

Mismos campos que POST, todos opcionales (`sometimes`).  
Se pueden enviar solo los campos que se desea actualizar.

---

## 6. Reglas de validación

| Campo | Store | Update | Reglas |
|---|---|---|---|
| `razon_social` | requerido | opcional | string, max:200 |
| `codigo_osinergmin` | opcional | opcional | string, max:20, **unique** (ignore self en update) |
| `registro_hidrocarburos` | opcional | opcional | string, max:50 |
| `nombre_comercial` | opcional | opcional | string, max:200 |
| `ruc_dni` | opcional | opcional | string, max:20 |
| `telefono` | opcional | opcional | string, max:30 |
| `fax` | opcional | opcional | string, max:30 |
| `direccion` | opcional | opcional | string, max:300 |
| `distrito` | opcional | opcional | string, max:100 |
| `provincia` | opcional | opcional | string, max:100 |
| `departamento` | opcional | opcional | string, max:100 |
| `latitud` | opcional | opcional | numeric, between:-90,90 |
| `longitud` | opcional | opcional | numeric, between:-180,180 |
| `activo` | opcional | opcional | boolean |

### Protección contra duplicados

Si se proporciona `codigo_osinergmin`, debe ser único en la tabla.  
En update, se ignora el propio registro para evitar falso positivo.

**Justificación:** la migración define `codigo_osinergmin` como nullable con INDEX pero sin UNIQUE constraint a nivel de BD. La unicidad se enforcea a nivel de aplicación (FormRequest) para mantener compatibilidad con datos donde el campo es null (múltiples registros sin código asignado son válidos).

---

## 7. Códigos HTTP de respuesta

| Situación | Código |
|---|---|
| Listado exitoso | 200 |
| Consulta exitosa | 200 |
| Creación exitosa | 201 |
| Actualización exitosa | 200 |
| Eliminación exitosa | 200 |
| Sin autenticación | 401 |
| Sin autorización (rol) | 403 |
| Recurso no encontrado | 404 |
| Datos de validación inválidos | 422 |

---

## 8. Ejemplos de respuestas JSON

### GET `/api/establecimientos` — 200 OK

```json
{
    "success": true,
    "message": "Lista de establecimientos.",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "codigo_osinergmin": "DH12345",
                "razon_social": "Grifo San Martín S.A.C.",
                "nombre_comercial": "Grifo San Martín",
                "ruc_dni": "20123456789",
                "telefono": "01-2345678",
                "direccion": "Av. Principal 123",
                "distrito": "Lima",
                "provincia": "Lima",
                "departamento": "Lima",
                "latitud": "-12.0464000",
                "longitud": "-77.0428000",
                "activo": true,
                "created_at": "2026-09-09T00:00:00.000000Z",
                "updated_at": "2026-09-09T00:00:00.000000Z"
            }
        ],
        "first_page_url": "http://localhost:8000/api/establecimientos?page=1",
        "last_page": 1,
        "per_page": 15,
        "total": 1
    }
}
```

### POST `/api/establecimientos` — 201 Created

```json
{
    "success": true,
    "message": "Establecimiento creado correctamente.",
    "data": {
        "establecimiento": {
            "id": 2,
            "razon_social": "Grifo Nuevo S.A.C.",
            "codigo_osinergmin": "DH99999",
            ...
        }
    }
}
```

### DELETE `/api/establecimientos/{id}` — 200 OK

```json
{
    "success": true,
    "message": "Establecimiento eliminado correctamente."
}
```

### Error 401 (sin token)

```json
{
    "message": "Unauthenticated."
}
```

### Error 403 (rol insuficiente)

```json
{
    "success": false,
    "message": "No tiene permisos para realizar esta acción.",
    "rol_requerido": ["ADMIN"],
    "rol_actual": "CONSULTA"
}
```

### Error 404 (no encontrado)

```json
{
    "success": false,
    "message": "Establecimiento no encontrado."
}
```

### Error 422 (validación)

```json
{
    "message": "The razon social field is required.",
    "errors": {
        "razon_social": ["La razón social es obligatoria."],
        "codigo_osinergmin": ["Ya existe un establecimiento con ese código Osinergmin."]
    }
}
```

---

## 9. Paginación

El listado `GET /api/establecimientos` devuelve 15 registros por página, ordenados por `razon_social` ascendente.

Para navegar entre páginas:

```
GET /api/establecimientos?page=2
```

---

## 10. Archivos implementados

| Archivo | Descripción |
|---|---|
| `app/Http/Controllers/EstablecimientoController.php` | Controlador CRUD |
| `app/Http/Requests/StoreEstablecimientoRequest.php` | Validación creación |
| `app/Http/Requests/UpdateEstablecimientoRequest.php` | Validación actualización |
| `routes/api.php` | Rutas actualizadas |
| `tests/Feature/EstablecimientoTest.php` | 19 tests Feature |

---

## 11. Tests implementados

Archivo: `tests/Feature/EstablecimientoTest.php`  
Suite: Feature | BD: `price_api_test` | Estrategia: `RefreshDatabase`

| # | Test | Verifica |
|---|---|---|
| 1 | `admin_puede_listar_establecimientos` | GET 200 + estructura paginada |
| 2 | `fiscalizador_puede_listar_establecimientos` | GET 200 |
| 3 | `consulta_puede_listar_establecimientos` | GET 200 |
| 4 | `admin_puede_consultar_un_establecimiento` | GET/{id} 200 + datos correctos |
| 5 | `admin_puede_crear_establecimiento` | POST 201 + BD |
| 6 | `fiscalizador_puede_crear_establecimiento` | POST 201 |
| 7 | `consulta_no_puede_crear_establecimiento` | POST 403 |
| 8 | `admin_puede_actualizar_establecimiento` | PUT 200 + BD |
| 9 | `fiscalizador_puede_actualizar_establecimiento` | PUT 200 |
| 10 | `consulta_no_puede_actualizar_establecimiento` | PUT 403 |
| 11 | `admin_puede_eliminar_establecimiento` | DELETE 200 + assertMissing |
| 12 | `fiscalizador_no_puede_eliminar_establecimiento` | DELETE 403 + assertHas |
| 13 | `consulta_no_puede_eliminar_establecimiento` | DELETE 403 |
| 14 | `usuario_no_autenticado_recibe_401` | 5 verbos sin token → 401 |
| 15 | `id_inexistente_devuelve_404` | GET/PUT/DELETE 404 |
| 16 | `datos_invalidos_devuelven_422` | 3 casos de validación |
| 17 | `no_se_puede_crear_establecimiento_con_codigo_osinergmin_duplicado` | 422 en duplicado |
| B1 | `update_no_falla_por_su_propio_codigo_osinergmin` | self-unique ignore |
| B2 | `listado_tiene_estructura_paginada` | per_page=15 + campos paginación |

**Resultado:** `OK (49 tests, 176 assertions)` — suite completa.

---

## 12. Resultado de validaciones finales

### `php artisan migrate:status`
```
16 migraciones — todas [Ran] — sin pendientes
Sin modificaciones al esquema existente
```

### `php artisan route:list`
```
13 rutas activas:
  GET|HEAD  api/establecimientos           EstablecimientoController@index
  POST      api/establecimientos           EstablecimientoController@store
  GET|HEAD  api/establecimientos/{id}      EstablecimientoController@show
  PUT       api/establecimientos/{id}      EstablecimientoController@update
  DELETE    api/establecimientos/{id}      EstablecimientoController@destroy
  POST      api/login                      AuthController@login
  POST      api/logout                     AuthController@logout
  GET|HEAD  api/user                       AuthController@user
  (+ sanctum, storage, up, web)
```

### `phpunit`
```
OK (49 tests, 176 assertions)
PHPUnit 11.5.56 — PHP 8.2.30
Time: 00:04.314
```

---

## 13. Decisiones de diseño

### Unicidad de `codigo_osinergmin`
La migración define el campo como nullable con INDEX pero sin UNIQUE constraint. Se optó por validar unicidad a nivel de aplicación (FormRequest) porque:
- Múltiples establecimientos pueden no tener código asignado (NULL es válido en varios registros).
- El constraint UNIQUE en MySQL con valores NULL tiene comportamiento variable entre versiones.
- La validación de Laravel `unique` ignora valores NULL automáticamente.

### Paginación de 15 registros
Sistema PRICE maneja establecimientos de combustibles en una región/país. 15 registros por página es apropiado para consumo desde la app Android y permite navegación fluida sin sobrecargar la red.

### HTTP 200 en DELETE
Se usa 200 con body JSON en lugar de 204 (sin body) para mantener consistencia con el formato `{success, message}` usado en todos los endpoints de la API.

---

## 14. Pendiente para fases posteriores

- Filtros de búsqueda en listado (por `razon_social`, `distrito`, `departamento`, `activo`)
- Endpoint de búsqueda `GET /api/establecimientos?search=...` (equivalente al `EstablecimientoDao.search()` de Android)
- Soft delete opcional para establecimientos con fiscalizaciones asociadas
- Endpoint `GET /api/establecimientos/{id}/fiscalizaciones` para ver las fiscalizaciones de un establecimiento
