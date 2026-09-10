# FASE 6.3 — Modelo de Datos del Sistema PRICE

**Proyecto:** API REST Laravel — Sistema PRICE  
**Fecha:** 2026-09-09  
**Estado:** ✅ Completada

---

## 1. Resumen ejecutivo

Se implementó el modelo de datos completo del sistema PRICE inspeccionando previamente las entidades Room de la aplicación Android para garantizar compatibilidad.

- **16 migraciones** ejecutadas en `price_api` (Batch 1, 2 y 3)
- **12 modelos Eloquent** creados/actualizados
- **2 seeders** de datos estructurales (productos + catálogo de incumplimientos)
- **5 factories** para testing
- **17/17 tests pasando** (11 nuevos de PRICE + 4 Sanctum + 2 Example)

---

## 2. Decisiones de diseño

### 2.1 `users` = `usuarios` del sistema PRICE

**Decisión:** Se extendió la tabla `users` de Laravel en lugar de crear una tabla `usuarios` separada.

**Justificación:**
- Laravel ya provee `users` con autenticación, hashing de passwords y Sanctum.
- Duplicar usuarios generaría problemas de sincronización y redundancia.
- Se agregaron los campos PRICE necesarios (`dni`, `rol`, `activo`) via migración adicional.

**Equivalencia Android:**

| Campo Android (`UsuarioEntity`) | Campo Laravel (`users`) |
|---|---|
| `id` | `id` |
| `usuario` | `email` (usado para login) |
| `nombre` + `apellido` | `name` |
| `dni` | `dni` ← nuevo |
| `rol` | `rol` ← nuevo |
| `token` | Tokens via `personal_access_tokens` (Sanctum) |

**Roles disponibles:** `ADMIN` \| `FISCALIZADOR` \| `CONSULTA`

### 2.2 snake_case en MySQL vs camelCase en Android Room

Room usa los nombres de campo Java directamente como columnas (camelCase). En MySQL se usa snake_case convencional de Laravel. La conversión es sistemática:

| Android (camelCase) | MySQL (snake_case) |
|---|---|
| `establecimientoId` | `establecimiento_id` |
| `fiscalizadorResponsableId` | `user_id` |
| `numeroExpediente` | `numero_expediente` |
| `fechaDiligencia` | `fecha_diligencia` |
| `codigoOsinergmin` | `codigo_osinergmin` |
| `razonSocial` | `razon_social` |
| `registroHidrocarburos` | `registro_hidrocarburos` |
| ... | ... |

### 2.3 Tipos de datos

| Tipo Android | Tipo MySQL | Notas |
|---|---|---|
| `Long` (ID) | `BIGINT UNSIGNED AUTO_INCREMENT` | Servidor genera los IDs |
| `String` (fecha) | `DATE` / `TIME` | Fechas tipadas correctamente |
| `Double` (precio) | `DECIMAL(10,4) UNSIGNED` | Evita imprecisión float; UNSIGNED = no negativos |
| `Double` (lat/lon) | `DECIMAL(10,7)` | 7 decimales para geolocalización |
| `Boolean` | `TINYINT(1)` | Cast automático en Eloquent |
| `String` (imagen_firma) | `LONGTEXT` | Base64 o path |

### 2.4 Estado de fiscalización

Android usa `String` libre para `estado`. Se tipó como `ENUM` en MySQL para garantizar integridad:

```
ENUM('BORRADOR', 'EN_PROCESO', 'FINALIZADA', 'ACTA_GENERADA')
```

Android asigna `"BORRADOR"` offline; los otros estados vienen de la API.

### 2.5 Desnormalización intencional en `fiscalizaciones`

Los campos `agente_fiscalizado`, `codigo_osinergmin`, `registro_hidrocarburos`, `direccion`, `distrito`, `provincia`, `departamento`, `ruc_dni`, `telefono_fax` son **copias del establecimiento al momento de la diligencia**. Este comportamiento fue verificado en `FiscalizacionRepository` Android y es intencional: el acta debe reflejar el estado del establecimiento en la fecha de la diligencia, no el estado actual.

