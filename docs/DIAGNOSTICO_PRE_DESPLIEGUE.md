# DIAGNÓSTICO PRE-DESPLIEGUE — API REST PRICE

> Auditoría técnica para migrar el proyecto de **Laragon (Windows, localhost)** a un
> **hosting Linux real con PHP + MySQL + HTTPS + dominio público**.
>
> Alcance: **solo auditoría y documentación**. No se modificó código de la aplicación.

---

## 1. Resumen ejecutivo

El proyecto **está preparado para desplegarse en Linux** con modificaciones **mínimas de
configuración (variables de entorno)**. No hay dependencias hardcodeadas de Laragon/Windows
en el código versionado; el almacenamiento usa rutas relativas (sin `C:\...`); el PDF se
genera con código PHP puro (sin binarios del sistema); y las migraciones son MySQL estándar.

**Bloqueadores reales para desplegar:** ninguno de código. Los requisitos son:

1. **Hosting con PHP 8.2+** y extensiones `mbstring`, `iconv`, `pdo_mysql` (ver §4).
2. **MySQL 8 / MariaDB 10.4+** (compatible con las migraciones).
3. **Composer** en el servidor (o subir `vendor/` compilado).
4. Configurar `.env` de producción (§7): `APP_ENV=production`, `APP_DEBUG=false`,
   `APP_URL=https://api.<dominio>.me`, credenciales DB, `APP_KEY` nueva.
5. `php artisan migrate` + seed **solo de catálogos** (productos e incumplimientos).
   **NO usar `UserSeeder` en producción** (contiene credenciales de prueba conocidas).
