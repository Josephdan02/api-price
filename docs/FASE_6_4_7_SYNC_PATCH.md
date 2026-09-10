# FASE 6.4.7 — SINCRONIZACIÓN / ACTUALIZACIÓN PARCIAL DE FISCALIZACIONES

## 1. Objetivo

Habilitar en el backend Laravel del proyecto PRICE un endpoint **HTTP PATCH** que permita
actualizar **parcialmente** una fiscalización, enviando únicamente los campos que cambian.

Esto prepara el backend para que la futura aplicación Android pueda sincronizar el avance
de una fiscalización (abrir diligencia, registrar hora de cierre, finalizar, etc.) sin tener
que reenviar el objeto completo en cada petición.

**Alcance deliberadamente limitado.** Esta fase NO incluye: fotos, geolocalización, firma
manuscrita/digital, cambios en el PDF/Acta, migraciones, cambios de esquema, colas, UUIDs,
resolución de conflictos ni mecanismos avanzados de merge offline.

---

## 2. Endpoint

```
PATCH /api/fiscalizaciones/{fiscalizacion}
Content-Type: application/json
Authorization: Bearer <token>
```

- **Controller:** `App\Http\Controllers\FiscalizacionController@patch`
- **FormRequest:** `App\Http\Requests\PatchFiscalizacionRequest`
  (extiende `UpdateFiscalizacionRequest` → reutiliza reglas y mensajes)
- **Ruta:** `routes/api.php`, grupo `auth:sanctum` + `role:ADMIN,FISCALIZADOR`

---

## 3. Autorización

| Rol            | PATCH |
|----------------|-------|
| `ADMIN`        | ✅ 200 |
| `FISCALIZADOR` | ✅ 200 |
| `CONSULTA`     | ❌ 403 |
| Sin token      | ❌ 401 |

Se reutiliza el middleware existente `EnsureUserHasRole` (alias `role`) y el guard
`auth:sanctum`. **No se creó autenticación paralela ni se modificó el middleware.**

---

## 4. Campos permitidos

PATCH acepta exactamente los mismos campos que PUT (todos son `sometimes`, por lo que
ninguno es obligatorio):

| Campo                | Reglas (idénticas a PUT)                                    |
|----------------------|-------------------------------------------------------------|
| `establecimiento_id` | `sometimes`, `required`, `exists:establecimientos,id`        |
| `user_id`            | `sometimes`, `nullable`, `exists:users,id` + no CONSULTA     |
| `numero_expediente`  | `sometimes`, `nullable`, `string`, `max:100`                 |
| `fecha_diligencia`   | `sometimes`, `required`, `date`                              |
| `hora_apertura`      | `sometimes`, `required`, `date_format:H:i`                   |
| `hora_cierre`        | `sometimes`, `nullable`, `date_format:H:i`, `after:hora_apertura` |
| `estado`             | `sometimes`, `in:BORRADOR,EN_PROCESO,FINALIZADA,ACTA_GENERADA` |

### Campos derivados / desnormalizados (protegidos)

`agente_fiscalizado`, `codigo_osinergmin`, `registro_hidrocarburos`, `direccion`,
`distrito`, `provincia`, `departamento`, `ruc_dni` y `telefono_fax` **no** forman parte
de las reglas de validación y por tanto **nunca se aceptan del cliente**.

Si el PATCH cambia `establecimiento_id`, el helper privado
`FiscalizacionController::sincronizarDesdeEstablecimiento()` vuelve a copiar esos campos
desde el `Establecimiento` seleccionado. Si no se envía `establecimiento_id`, los valores
derivados permanecen intactos y no pueden corromperse desde la app Android.

> El helper es compartido por `update()` (PUT) y `patch()` (PATCH) para evitar duplicar
> la lógica de negocio.

---

## 5. Ejemplos JSON

### 5.1 Cambiar solo el estado

```http
PATCH /api/fiscalizaciones/1
```

```json
{ "estado": "EN_PROCESO" }
```

### 5.2 Cambiar solo la hora de cierre

```json
{ "hora_cierre": "16:30" }
```

### 5.3 Varios campos a la vez

```json
{
    "estado": "FINALIZADA",
    "hora_cierre": "17:00"
}
```

### 5.4 Secuencia típica de sincronización desde Android

```json
{ "estado": "EN_PROCESO", "user_id": 2 }
```
```json
{ "hora_cierre": "17:00" }
```
```json
{ "estado": "FINALIZADA" }
```

---

## 6. Códigos HTTP

| Código | Situación                                                    |
|--------|--------------------------------------------------------------|
| `200`  | Actualización parcial correcta                               |
| `401`  | Sin token / token inválido                                   |
| `403`  | Rol no autorizado (por ejemplo `CONSULTA`)                   |
| `404`  | Fiscalización inexistente                                    |
| `422`  | Error de validación o regla de negocio (p. ej. transición inválida) |

Respuesta de éxito (misma convención del proyecto):