### 2.6 `fiscalizacion_incumplimientos` como modelo propio

Esta tabla es Many-to-Many entre `fiscalizaciones` e `incumplimientos_catalogo`, pero se expone como modelo Eloquent propio (`FiscalizacionIncumplimiento`) en lugar de solo `pivot` porque:
1. Tiene campos propios: `seleccionado`, `observacion`.
2. `hechos_verificados` tiene FK directa a esta tabla.
3. Android borra y reinserta todos los registros al editar la selección (operación atómica).

### 2.7 Modelos que necesitan `$table` explícita

Laravel pluraliza en inglés. Los modelos en español necesitan declarar `$table`:

| Modelo | Sin `$table` (incorrecto) | Con `$table` (correcto) |
|---|---|---|
| `Fiscalizacion` | `fiscalizacions` | `fiscalizaciones` |
| `Verificacion` | `verificacions` | `verificaciones` |
| `Observacion` | `observacions` | `observaciones` |

---

## 3. Tablas del dominio PRICE

### 3.1 `users` (extendida)

Tabla base de Laravel + campos PRICE.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `name` | VARCHAR(255) | nombre completo |
| `email` | VARCHAR(255) UNIQUE | usado como `usuario` de login |
| `password` | VARCHAR(255) | hash bcrypt |
| `dni` | VARCHAR(20) NULL | agregado por PRICE |
| `rol` | ENUM('ADMIN','FISCALIZADOR','CONSULTA') | default: FISCALIZADOR |
| `activo` | TINYINT(1) | default: 1 |
| `email_verified_at` | TIMESTAMP NULL | |
| `remember_token` | VARCHAR(100) NULL | |
| `created_at` / `updated_at` | TIMESTAMP | |

### 3.2 `establecimientos`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `codigo_osinergmin` | VARCHAR(20) NULL | INDEX |
| `registro_hidrocarburos` | VARCHAR(50) NULL | |
| `razon_social` | VARCHAR(200) | NOT NULL |
| `nombre_comercial` | VARCHAR(200) NULL | |
| `ruc_dni` | VARCHAR(20) NULL | INDEX |
| `telefono` | VARCHAR(30) NULL | |
| `fax` | VARCHAR(30) NULL | |
| `direccion` | VARCHAR(300) NULL | |
| `distrito` | VARCHAR(100) NULL | |
| `provincia` | VARCHAR(100) NULL | |
| `departamento` | VARCHAR(100) NULL | |
| `latitud` | DECIMAL(10,7) NULL | |
| `longitud` | DECIMAL(10,7) NULL | |
| `activo` | TINYINT(1) | default: 1 |
| `created_at` / `updated_at` | TIMESTAMP | |

### 3.3 `fiscalizaciones`

**FK:** `establecimiento_id` → `establecimientos.id` CASCADE  
**FK:** `user_id` → `users.id` RESTRICT  
**INDEX:** `numero_expediente`, `estado`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `establecimiento_id` | BIGINT UNSIGNED | FK |
| `user_id` | BIGINT UNSIGNED | FK (fiscalizador responsable) |
| `numero_expediente` | VARCHAR(100) NULL | INDEX |
| `agente_fiscalizado` | VARCHAR(200) NULL | copia desnormalizada |
| `codigo_osinergmin` | VARCHAR(20) NULL | copia desnormalizada |
| `registro_hidrocarburos` | VARCHAR(50) NULL | copia desnormalizada |
| `fecha_diligencia` | DATE | NOT NULL |
| `hora_apertura` | TIME | NOT NULL |
| `hora_cierre` | TIME NULL | |
| `direccion` | VARCHAR(300) NULL | copia desnormalizada |
| `distrito` | VARCHAR(100) NULL | copia desnormalizada |
| `provincia` | VARCHAR(100) NULL | copia desnormalizada |
| `departamento` | VARCHAR(100) NULL | copia desnormalizada |
| `ruc_dni` | VARCHAR(20) NULL | copia desnormalizada |
| `telefono_fax` | VARCHAR(50) NULL | copia desnormalizada |
| `estado` | ENUM | BORRADOR\|EN_PROCESO\|FINALIZADA\|ACTA_GENERADA |
| `created_at` / `updated_at` | TIMESTAMP | |

