# FASE 6.4.8 — AUDITORÍA FINAL Y PREPARACIÓN DEL API PARA PRODUCCIÓN

## 1. Objetivo

Auditoría técnica final del backend REST PRICE (Laravel 12 + Sanctum 4 + PHP 8.2 + MySQL)
para dejarlo estable, seguro y compatible con el futuro consumo desde Android.

**No** se agregaron funcionalidades de negocio. **No** se crearon migraciones ni se modificó
el esquema. **No** se reemplazó Sanctum, el generador de PDF ni las rutas existentes.

---

## 2. Inspección realizada

Se auditaron: `.env`, `.env.example`, `.gitignore`, `composer.json`, `bootstrap/app.php`,
`config/app.php`, `config/auth.php`, `config/sanctum.php`, `config/cors.php`, `config/filesystems.php`,
`routes/api.php`, `app/Http/Middleware/`, controllers, Form Requests, modelos, `app/Services/`
(`MinimalPdf`, `ActaFiscalizacionPdfService`), `app/Http/Controllers/ActaFiscalizacionController.php`,
`tests/Feature/`, `storage/`, `public/` y `docs/`.

Comandos de verificación ejecutados (no destructivos):

- `php artisan test` → **285 passed (908 assertions)** antes de correcciones.
- `php artisan migrate:status` → 17/17 `[Ran]`, 0 pendientes.
- `php artisan route:list --path=api` → **48 rutas**.
- `php -l` en los ficheros corregidos (config, tests).

---

## 3. Estado de `.env` / `.env.example`

| Variable | `.env` (local) | Observación |
|----------|----------------|-------------|
| `APP_ENV` | `local` | En producción debe ser `production` |
| `APP_DEBUG` | `true` | En producción debe ser `false` |
| `APP_URL` | `http://localhost:8000` | Sustituir por el dominio real en el hosting |
| `APP_KEY` | `base64:…` (generada) | Nunca versionada; presente solo en `.env` |
| `APP_TIMEZONE` | `America/Lima` | **Ahora efectivo** (antes era ignorado, ver §Correcciones) |
| `DB_*` | MySQL local root | Configurable por env en el hosting |
| `SANCTUM_TOKEN_EXPIRATION` | (ausente → `null`) | Opcional para expirar tokens en producción |

**Verificaciones:**

- `.env` **NO** está versionado (`git ls-files .env` → vacío; `.gitignore` lo excluye).
- `.env.example` **no contiene contraseñas ni secretos** (`APP_KEY=` vacío, DB_PASSWORD vacío).
- `APP_DEBUG` puede desactivarse en producción con `APP_ENV=production` + `APP_DEBUG=false`
  (config: `(bool) env('APP_DEBUG', false)`).
- `APP_URL` se cambia con una variable de entorno (config `env('APP_URL', 'http://localhost')`).
- No hay secretos hardcodeados en PHP; las únicas claves viven en `.env`.

---

## 4. Estado de CORS

**Problema encontrado:** no existía `config/cors.php` → el middleware global `HandleCors`
(registrado por defecto en el framework) operaba sin configuración, por lo que **no se
enviaban cabeceras CORS**.

**Corrección aplicada:** se creó `config/cors.php` con la configuración mínima segura:

```php
'paths'               => ['api/*'],
'allowed_methods'     => ['*'],
'allowed_origins'     => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),
'allowed_origins_patterns' => [],
'allowed_headers'     => ['*'],
'exposed_headers'     => ['X-Acta-Pages', 'X-Acta-Documento-Id'],
'max_age'             => 0,
'supports_credentials'=> false,
```

- Para una **app Android nativa** (Bearer token) CORS es irrelevante — no se introdujo
  configuración especial por Android.
- Para **Postman/Insomnia** tampoco aplica CORS.
- Para un **futuro frontend web**, queda habilitado en `api/*`; en producción se puede
  restringir con `CORS_ALLOWED_ORIGINS=https://dominio1,https://dominio2`.