```json
{
    "success": true,
    "message": "Fiscalización actualizada parcialmente.",
    "data": {
        "fiscalizacion": { "...": "..." }
    }
}
```

`data.fiscalizacion` incluye el conjunto de relaciones del detalle
(`establecimiento`, `user`, `precios`, `verificacion`,
`fiscalizacionIncumplimientos.incumplimientoCatalogo`, `hechosVerificados`,
`observacion`, `firmas`, `documento`).

---

## 7. Reglas de transición de estado

La máquina de estados existente se conserva sin cambios:

```
BORRADOR ──► EN_PROCESO ──► FINALIZADA ──► ACTA_GENERADA
```

- `BORRADOR → FINALIZADA` o `BORRADOR → ACTA_GENERADA` → **422** (`Transición de estado no permitida`).
- Saltos hacia atrás o entre estados no contiguos → **422**.
- `estado` con valor fuera del catálogo → **422**.
- Pasar a `FINALIZADA` o `ACTA_GENERADA` sin `user_id` → **422** sobre `user_id`.
- Asignar un usuario con rol `CONSULTA` como fiscalizador → **422** sobre `user_id`.

---

## 8. Diferencia entre PUT y PATCH

| Aspecto | `PUT /api/fiscalizaciones/{id}` | `PATCH /api/fiscalizaciones/{id}` |
|---------|--------------------------------|-----------------------------------|
| Semántica | Reemplazo (el proyecto usa `sometimes`) | Actualización parcial explícita   |
| FormRequest | `UpdateFiscalizacionRequest` | `PatchFiscalizacionRequest` (hereda) |
| Reglas / mensajes | Idénticas | Idénticas (reutilizadas por herencia) |
| Respuesta | `200` `"Fiscalización actualizada correctamente."` | `200` `"Fiscalización actualizada parcialmente."` |
| Relaciones en `data` | `establecimiento`, `user` | Conjunto completo del detalle |

**PUT sigue funcionando exactamente igual que antes** — no se modificó su
comportamiento ni su contrato. PATCH **complementa** a PUT, no lo reemplaza.

### Nota técnica sobre la herencia del FormRequest

El método del controlador recibe `int $id` (no un modelo `Fiscalizacion`), por lo que
Laravel **no** resuelve route-model binding para `{fiscalizacion}` y
`$this->route('fiscalizacion')` devuelve un **string**.

`PatchFiscalizacionRequest` resuelve el modelo explícitamente mediante el helper privado
`resolverFiscalizacion()` para poder aplicar las reglas de negocio de transición de estados
y de fiscalizador responsable — el mismo patrón que ya usan
`UpdateObservacionRequest`, `UpdatePrecioRequest` y `UpdateVerificacionRequest`.

---

## 9. Uso esperado desde Android (fase posterior)

```kotlin
// Fase posterior — NO implementado en 6.4.7
@PATCH("api/fiscalizaciones/{id}")
suspend fun patchFiscalizacion(
    @Path("id") id: Int,
    @Body body: Map<String, @JvmSuppressWildcards Any?>
): FiscalizacionResponse
```

El backend queda preparado para recibir JSON parcial. **No se creó código Android,
DTOs, Retrofit ni Room** en esta fase.

---

## 10. Archivos creados / modificados

**Creados**

| Archivo | Descripción |
|---------|-------------|
| `app/Http/Requests/PatchFiscalizacionRequest.php` | FormRequest de PATCH (hereda de `UpdateFiscalizacionRequest`) |
| `tests/Feature/FiscalizacionPatchTest.php` | 18 tests de PATCH + regresión PUT/GET |
| `docs/FASE_6_4_7_SYNC_PATCH.md` | Esta documentación |

**Modificados**

| Archivo | Cambio |
|---------|--------|
| `app/Http/Controllers/FiscalizacionController.php` | + `patch()`, + helper `sincronizarDesdeEstablecimiento()` (compartido con `update()`), + `relacionesDetalle()` |
| `routes/api.php` | + `Route::patch('/fiscalizaciones/{fiscalizacion}', [...'patch'])` en el grupo `role:ADMIN,FISCALIZADOR` |

**Sin cambios:** migraciones, esquema de BD, modelos, middleware `EnsureUserHasRole`,
configuración Sanctum, generación de PDF/Acta.

---

## 11. Tests realizados

`php artisan test --filter=FiscalizacionPatchTest`

```
PASS  Tests\Feature\FiscalizacionPatchTest
  ✓ patch sin autenticacion devuelve 401
  ✓ consulta no puede actualizar con patch
  ✓ admin puede actualizar un solo campo
  ✓ patch de hora cierre sin hora apertura en payload
  ✓ fiscalizador puede actualizar varios campos
  ✓ patch permite transicion valida de estado
  ✓ patch respeta transiciones encadenadas
  ✓ patch rechaza transicion invalida
  ✓ patch rechaza finalizar sin fiscalizador
  ✓ patch rechaza datos invalidos
  ✓ patch con establecimiento inexistente falla
  ✓ patch rechaza usuario consulta como fiscalizador
  ✓ patch devuelve 404 para fiscalizacion inexistente
  ✓ put existente continua funcionando
  ✓ get show continua funcionando tras patch
  ✓ index continua funcionando tras patch
  ✓ patch no rompe relaciones existentes
  ✓ sincronizacion parcial estilo android

Tests: 18 passed (74 assertions)
```