### 3.4 `productos`

IDs 1-11 fijos (hardcodeados en Android).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | IDs 1-11 reservados |
| `nombre` | VARCHAR(100) | |
| `categoria` | ENUM | Líquidos\|GLP\|Envasado\|Otros |
| `unidad` | VARCHAR(20) | Galón, Litro, 3 kg … 45 kg, N/A |
| `activo` | TINYINT(1) | |
| `created_at` / `updated_at` | TIMESTAMP | |

### 3.5 `precios`

**FK:** `fiscalizacion_id` → `fiscalizaciones.id` CASCADE  
**FK:** `producto_id` → `productos.id` RESTRICT  
**UNIQUE:** (`fiscalizacion_id`, `producto_id`)

| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `fiscalizacion_id` | BIGINT UNSIGNED | FK |
| `producto_id` | BIGINT UNSIGNED | FK |
| `precio_price` | DECIMAL(10,4) UNSIGNED NULL | sistema PRICE |
| `precio_publicado` | DECIMAL(10,4) UNSIGNED NULL | letrero del local |
| `precio_surtidor` | DECIMAL(10,4) UNSIGNED NULL | surtidor físico |
| `precio_descuento` | DECIMAL(10,4) UNSIGNED NULL | precio con descuento |
| `tiene_descuento` | TINYINT(1) | default: 0 |
| `observacion` | TEXT NULL | |
| `created_at` / `updated_at` | TIMESTAMP | |

### 3.6 `verificaciones`

Relación 1-a-1 con `fiscalizaciones`.  
**FK:** `fiscalizacion_id` → `fiscalizaciones.id` CASCADE UNIQUE

| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `fiscalizacion_id` | BIGINT UNSIGNED UNIQUE | FK |
| `telefono_publicado` | VARCHAR(50) NULL | |
| `telefono_actualizado_price` | VARCHAR(50) NULL | |
| `horario_publicado` | VARCHAR(200) NULL | |
| `observaciones` | TEXT NULL | |
| `created_at` / `updated_at` | TIMESTAMP | |

### 3.7 `incumplimientos_catalogo`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | IDs 1-6 reservados |
| `codigo` | VARCHAR(10) UNIQUE | I-01 … I-06 |
| `descripcion` | TEXT | |
| `base_legal` | VARCHAR(300) NULL | pendiente confirmación institucional |
| `activo` | TINYINT(1) | |
| `created_at` / `updated_at` | TIMESTAMP | |

### 3.8 `fiscalizacion_incumplimientos`

Tabla pivote + modelo propio.  
**FK:** `fiscalizacion_id` → `fiscalizaciones.id` CASCADE  
**FK:** `incumplimiento_catalogo_id` → `incumplimientos_catalogo.id` RESTRICT  
**UNIQUE:** `fi_unique_fisc_incump` (`fiscalizacion_id`, `incumplimiento_catalogo_id`)

| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `fiscalizacion_id` | BIGINT UNSIGNED | FK |
| `incumplimiento_catalogo_id` | BIGINT UNSIGNED | FK |
| `seleccionado` | TINYINT(1) | default: 1 |
| `observacion` | TEXT NULL | |
| `created_at` / `updated_at` | TIMESTAMP | |

### 3.9 `hechos_verificados`

**FK:** `fiscalizacion_id` → `fiscalizaciones.id` CASCADE  
**FK:** `fiscalizacion_incumplimiento_id` → `fiscalizacion_incumplimientos.id` CASCADE  
**FK:** `user_id` → `users.id` SET NULL

| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `fiscalizacion_id` | BIGINT UNSIGNED | FK INDEX |
| `fiscalizacion_incumplimiento_id` | BIGINT UNSIGNED | FK INDEX |
| `user_id` | BIGINT UNSIGNED NULL | FK (quién registró) |
| `descripcion` | TEXT | NOT NULL |
| `fecha_registro` | DATE NULL | |
| `created_at` / `updated_at` | TIMESTAMP | |

### 3.10 `observaciones`

Relación 1-a-1 con `fiscalizaciones`.  
**FK:** `fiscalizacion_id` → `fiscalizaciones.id` CASCADE UNIQUE

| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `fiscalizacion_id` | BIGINT UNSIGNED UNIQUE | FK |
| `otras_ocurrencias` | TEXT NULL | |
| `documentacion_recabada` | TEXT NULL | |
| `manifestaciones_agente` | TEXT NULL | |
| `negativa_identificacion` | TINYINT(1) | default: 0 |
| `negativa_suscripcion` | TINYINT(1) | default: 0 |
| `negativa_recepcion` | TINYINT(1) | default: 0 |
| `observaciones_generales` | TEXT NULL | |
| `created_at` / `updated_at` | TIMESTAMP | |

### 3.11 `firmas`

**FK:** `fiscalizacion_id` → `fiscalizaciones.id` CASCADE

| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `fiscalizacion_id` | BIGINT UNSIGNED | FK INDEX |
| `tipo_firma` | ENUM('FISCALIZADOR','AGENTE') | |
| `nombre_completo` | VARCHAR(200) NULL | |
| `dni` | VARCHAR(20) NULL | |
| `relacion_agente` | VARCHAR(100) NULL | |
| `imagen_firma` | LONGTEXT NULL | base64 o path |
| `fecha_firma` | DATE NULL | |
| `created_at` / `updated_at` | TIMESTAMP | |

### 3.12 `documentos`

Relación 1-a-1 con `fiscalizaciones`.  
**FK:** `fiscalizacion_id` → `fiscalizaciones.id` CASCADE UNIQUE

| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `fiscalizacion_id` | BIGINT UNSIGNED UNIQUE | FK |
| `nombre_archivo` | VARCHAR(300) NULL | |
| `ruta_archivo` | VARCHAR(500) NULL | |
| `fecha_generacion` | DATETIME NULL | |
| `numero_paginas` | INT UNSIGNED NULL | |
| `estado` | VARCHAR(50) NULL | |
| `created_at` / `updated_at` | TIMESTAMP | |

---

## 4. Diagrama de relaciones

```
users (1) ─────────────────────────────────── (N) fiscalizaciones
                                                        │
establecimientos (1) ──────────────────────── (N) fiscalizaciones
                                                        │
                    ┌───────────────────────────────────┼───────────────────────────────┐
                    │                                   │                               │
              (1) verificaciones              (N) precios ◄─── (1) productos           │
              (1) observaciones               (N) fiscalizacion_incumplimientos          │
              (N) firmas                              │    ◄─── (1) incumplimientos_catalogo
              (1) documentos                  (N) hechos_verificados
                                                   │
                                              (1) users (quién registró)
```

---

## 5. Relaciones Eloquent implementadas