- `supports_credentials=false` (API de tokens) y se exponen las cabeceras del acta
  `X-Acta-Pages` / `X-Acta-Documento-Id`.

---

## 5. Estado de Sanctum

- **Guard:** `auth:sanctum` en todas las rutas de negocio.
- **Tokens:** se emiten `personal_access_tokens` (migración existente). `createToken('price-api-token')`.
- **Login único:** al autenticarse se **revocan los tokens anteriores** del usuario (sesión única).
- **Logout:** revoca únicamente el token usado en la petición.
- **Expiación:** `config/sanctum.php` tenía `'expiration' => null` (tokens eternos).
  **Corrección mínima:** ahora `'expiration' => env('SANCTUM_TOKEN_EXPIRATION', null)`
  — comportamiento actual intacto, pero el hosting puede activar expiración por env.
- **Usuarios inactivos:** bloqueados en login (403) y en el middleware de roles (401/403).
- **`formatUser()`** nunca expone `password` ni `remember_token`; el modelo los oculta (`$hidden`).

Nota menor documentada: `/api/user` y `/api/logout` solo usan `auth:sanctum` (no el middleware
de rol), por lo que un usuario desactivado tras login podría consultar **su propio** perfil o
cerrar sesión. No representa fuga de datos (solo datos propios) y no se modificó para no
cambiar el contrato actual.

---

## 6. Estado de middleware / roles

- `auth:sanctum` + middleware `role` (alias `EnsureUserHasRole`) en `bootstrap/app.php`.
- Roles verificados: `ADMIN`, `FISCALIZADOR`, `CONSULTA`.
- `EnsureUserHasRole` exige `user` **y** `user->activo`; devuelve 401 si no autenticado y
  403 con `rol_requerido`/`rol_actual` si el rol no coincide.
- Matriz verificada en `routes/api.php`:
  - Lectura (`index/show` + subrecurso + acta PDF): `ADMIN,FISCALIZADOR,CONSULTA`.
  - Escritura (`store/update` + PATCH): `ADMIN,FISCALIZADOR`.
  - Eliminación (`destroy`): `ADMIN`.
- No se encontró ningún endpoint sensible público salvo `POST /api/login` (intencionado).
---

## 7. Estado de rutas

`php artisan route:list --path=api` → **48 rutas** con contratos intactos:

- **Públicas:** `POST /api/login`.
- **Protegidas (auth:sanctum):** `GET /api/user`, `POST /api/logout`.
- **Establecimientos:** GET(2) + subrecurso `/{establecimiento}/fiscalizaciones`, POST, PUT, DELETE.
- **Fiscalizaciones:** GET(2), `GET /{id}/acta/pdf`, POST, PUT, **PATCH** (6.4.7).
- **Precios / Verificaciones / Incumplimientos / Hechos / Observaciones / Firmas / Documentos:**
  GET(2) + POST + PUT (+ DELETE solo ADMIN en los recursos con eliminación).

No se modificaron URLs, métodos HTTP, parámetros de ruta ni agrupación de middleware.

---

## 8. Estado de validaciones

Revisados los 21 Form Requests (Store/Update por recurso + `LoginRequest` + `PatchFiscalizacionRequest`).
Todas las reglas se contrastaron con las migraciones reales:

- `establecimiento_id`/`fiscalizacion_id`/`producto_id`/`user_id` → `exists` + `required` donde la
  columna es NOT NULL.
- Longitudes (`max`) alineadas con los `string(n)` de la migración (expediente 100, DNI 20,
  nombre_archivo 300, etc.).
- `estado` de fiscalización restringido al catálogo `BORRADOR|EN_PROCESO|FINALIZADA|ACTA_GENERADA`.
- `tipo_firma` → `in:FISCALIZADOR,AGENTE` (enum de la migración).
- 1-a-1 reales (`verificaciones.fiscalizacion_id`, `observaciones.fiscalizacion_id`,
  `documentos.fiscalizacion_id`) con `unique`/`withValidator` ignorando el propio registro.