### Cobertura de los requisitos A–P

| Requisito | Test |
|-----------|------|
| A. PATCH un solo campo | `admin_puede_actualizar_un_solo_campo`, `patch_de_hora_cierre_sin_hora_apertura_en_payload` |
| B. PATCH varios campos | `fiscalizador_puede_actualizar_varios_campos` |
| C. PATCH estado válido | `patch_permite_transicion_valida_de_estado` |
| D. Respeta transiciones | `patch_respeta_transiciones_encadenadas` |
| E. Rechaza transición inválida | `patch_rechaza_transicion_invalida` |
| F. Rechaza finalizar sin fiscalizador | `patch_rechaza_finalizar_sin_fiscalizador` |
| G. Rechaza datos inválidos | `patch_rechaza_datos_invalidos`, `patch_con_establecimiento_inexistente_falla`, `patch_rechaza_usuario_consulta_como_fiscalizador` |
| H. 404 inexistente | `patch_devuelve_404_para_fiscalizacion_inexistente` |
| I. 401 sin token | `patch_sin_autenticacion_devuelve_401` |
| J/M. 403 CONSULTA | `consulta_no_puede_actualizar_con_patch` |
| K. ADMIN | `admin_puede_actualizar_un_solo_campo` |
| L. FISCALIZADOR | `fiscalizador_puede_actualizar_varios_campos` |
| N. PUT sigue funcionando | `put_existente_continua_funcionando` |
| O. GET sigue funcionando | `get_show_continua_funcionando_tras_patch`, `index_continua_funcionando_tras_patch` |
| P. Relaciones intactas | `patch_no_rompe_relaciones_existentes` |
| Uso Android | `sincronizacion_parcial_estilo_android` |

---

## 12. Suite completa

```
php artisan test
Tests:    285 passed (908 assertions)
Duration: 14.64s
```

- Suite previa a 6.4.7: **267 passed (834 assertions)**
- Añadidos en 6.4.7: **18 passed (74 assertions)**
- **0 failures · 0 errors**

---

## 13. `route:list`

```
php artisan route:list --path=api/fiscalizaciones
Showing [6] routes

GET|HEAD  api/fiscalizaciones ......................... FiscalizacionController@index
POST      api/fiscalizaciones ......................... FiscalizacionController@store
GET|HEAD  api/fiscalizaciones/{fiscalizacion} ......... FiscalizacionController@show
PUT       api/fiscalizaciones/{fiscalizacion} ......... FiscalizacionController@update
PATCH     api/fiscalizaciones/{fiscalizacion} ......... FiscalizacionController@patch
GET|HEAD  api/fiscalizaciones/{fiscalizacion}/acta/pdf  ActaFiscalizacionController@pdf
```

---

## 14. `migrate:status`

```
php artisan migrate:status
→ 17 migraciones · todas [Ran] · 0 pendientes
```

No se creó ni modificó ninguna migración en esta fase.

---

## 15. Problemas encontrados y soluciones

1. **Route-model binding no disponible en el FormRequest.**
   El action recibe `int $id`, por lo que `$this->route('fiscalizacion')` devuelve un string
   y el `withValidator()` heredado de `UpdateFiscalizacionRequest` no podía validar
   transiciones de estado ni el fiscalizador responsable.
   *Solución:* `PatchFiscalizacionRequest` sobreescribe `withValidator()` resolviendo el
   modelo con el helper `resolverFiscalizacion()` — mismo patrón ya usado en los
   FormRequest de Observación, Precio y Verificación.

2. **Estructura de la respuesta de `index()`.**
   El primer intento de test asumió `data.fiscalizaciones.data`, pero `index()` devuelve el
   paginador directamente bajo `data`.
   *Solución:* corregida la aserción a `['data' => ['data', 'current_page', 'total']]`.

3. **`hora_cierre` con regla `after:hora_apertura` sin enviar `hora_apertura`.**
   Verificado que el PATCH parcial de `hora_cierre` funciona correctamente (test
   `patch_de_hora_cierre_sin_hora_apertura_en_payload` → 200); Laravel resuelve la
   comparación sin producir error SQL ni excepción.

---

## 16. Confirmación de alcance

Esta fase **no** implementó ninguna fase posterior: ni fotos, ni geolocalización, ni firma
manuscrita, ni cambios en el Acta/PDF, ni migraciones, ni cambios de esquema, ni
sincronización offline avanzada, ni código Android.