| Modelo | Relación | Modelo relacionado |
|---|---|---|
| `User` | `hasMany` | `Fiscalizacion` |
| `User` | `hasMany` | `HechoVerificado` |
| `Establecimiento` | `hasMany` | `Fiscalizacion` |
| `Fiscalizacion` | `belongsTo` | `User` |
| `Fiscalizacion` | `belongsTo` | `Establecimiento` |
| `Fiscalizacion` | `hasMany` | `Precio` |
| `Fiscalizacion` | `hasOne` | `Verificacion` |
| `Fiscalizacion` | `hasOne` | `Observacion` |
| `Fiscalizacion` | `hasMany` | `Firma` |
| `Fiscalizacion` | `hasOne` | `Documento` |
| `Fiscalizacion` | `hasMany` | `FiscalizacionIncumplimiento` |
| `Fiscalizacion` | `belongsToMany` | `IncumplimientoCatalogo` (via `fiscalizacion_incumplimientos`) |
| `Fiscalizacion` | `hasMany` | `HechoVerificado` |
| `Producto` | `hasMany` | `Precio` |
| `Precio` | `belongsTo` | `Fiscalizacion` |
| `Precio` | `belongsTo` | `Producto` |
| `Verificacion` | `belongsTo` | `Fiscalizacion` |
| `Observacion` | `belongsTo` | `Fiscalizacion` |
| `Firma` | `belongsTo` | `Fiscalizacion` |
| `Documento` | `belongsTo` | `Fiscalizacion` |
| `FiscalizacionIncumplimiento` | `belongsTo` | `Fiscalizacion` |
| `FiscalizacionIncumplimiento` | `belongsTo` | `IncumplimientoCatalogo` |
| `FiscalizacionIncumplimiento` | `hasMany` | `HechoVerificado` |
| `IncumplimientoCatalogo` | `hasMany` | `FiscalizacionIncumplimiento` |
| `IncumplimientoCatalogo` | `belongsToMany` | `Fiscalizacion` |
| `HechoVerificado` | `belongsTo` | `Fiscalizacion` |
| `HechoVerificado` | `belongsTo` | `FiscalizacionIncumplimiento` |
| `HechoVerificado` | `belongsTo` | `User` |

---

## 6. Catálogos de datos estructurales

### 6.1 Productos (IDs fijos 1-11 — NO modificar)

| ID | Nombre | Categoría | Unidad |
|---|---|---|---|
| 1 | Diesel B5 / B5 S-50 | Líquidos | Galón |
| 2 | G-84 / Gasohol 84 Plus | Líquidos | Galón |
| 3 | Regular / Gasohol Regular | Líquidos | Galón |
| 4 | Premium / Gasohol Premium | Líquidos | Galón |
| 5 | GLP Automotor | GLP | Litro |
| 6 | Otro / Marca | Otros | N/A |
| 7 | GLP cilindro 3 kg | Envasado | 3 kg |
| 8 | GLP cilindro 5 kg | Envasado | 5 kg |
| 9 | GLP cilindro 10 kg | Envasado | 10 kg |
| 10 | GLP cilindro 15 kg | Envasado | 15 kg |
| 11 | GLP cilindro 45 kg | Envasado | 45 kg |

> ⚠️ Los IDs 1-11 están hardcodeados en `PrecioRepository.initializeCatalog()` del cliente Android. No cambiar.

### 6.2 Incumplimientos catálogo (IDs fijos 1-6)

| ID | Código | Descripción resumida |
|---|---|---|
| 1 | I-01 | Precios no registrados/actualizados en el PRICE |
| 2 | I-02 | No exhibe lista de precios vigente |
| 3 | I-03 | Ubicación y/o teléfono no registrado/actualizado |
| 4 | I-04 | No exhibe horario de atención y/o teléfono visiblemente |
| 5 | I-05 | No utiliza el galón como unidad de medida |
| 6 | I-06 | No cuenta con rótulo visible de precio por galón |

> ⚠️ Los textos de `base_legal` son placeholders. Actualizar con referencias normativas reales de Osinergmin.

---

## 7. Seeders y Factories

### Seeders (datos estructurales — idempotentes via `updateOrInsert`)

| Seeder | Descripción |
|---|---|
| `ProductoSeeder` | 11 combustibles con IDs fijos 1-11 |
| `IncumplimientoCatalogoSeeder` | 6 incumplimientos I-01 a I-06 |

Ejecutar: `php artisan db:seed`

### Factories (para testing — NO usar en producción)