- `PatchFiscalizacionRequest` reutiliza por herencia `UpdateFiscalizacionRequest` (mismas reglas).

**No** se inventó ninguna regla nueva ni se alteró ningún enum/valor permitido.

---

## 9. Estado de respuestas JSON

Convención verificada en todos los CRUD:

```json
{ "success": true, "message": "...", "data": ... }
```

- 200 (lectura/actualización/eliminación), 201 (creación), 401 (sin token), 403 (rol),
  404 (recurso inexistente), 422 (validación), 500 (error controlado en acta).
- No se realizó ninguna reescritura: la compatibilidad con las respuestas existentes tiene
  prioridad y **ningún test de contrato se rompió**.

---

## 10. Estado de manejo de errores

- **Sanctum** devuelve 401 para peticiones sin token en rutas protegidas.
- **Middleware de rol** devuelve 401/403 en el formato JSON del proyecto.
- **Controllers** devuelven 404/422 en formato JSON uniforme.
- **Acta PDF:** `ActaFiscalizacionController@pdf` envuelve la generación en `try/catch` con
  `report($e)` y devuelve **500 JSON genérico** (`"No se pudo generar el acta en PDF."`)
  sin filtrar stack traces ni rutas locales. 404 si la fiscalización no existe; `application/pdf`
  si la generación es correcta (con `Content-Disposition`, `Content-Length`, `X-Acta-Pages`,
  `X-Acta-Documento-Id`).
- La configuración de excepciones (`bootstrap/app.php`) no filtra stack traces: con
  `APP_DEBUG=true` el desarrollador ve detalle; en producción `APP_DEBUG=false` muestra el
  error genérico. No se ocultó información en pruebas.

---

## 11. Estado del PDF / storage

- **Disco:** `Storage::disk('public')` → `storage/app/public` (widget `public`, `visibility => public`).
- **Ruta almacenada:** relativa (`actas/<archivo>.pdf`), sin rutas absolutas de Windows.
- **Nombre:** `acta-<expediente slug>.pdf` (o `acta-fiscalizacion-<id>.pdf` si no hay expediente).
- **Restauración:** si cambia el nombre, se **elimina el archivo anterior** tras escribir el nuevo.
- **Idempotente:** `Documento::updateOrCreate(['fiscalizacion_id' => …])` → nunca se duplica
  el Documento (1:1) y se actualizan `nombre_archivo`, `ruta_archivo`, `fecha_generacion`,
  `numero_paginas` (vía `pageCount()`).
- **No se modifica** el `estado` de la fiscalización ni el estado del documento.
- `MinimalPdf` no se sustituyó por Dompdf ni se añadieron dependencias.
- **Hallazgo:** `public/storage` (enlace simbólico) no existe localmente. No es necesario para
  el endpoint (el PDF se envía por bytes), pero **sí** se requiere `php artisan storage:link`
  en el hosting si algún día se sirve el archivo por URL pública.

---

## 12. Revisión de secretos / Git

- `git ls-files .env` → **vacío** (`.env` está en `.gitignore`).
- `git ls-files` **no** incluye `.env`, ningún `*.key`, ni archivos `secret|credential|password`.
- `.gitignore` excluye: `.env`, `.env.backup`, `.env.production`, `/public/storage`,
  `/storage/*.key`, `/vendor`, logs, caches IDE.
- `.env.example` versionado y **seguro** para GitHub (sin claves, sin contraseñas).
- `APP_KEY` real existe únicamente en `.env` (no versionado).
---

## 13. Estado de migraciones

`php artisan migrate:status` → **17 migraciones, todas `[Ran]`, 0 pendientes**:

users, cache, jobs, personal_access_tokens, alter_users_add_price_fields, establecimientos,
fiscalizaciones, productos, precios, verificaciones, incumplimientos_catalogo,
fiscalizacion_incumplimientos, hechos_verificados, observaciones, firmas, documentos,
alter_fiscalizaciones_make_user_id_nullable.

**No se creó ni modificó ninguna migración** en esta fase.

---

## 14. Estado de pruebas

- **Suite completa:** `php artisan test` → **285 passed (908 assertions)**, 0 failures, 0 errors
  (resultado tras las correcciones de esta fase).
- El cambio de zona horaria destapó 1 test con dato incorrecto (`permite_transicion_valida_de_estado`),
  que enviaba un objeto Carbon como fecha en el JSON. **Corregido** con la representación real
  del contrato (`Y-m-d`). Suite verde confirmada.
- `php -l` sin errores en los ficheros modificados/creados.

---

## 15. Recomendaciones para hosting (futuro despliegue)

Comandos a ejecutar en el servidor (documentados, **no** ejecutados aquí):

```bash
php artisan config:cache     # cachea la configuración (entorno production)
php artisan route:cache      # cachea las rutas
php artisan view:cache       # cachea las vistas (si hubiera)
php artisan storage:link     # crea public/storage → storage/app/public
php artisan migrate --force  # aplica migraciones (solo cuando corresponda)
```

Variables de producción sugeridas (sin valores reales aquí):

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<dominio>
APP_TIMEZONE=America/Lima
CORS_ALLOWED_ORIGINS=https://<dominio-frontend>   # opcional
SANCTUM_TOKEN_EXPIRATION=<minutos>                # opcional
DB_DATABASE=<bd>
DB_USERNAME=<usuario>
DB_PASSWORD=<secreto>
APP_KEY=<clave generada en producción>
```

Requisitos: PHP `^8.2`, MySQL, escritura en `storage/`, directorio `storage/app/public`
con permisos para el usuario del servicio.

---

## 16. Riesgos pendientes (dependen del hosting / decisiones futuras)

1. **Rate limiting del login**: `POST /api/login` no tiene throttle. Para un API expuesto se
   recomienda limitar intentos (p. ej. `throttle` por IP) — no se implementó para no arriesgar
   la estabilidad de la suite ni introducir flakiness.
2. **Rate limiting global de la API**: el grupo `api` no usa `throttle:api`. Recomendación
   de producción (configurable en `bootstrap/app.php`).
3. **Expiración de tokens**: disponible por env (`SANCTUM_TOKEN_EXPIRATION`); queda decidir
   el valor y la estrategia de renovación desde Android.
4. **`public/storage`**: crear el enlace simbólico en el hosting si se desea servir PDFs por URL.
5. **Login sin throttle + fuerza bruta**: mitigar en el propio hosting (WAF/rate limit) o
   implementar throttle en una fase posterior.
6. **`/api/user` y `/api/logout`** con usuario desactivado tras login: aceptados por diseño
   (solo datos propios); considerar bloqueo si el negocio lo exige.
7. **Tiempo de vida de tokens en Android**: definir manejo de 401 → re-login en la app.

---

## 17. Correcciones aplicadas en esta fase (resumen)

| Archivo | Corrección |
|---------|------------|
| `config/app.php` | `'timezone' => env('APP_TIMEZONE', 'UTC')` — `APP_TIMEZONE=America/Lima` ahora se respeta (antes hardcodeado `UTC`) |
| `config/cors.php` | **Creado** — configuración CORS mínima segura para `api/*` |
| `config/sanctum.php` | `'expiration' => env('SANCTUM_TOKEN_EXPIRATION', null)` — expiración configurable sin cambiar el comportamiento actual |
| `tests/Feature/FiscalizacionTest.php` | `permite_transicion_valida_de_estado`: envía `fecha_diligencia` como `'Y-m-d'` (contrato real) en vez del objeto Carbon |
| `docs/FASE_6.4.8_AUDITORIA_FINAL_PRODUCCION.md` | Este documento |