6. Apuntar el servidor web a `public/` y servir HTTPS (Let's Encrypt).
7. `php artisan key:generate` **NO** debe conservar la `APP_KEY` de local en producción.

**Estado de pruebas:** 285 tests / 908 assertions / 0 failures / 0 errors (idéntico al
baseline 6.4.8).

---

## 2. Estado actual

- Laravel Framework `v12.69.2` (composer.lock), PHP `8.2.30` local, Sanctum `^4.0`,
  PHPUnit `11.5.50`, MySQL local vía `pdo_mysql`.
- 17 migraciones ejecutadas, 0 pendientes (`migrate:status`).
- 48 rutas API (`route:list`), todas con contrato verificado.
- CRUDs: Establecimientos, Fiscalizaciones (+ PATCH 6.4.7), Precios, Verificaciones,
  Incumplimientos, Hechos, Observaciones, Firmas, Documentos, Acta PDF, búsquedas 6.4.6.
- Configuración de producción ya preparada en fases previas: timezone por env (6.4.8),
  CORS configurable (6.4.8), expiración de tokens por env (6.4.8).

---

## 3. Requisitos mínimos del servidor

| Recurso | Mínimo sugerido |
|---------|-----------------|
| Sistema | Linux (Ubuntu 22.04/24.04 LTS o similar) |
| PHP | **8.2.0 o superior** (requisito de `laravel/framework`) |
| Web server | Apache o Nginx apuntando a `public/` (ver §8) |
| Base de datos | MySQL **8.0+** o MariaDB **10.4+** (charset `utf8mb4`) |
| Composer | 2.x (para `composer install --no-dev`) |
| Almacenamiento | Disco escribible en `storage/` (PDFs) |
| RAM/CPU | 512 MB RAM / 1 vCPU suficientes para uso académico |
| Node.js | **NO necesario** (API pura, sin assets Vite compilados) |

---

## 4. Dependencias PHP

**Extensiones exigidas por `laravel/framework` (composer.json):**

| Extensión | Motivo |
|-----------|--------|
| `ext-ctype` | Núcleo PHP |
| `ext-filter` | Núcleo PHP |
| `ext-hash` | Hashing |
| `ext-mbstring` | **Crítico**: usado por `ActaFiscalizacionPdfService` (`mb_strlen`/`mb_substr`) |
| `ext-openssl` | Criptografía/tokens |
| `ext-session` | Sanciones/sesiones |
| `ext-tokenizer` | Núcleo PHP 8.2 |

**Extensiones usadas por el código de la aplicación:**

| Extensión | Dónde se usa |
|-----------|--------------|
| `iconv` | `MinimalPdf` (transliteración UTF-8 → ASCII para el PDF) |
| `pdo_mysql` | Conexiones a BD (`config/database.php`) |
| `pdo` | Base de datos |
| `filesystem`/`fileinfo` | (Recomendada) disco local de Flysystem |
| `gd` / `zip` / `zlib` | (Opcional) solo para tests con imágenes / Phar |

> En PHP oficial de Linux (packages `php8.2-*` de sury o del distro) todas están incluidas;
> en hosting compartido verificar `mbstring`, `iconv` y `pdo_mysql` explícitamente.

---

## 5. Dependencias MySQL

- Migraciones usan tipos estándar (`foreignId()->constrained(...)`, `enum`, `longText`,
  `unsignedInteger`, `datetime`, `timestamps`) → compatibles MySQL 5.7+/8.0+ y
  MariaDB **10.4+**. Recomendado MySQL 8.0+ o MariaDB 10.4+ para `utf8mb4_unicode_ci`.
- `config/database.php` → driver `mysql`, charset `utf8mb4`, collation
  `utf8mb4_unicode_ci`, `strict => true`, FK constraints activas.
- `php artisan migrate --force` debería funcionar en una BD limpia (todas las migraciones
  están en orden y ya verificadas en local/test).
- No hay soft deletes (borrado físico en cascada, según diseño de fases anteriores).

---

## 6. Dependencias del sistema

- **Ninguna binaria externa.** El PDF se genera con `MinimalPdf` (solo PHP, fuente base
  Helvetica no embeber, streams sin comprimir) → **no requiere** `pdftk`, `ghostscript`,
  `wkhtmltopdf` ni `libreoffice`.
- No se requiere `cron` para funcionalidad actual (`QUEUE_CONNECTION=sync`; no hay jobs
  programados de negocio).
- No se requiere `Redis`/`Memcached` (cache/sesión en archivos).
---

## 7. Variables `.env` necesarias en producción

Basado en `.env.example` (seguro: **sin secretos reales**, APP_KEY vacía) y `config/*.php`
que usan `env()` con fallback (sin valores hardcodeados que bloqueen producción).

| Variable | Valor local | Producción (cambiar) | Obligatoria |
|----------|-------------|----------------------|-------------|
| `APP_ENV` | `local` | `production` | Sí |
| `APP_DEBUG` | `true` | `false` | Sí |
| `APP_URL` | `http://localhost:8000` | `https://api.<dominio>.me` | Sí |
| `APP_KEY` | base64 local | `php artisan key:generate` (nueva) | Sí |
| `APP_TIMEZONE` | `America/Lima` | `America/Lima` | Recomendada |
| `DB_CONNECTION` | `mysql` | `mysql` | Sí |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / 3306 | host real / 3306 | Sí |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | `price_api` / root / vacío | credenciales reales | Sí |
| `CORS_ALLOWED_ORIGINS` | ausente → `*` | dominio(s) si hay web | Opcional |
| `SANCTUM_TOKEN_EXPIRATION` | ausente → `null` | minutos (p. ej. 10080) | Opcional |
| `SANCTUM_TOKEN_PREFIX` | vacío | p. ej. `Bearer ` | Opcional |
| `FILESYSTEM_DISK` | `local` | `local` (el PDF usa `disk('public')` explícito) | No |
| `LOG_CHANNEL` | `stack` | `daily` recomendado | Opcional |
| `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` | file/file/sync | válidos para hosting pequeño | No |

> **Gap documentado:** `.env.example` aún no incluye `CORS_ALLOWED_ORIGINS` ni
> `SANCTUM_TOKEN_EXPIRATION` (introducidas en 6.4.8). Recomendación: añadirlas como
> comentario al template en una fase futura (no crítico).

---

## 8. Diagnóstico de dependencias Laragon/Windows

Resultado de `git grep` sobre el **código versionado** (`.env` NO está versionado):

- **Ninguna ruta `C:\laragon`, `C:\www\`, unidades de disco ni rutas Windows en el código.**
- `public/index.php`, `bootstrap/app.php` y `config/*.php` usan `__DIR__`, `storage_path()`,
  `public_path()`, `database_path()` → **100% portable**.
- Las únicas apariciones de `localhost/127.0.0.1` en ficheros versionados son defaults de
  configuración (sustituibles por env) y `phpunit.xml` (solo tests locales):
  - `config/sanctum.php` → `SANCTUM_STATEFUL_DOMAINS` solo afecta SPA con cookies; Android
    usa Bearer token, por lo que es irrelevante.
- **No hay dependencias de Apache/Nginx de Laragon**: `public/.htaccess` es el estándar
  Laravel. Para Nginx se replican pocas líneas (ver §18).

**Conclusión:** el proyecto puede ejecutarse en Linux **sin modificaciones de código**.

---

## 9. Compatibilidad Linux (filesystem)

- `storage/` versionado **solo con `.gitignore`** → en el servidor se crea al escribir.
- Directorios que deben ser **escribibles**:
  - `storage/app/public/` (actas PDF) — ruta relativa `actas/<archivo>.pdf`
  - `storage/framework/cache/data/`, `storage/framework/sessions/`, `storage/framework/views/`
  - `storage/logs/` (log Laravel) y `bootstrap/cache/` (primer arranque)
- Nombres de PDF: generados con `preg_replace` a `[A-Za-z0-9-_]` → **seguros en filesystems
  case-sensitive** (Linux).
- La BD almacena rutas **relativas** (`actas/...`), nunca absolutas de Windows ✓.
- `php artisan storage:link` → `public/storage → storage/app/public`. **No obligatorio**:
  el endpoint sirve el PDF por bytes (`application/pdf`), pero recomendable para URL pública.
---

## 10. PDF / actas

- **Generación:** `MinimalPdf` (PHP puro, PDF 1.4, fuente base Helvetica, streams sin
  comprimir). **No requiere paquetes del sistema → compatible Linux.** (No sustituir por Dompdf.)
- **Almacenamiento:** `Storage::disk('public')->put('actas/...')` → `storage/app/public`.
- **Idempotencia:** `Documento::updateOrCreate(['fiscalizacion_id' => ...])` — regenerar el
  acta actualiza el Documento 1:1 (elimina el archivo previo si cambió el nombre).
- **Número de páginas:** `pdf->pageCount()` → `numero_paginas`.
- **Respuesta:** `application/pdf` + `Content-Disposition` + `Content-Length` +
  `X-Acta-Pages`/`X-Acta-Documento-Id`; 404 si no existe la fiscalización; 500 JSON genérico
  (sin stack trace) ante excepción. Todo validado por tests.

---

## 11. Sanctum / HTTPS

- **API 100% token-based (Bearer)** → **no necesita** cookies ni modo stateful para Android.
- Flujo: `POST /api/login` (DNI+password) → `{ token, user }`; rutas protegidas con
  `auth:sanctum` (valida el Bearer contra `personal_access_tokens`).
- `config/sanctum.php`:
  - `stateful` (cookies) solo para `localhost/127.0.0.1` por defecto → irrelevante para
    Android; configurable en prod.
  - `expiration => env('SANCTUM_TOKEN_EXPIRATION', null)` → **null = tokens sin expirar**.
    Recomendado fijar expiración en producción y manejar 401→re-login en Android.
  - `token_prefix` por env.
- **HTTPS:** el dominio debe servir TLS (Let's Encrypt). En `.env` fijar
  `APP_URL=https://api.<dominio>.me`. Laravel funciona tras proxy/TLS sin cambios;
  para Nginx se recomienda `X-Forwarded-Proto` (ver §18).

---

## 12. CORS

- `config/cors.php` (6.4.8): `paths=api/*`, `allowed_methods=*`, `allowed_origins` por env
  (`CORS_ALLOWED_ORIGINS`, default `*`), `allowed_headers=*`, `exposed_headers=X-Acta-Pages /
  X-Acta-Documento-Id`, `supports_credentials=false`.
- **Android nativo NO aplica CORS** (no es navegador) → esta configuración no afecta a la app.
- `supports_credentials=false` es **correcto** para API de tokens (sin cookies).
- Para un futuro frontend web: fijar `CORS_ALLOWED_ORIGINS` al dominio real.
- `*` por defecto es aceptable para Android/Postman, pero conviene restringirlo si la API
  se expone a navegadores.

---

## 13. Seguridad básica

| Nivel | Hallazgo |
|-------|----------|
| **CRÍTICO** | Ninguno detectado en código. |
| **ALTO** | `POST /api/login` público **sin rate limiting** (riesgo de fuerza bruta). `APP_DEBUG=true` en `.env` local (debe ser `false` en prod). |
| **MEDIO** | `CORS_ALLOWED_ORIGINS=*` por defecto. Token sin expiración por defecto. `UserSeeder` con credenciales de prueba conocidas (no ejecutar en prod). |
| **BAJO** | `/api/user` y `/api/logout` solo exigen `auth:sanctum` (sin chequeo adicional de rol/activo; el resto de rutas sí). Respuestas de error genéricas (sin stack traces con `APP_DEBUG=false`). |

- No hay llaves/secretos hardcodeados; `.env` no versionado; `formatUser()` no expone
  password/remember_token; modelos usan `$fillable` restringido; FKs y 1:1 validadas en Requests.
---

## 14. Rate limiting

| Aspecto | Situación actual |
|---------|------------------|
| Login | **Sin throttle.** `POST /api/login` acepta N intentos por IP (riesgo fuerza bruta). |
| API general | **Sin throttle.** El middleware `throttle` no está aplicado a rutas `api/*` ni en `bootstrap/app.php`. |
| Prioridad | **MEDIA-ALTA.** Recomendable antes de exponer el login público: `throttle` por IP en login (p. ej. 5/60s) y límite razonable global (p. ej. 60-120 req/min). No implementado en esta auditoría (fuera de alcance, requiere tests). |

> Riesgo real solo si la URL es pública y conocida; para uso académico/acotado es bajo, pero
> se recomienda como mejora de producción.

---

## 15. Git / GitHub

- `.gitignore` adecuado: excluye `.env`, `.env.backup`, `.env.production`, `/vendor`,
  `/public/build`, `/public/hot`, `/public/storage`, `/storage/*.key`, `/storage/pail`,
  logs, `.phpunit.result.cache`, IDE, `.DS_Store`, `node_modules`, `auth.json`.
- `git ls-files .env` → **vacío** (`.env` NO versionado). `.env.example` versionado y **sin
  secretos reales** (APP_KEY vacía, DB_PASSWORD vacío).
- `git ls-files storage` → solo `.gitignore` (no se versionan PDFs ni cachés).
- Comprobado `git grep` → sin dlugunas rutas absolutas Windows ni dependencias Laragon.
- `git status --short` muestra cambios **sin commitear** de las fases 6.4.6/6.4.7/6.4.8
  (controllers, requests, routes, tests, config, docs). **Antes de desplegar se debe
  commitear y subir a GitHub** para poder `git clone` en el servidor.

---

## 16. Migraciones / seeders

**A) Estructura reproducible (100%):** las 17 migraciones crean toda la BD (incluyendo
`users`, `personal_access_tokens`, catálogos, FKs, enums, unique). `php artisan migrate`
en BD vacía genera el mismo esquema que local.

**B) Datos de catálogo (requeridos para la app):**
- `ProductoSeeder`: 11 productos con **IDs fijos 1-11** (hardcodeados en el catálogo Android:
  `PrecioRepository.initializeCatalog`). **Obligatorio** cargarlo.
- `IncumplimientoCatalogoSeeder`: 6 incumplimientos **códigos I-01…I-06** (hardcodeados en
  Android: `IncumplimientoRepository.initializeCatalog`). **Obligatorio** cargarlo.

**C) Datos de prueba / NO cargar en producción:**
- `UserSeeder`: crea usuarios con **credenciales conocidas de prueba**
  (`00000001/Admin1234!`, etc.). **NO ejecutarlo en producción** (o cambiar contraseñas
  inmediatamente). En producción crear el/los usuario(s) real(es) con `Hash::make()`.
- Establecimientos y fiscalizaciones son datos operativos (se crean vía API, no seed).

**Comando sugerido para catálogos únicamente:**
```bash
php artisan db:seed --class=ProductoSeeder
php artisan db:seed --class=IncumplimientoCatalogoSeeder
```
(Usar `updateOrInsert` → idempotente y seguro.)

---

## 17. Procedimiento de despliegue propuesto

```bash
# 1. Preparar el código
git clone <repo> /var/www/api-price && cd /var/www/api-price
composer install --no-dev --no-interaction

# 2. Entorno
cp .env.example .env
# ... editar .env (APP_ENV=production, APP_DEBUG=false, APP_URL=https..., DB_*, APP_KEY vacío)

# 3. Clave y migraciones
php artisan key:generate          # crea APP_KEY (NO conservar la local)
php artisan migrate --force       # o php artisan migrate si es interactivo
php artisan db:seed --class=ProductoSeeder
php artisan db:seed --class=IncumplimientoCatalogoSeeder
php artisan storage:link          # opcional: public/storage -> storage/app/public

# 4. Caché opcional (mejora rendimiento)
php artisan config:cache          # bakes config
php artisan route:cache           # bakes routes (requiere SerializableClosure ✓ ya instalado)
php artisan view:cache            # compila Blade (welcome)

# 5. Servidor web (nginx/apache)
#   - document root: /var/www/api-price/public
#   - PHP-FPM 8.2
#   - HTTPS (Let's Encrypt)
#   - Nginx: pasar X-Forwarded-Proto + reenvío de Authorization

# 6. Verificación
curl -k https://api.<dominio>.me/api/login -X POST -H 'Content-Type: application/json' \
     -d '{"dni":"...","password":"..."}'
```
---

## 18. Checklist pre-despliegue

- [ ] PHP 8.2+ con `mbstring`, `iconv`, `pdo_mysql`, `openssl`, `session`, `tokenizer`.
- [ ] MySQL 8+/MariaDB 10.4+ creada (charset `utf8mb4`, collation `utf8mb4_unicode_ci`).
- [ ] Composer instalado (o `vendor/` precompilado con `composer install --no-dev`).
- [ ] `.env` de producción con `APP_ENV=production`, `APP_DEBUG=false`,
      `APP_URL=https://api.<dominio>.me`, `APP_KEY` nueva, `DB_*` reales.
- [ ] `php artisan migrate` completado sin errores.
- [ ] Catálogos sembrados: `ProductoSeeder` e `IncumplimientoCatalogoSeeder`.
- [ ] Usuario(s) real(es) creados (NO usar `UserSeeder`).
- [ ] `composer install --no-dev` (sin faker/phpunit/pint en producción).
- [ ] Commit + push del código a GitHub (las fases 6.4.6-6.4.8 están sin commitear).
- [ ] Nginx/Apache apuntando a `public/`, HTTPS activo.
- [ ] (Opcional) `storage:link`, `config:cache`, `route:cache`, `view:cache`.

## 19. Checklist post-despliegue

- [ ] `GET https://api.<dominio>.me/up` responde 200 (health check de Laravel 12).
- [ ] `POST /api/login` con un usuario real devuelve 200 + `{ token }`.
- [ ] `GET /api/user` con `Authorization: Bearer <token>` devuelve 200.
- [ ] Sin token → 401 (JSON `{ success: false }`).
- [ ] Roles: CONSULTA recibe 403 en POST/PUT/DELETE.
- [ ] Crear establecimiento + fiscalización vía API (o datos cargados) y comprobar
      `GET /api/fiscalizaciones/{id}/acta/pdf` → `Content-Type: application/pdf`, 200.
- [ ] Regenerar el acta dos veces → un solo Documento (idempotencia) + `numero_paginas`.
- [ ] `migrate:status` → 17/17 ejecutadas.
- [ ] Log revisable en `storage/logs/laravel.log` con `APP_DEBUG=false` (sin stack traces).

## 20. Riesgos clasificados

| Nivel | Riesgo | Estado |
|-------|--------|--------|
| CRÍTICO | Ninguno en código | — |
| ALTO | Login sin rate limit | Abierto (recomendado pre-producción) |
| ALTO | `APP_DEBUG=true` si se copia `.env` local | Se previene en checklist |
| MEDIO | `CORS_ALLOWED_ORIGINS=*` | Ajustar si hay frontend web |
| MEDIO | Token sin expiración | Configurar `SANCTUM_TOKEN_EXPIRATION` |
| MEDIO | `UserSeeder` en producción | NO ejecutar; crear usuarios reales |
| MEDIO | Cambios 6.4.6-6.4.8 sin commitear | Commitear antes de clonar |
| BAJO | `/user` y `/logout` sin chequeo extra de rol | Aceptado por diseño |
| BAJO | Logs daily vs single | Opcional |

## 21. Recomendación de hosting

Para un proyecto académico con consumo Android esporádico:

| Opción | ¿Suficiente? | Simplicidad | Costo/valor |
|--------|--------------|-------------|-------------|
| **A) Hosting compartido** | Depende: PHP 8.2 + Composer | Alta si lo soporta | Buena si cumple |
| **B) VPS (recomendado)** | Sí (Ubuntu + nginx + PHP-FPM + MySQL) | Media | Mejor costo/control (~5-10 $/mes) |
| **C) PaaS (Railway/Render/Fly)** | Sí (contenedor) | Alta (deploy por git) | Puede costar más; requiere volumen persistente para PDFs |
| **D) Otro (VM Laragon)** | — | — | No público |

**Requisitos mínimos del hosting:** PHP 8.2+ (`mbstring`, `iconv`, `pdo_mysql`), MySQL 8 /
MariaDB 10.4+ (`utf8mb4`), Composer + SSH (o `vendor/` subido), document root en `public/`,
HTTPS, `storage/` escribible. **Cron: NO necesario.**

**Recomendación final:** **VPS Linux mínimo** (o PaaS) — suficiente y de mejor relación
costo/simplicidad para este proyecto académico.
## 22. Recomendaciones antes de publicar

1. Fijar `APP_DEBUG=false` y `APP_ENV=production`.
2. Generar `APP_KEY` nueva en el servidor (`php artisan key:generate`).
3. Definir expiración de tokens (`SANCTUM_TOKEN_EXPIRATION`).
4. Añadir throttle al login (o al menos proteger con firewall/WAF del hosting).
5. Ajustar `CORS_ALLOWED_ORIGINS` si habrá frontend web.
6. Commitear y publicar el código en GitHub (público para el proyecto Android).
7. NO versionar ni subir `.env`, PDFs ni cachés.

## 23. Compatibilidad futura con Android

La API ya es consumible desde Android sin cambios en el servidor:

- **URL base Android:** `https://api.<dominio>.me` (variable en la app). Android **9+ bloquea
  tráfico HTTP no localhost** → HTTPS obligatorio (ya previsto).
- **Auth:** `Authorization: Bearer <token>` (Sanctum). El servidor devuelve 401 al expirar;
  la app debe re-autenticarse.
- **Formatos:** JSON con `{ success, message, data }`; códigos 200/201/401/403/404/422/500
  probados. El contrato no cambia; Android no introduce CORS.
- **PDF:** `GET /api/fiscalizaciones/{id}/acta/pdf` → bytes `application/pdf`
  (descargable/abrible con lector). Cabeceras `X-Acta-Pages` / `X-Acta-Documento-Id` sin
  parsear el PDF. Regenerar es idempotente (no duplica Documentos).
- El diseño visual de Android (Activities/XML/logos/colores) no afecta al contrato API.

## 24. Qué NO es necesario modificar

- Estructura de tablas/migraciones (ya compatibles con MySQL de hosting).
- Controllers/Requests/Services (portables; sin rutas Windows).
- `MinimalPdf`/`ActaFiscalizacionPdfService`/`ActaFiscalizacionController` (idempotentes y
  portables; no se sustituye por Dompdf).
- Sanctum (funciona con Bearer; no requiere stateful para Android).
- Middleware `role` (`EnsureUserHasRole`) — ya porta los roles del proyecto.
- `config/cors.php` (configurable por env) y `config/app.php`/`config/sanctum.php` (env).
- Almacenamiento: rutas relativas; solo asegurar permisos de escritura en `storage/`.
- Tests (285/908 ✓) — corren en cualquier entorno con MySQL test.

---

### Verificación realizada en esta auditoría

- `php artisan test` → **285 passed (908 assertions)**, 0 failures, 0 errors.
- `php artisan migrate:status` → 17/17 Ran, 0 pendientes.
- `php artisan route:list --path=api` → **48 rutas**.
- `git grep` → sin rutas Laragon/Windows; `.env` no versionado; `.env.example` sin secretos.
- `public/` → `.htaccess`, `favicon.ico`, `index.php`, `robots.txt` (API pura; `npm build`
  no requerido en producción).
- Comprobados en `artisan list`: `config:cache`, `route:cache`, `view:cache`, `migrate`,
  `db:seed`, `key:generate`, `storage:link` disponibles en este Laravel 12.
- Composer.lock: `laravel/framework v12.69.2`, `laravel/sanctum ^4.0`, PHP `^8.2`,
  extensiones requeridas (ctype, filter, hash, mbstring, openssl, session, tokenizer) + las
  usadas por la app (`iconv`, `pdo_mysql`).
- `bootstrap/cache/packages.php` y `services.php` generados localmente (NO versionados);
  se regeneran en el servidor en el primer arranque.