| Factory | Estados adicionales |
|---|---|
| `UserFactory` | `admin()`, `consulta()`, `inactivo()` |
| `EstablecimientoFactory` | `inactivo()` |
| `FiscalizacionFactory` | `enProceso()`, `finalizada()`, `actaGenerada()` |
| `ProductoFactory` | — |
| `PrecioFactory` | `conDescuento()` |

---

## 8. Archivos de migración

| Archivo | Tabla | Batch |
|---|---|---|
| `0001_01_01_000000_create_users_table` | `users` | 1 |
| `0001_01_01_000001_create_cache_table` | `cache`, `cache_locks` | 1 |
| `0001_01_01_000002_create_jobs_table` | `jobs`, `job_batches`, `failed_jobs` | 1 |
| `2026_09_09_031303_create_personal_access_tokens_table` | `personal_access_tokens` | 1 |
| `2026_09_09_100000_alter_users_add_price_fields` | `users` (ALTER) | 2 |
| `2026_09_09_100001_create_establecimientos_table` | `establecimientos` | 2 |
| `2026_09_09_100002_create_fiscalizaciones_table` | `fiscalizaciones` | 2 |
| `2026_09_09_100003_create_productos_table` | `productos` | 2 |
| `2026_09_09_100004_create_precios_table` | `precios` | 2 |
| `2026_09_09_100005_create_verificaciones_table` | `verificaciones` | 2 |
| `2026_09_09_100006_create_incumplimientos_catalogo_table` | `incumplimientos_catalogo` | 2 |
| `2026_09_09_100007_create_fiscalizacion_incumplimientos_table` | `fiscalizacion_incumplimientos` | 3 |
| `2026_09_09_100008_create_hechos_verificados_table` | `hechos_verificados` | 3 |
| `2026_09_09_100009_create_observaciones_table` | `observaciones` | 3 |
| `2026_09_09_100010_create_firmas_table` | `firmas` | 3 |
| `2026_09_09_100011_create_documentos_table` | `documentos` | 3 |

---

## 9. Tests implementados

Archivo: `tests/Feature/PriceModelTest.php`  
Suite: Feature | BD: `price_api_test` | Estrategia: `RefreshDatabase`

| # | Test | Verifica |
|---|---|---|
| 1 | `se_puede_crear_un_establecimiento` | CRUD básico + campo `activo` |
| 2 | `se_puede_crear_una_fiscalizacion_con_usuario_y_establecimiento` | Relaciones BelongsTo |
| 3 | `se_pueden_registrar_productos_y_precios` | FK + relaciones + HasMany |
| 4 | `no_se_permiten_precios_negativos` | DECIMAL UNSIGNED en BD |
| 5 | `se_puede_registrar_una_verificacion` | HasOne desde fiscalización |
| 6 | `se_puede_registrar_un_incumplimiento` | Tabla pivote + BelongsToMany |
| 7 | `se_pueden_registrar_hechos_verificados` | FK a FiscalizacionIncumplimiento |
| 8 | `las_relaciones_eloquent_funcionan_correctamente` | Eager loading completo |
| 9 | `no_se_puede_crear_fiscalizacion_con_referencias_inexistentes` | Integridad referencial |
| 10 | `los_estados_de_fiscalizacion_funcionan_correctamente` | ENUM + transiciones de estado |
| 11 | `al_borrar_fiscalizacion_se_eliminan_en_cascada_sus_hijos` | CASCADE DELETE en cadena |

**Resultado:** `OK (17 tests, 44 assertions)` — incluye tests de fases anteriores.

---

## 10. Resultados de validaciones finales

### `php artisan migrate:status`
```
16 migraciones — todas en estado [Ran]
Batch 1: 4 migraciones base Laravel/Sanctum
Batch 2: 8 migraciones PRICE (users ALTER + 7 tablas nuevas)
Batch 3: 5 migraciones PRICE (tablas restantes)
```

### `php artisan route:list`
```
6 rutas activas:
  GET|HEAD  /
  GET|HEAD  api/user          ← protegida por auth:sanctum
  GET|HEAD  sanctum/csrf-cookie
  GET|HEAD  storage/{path}
  PUT       storage/{path}
  GET|HEAD  up
```

### `phpunit`
```
PHPUnit 11.5.56 — PHP 8.2.30
OK (17 tests, 44 assertions)
Time: 00:02.449
```

---

## 11. Problemas encontrados y soluciones

### 11.1 Tabla `fiscalizacion_incumplimientos` huérfana

**Problema:** La tabla existía físicamente en `price_api` creada por una sesión anterior, pero sin registro en la tabla `migrations`. Causó error `Table already exists` al ejecutar la migración.

**Solución:** Se verificó que la tabla estaba vacía (0 filas) y se eliminó con `DROP TABLE` para que la migración la recreara correctamente con FK y unique constraints.

### 11.2 Nombre de índice demasiado largo

**Problema:** MySQL tiene límite de 64 caracteres para nombres de objetos. El nombre autogenerado `fiscalizacion_incumplimientos_fiscalizacion_id_incumplimiento_catalogo_id_unique` supera ese límite.

**Solución:** Se especificó nombre explícito corto en la migración:
```php
$table->unique(['fiscalizacion_id', 'incumplimiento_catalogo_id'], 'fi_unique_fisc_incump');
```

### 11.3 Pluralización inglesa de modelos en español

**Problema:** Laravel pluraliza `Fiscalizacion` → `fiscalizacions`, `Verificacion` → `verificacions`, `Observacion` → `observacions` en lugar de `fiscalizaciones`, `verificaciones`, `observaciones`.

**Solución:** Se declaró `$table` explícita en los tres modelos afectados.

---

## 12. Compatibilidad Android ↔ Laravel

| Aspecto | Android (Room) | Laravel (MySQL) | Compatibilidad |
|---|---|---|---|
| Generación de IDs | App asigna IDs manualmente o recibe de API | AUTO_INCREMENT en servidor | ✅ API devuelve ID real tras POST |
| Nombres de columna | camelCase (sin @ColumnInfo) | snake_case | ✅ Conversión sistemática documentada |
| Tipos de fecha | String `"yyyy-MM-dd"` | DATE/TIME/DATETIME | ✅ API devuelve formato ISO compatible |
| Booleanos | `Boolean` Java | TINYINT(1) | ✅ Transparente via cast Eloquent |
| Precios | `Double` Java | DECIMAL(10,4) UNSIGNED | ✅ Mayor precisión en servidor |
| Estado fiscalización | String libre | ENUM | ✅ API valida; Android recibe string |
| IDs de productos | Hardcoded 1-11 | IDs 1-11 sembrados | ✅ Seeder garantiza IDs fijos |
| IDs de incumplimientos | Hardcoded 1-6 | IDs 1-6 sembrados | ✅ Seeder garantiza IDs fijos |
| Base URL Android | `http://10.0.2.2:8000/` | `localhost:8000` (Laragon) | ✅ 10.0.2.2 = host desde emulador |

---

## 13. Pendiente para FASE 6.4

1. **Endpoints REST completos:**
   - `POST /api/login` — autenticación y retorno de token Sanctum
   - CRUD de establecimientos
   - CRUD de fiscalizaciones
   - Registro de precios, verificaciones, incumplimientos, hechos, observaciones, firmas
   - `GET /api/fiscalizaciones/{id}/acta/pdf` — generación del PDF del acta

2. **Middleware de autenticación** para todas las rutas protegidas.

3. **Validación de requests** (FormRequests) para cada endpoint.

4. **Textos reales de `base_legal`** en `incumplimientos_catalogo` — requiere confirmación institucional de Osinergmin.

5. **Lógica de sincronización offline** — la app Android guarda con `estado = BORRADOR` cuando no hay red; la API debe soportar `PATCH /api/fiscalizaciones/{id}` para actualizar el estado y sincronizar.

6. **Generación de PDF** del acta de fiscalización.

7. **Fotografías y geolocalización** — pendiente de análisis de requisitos.
