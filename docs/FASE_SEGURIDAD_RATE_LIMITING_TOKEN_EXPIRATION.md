# FASE SEGURIDAD — RATE LIMITING DE LOGIN + EXPIRACIÓN DE TOKENS SANCTUM

## 1. Objetivo
Proteger el único endpoint público de la API (`POST /api/login`) contra fuerza bruta y dar vida útil configurable a los tokens Sanctum, sin alterar el contrato JSON, los roles ni la lógica de negocio. Cierra los dos riesgos (ALTO/MEDIO) pendientes de la auditoría 6.4.8 / pre-despliegue: *rate limiting* y *estrategia de expiración de tokens*.

## 2. Alcance
| Ítem | Decisión |
|---|---|
| Rate limiting | Nativo Laravel (`RateLimiter` + `throttle`), sin paquetes |
| Límite | 5 intentos por minuto |
| Clave del límite | DNI enviado + IP del cliente |
| Endpoint protegido | Solo `POST /api/login` |
| Expiración tokens | `SANCTUM_TOKEN_EXPIRATION` (minutos), default `null` (no expira) |
| Migraciones / esquema | Ninguna |
| Contrato JSON / Android | Sin cambios (`Authorization: Bearer <token>`) |

## 3. Rate limiting del login
### 3.1 Implementación (2 archivos)
- `app/Providers/AppServiceProvider.php` — en `boot()`:
  ```php
  RateLimiter::for('login', function (Request $request) {
      return Limit::perMinute(5)->by(($request->input('dni') ?: 'anon').'|'.$request->ip());
  });
  ```
- `routes/api.php` — `Route::post('/login', ...)->middleware('throttle:login');`

### 3.2 Criterio de identificación del cliente
El identificador de login en PRICE es el **DNI** (no email; ver `LoginRequest`). La clave combina `dni|ip`:
- Fuerza bruta contra un DNI concreto → bloqueada tras 5 fallos/min desde esa IP.
- Un DNI legítimo desde otra IP no se ve afectado.
- Un atacante que rota DNI sigue limitado por su IP.
- El usuario legítimo de la demo académica (pocas peticiones/min) no nota el límite.

### 3.3 Comportamiento HTTP
| Caso | Respuesta |
|---|---|
| < 5 intentos/min | Normal: 200 (éxito) o 422 (credenciales inválidas) |
| Intento 6+ en la misma ventana | **429** `Too Many Attempts.` + headers `Retry-After`, `X-RateLimit-Limit`, `X-RateLimit-Remaining` |
| Ventana vencida | Se reinicia automáticamente (ventana de 60 s) |

No se cambia la respuesta de login exitoso, la validación de credenciales, los roles ni Sanctum. No hay tabla propia de rate limiting (usa `cache`, driver `database/array` según `CACHE_STORE`).

## 4. Expiración de tokens Sanctum
### 4.1 Configuración
`config/sanctum.php` (ya presente desde la auditoría):
```php
'expiration' => env('SANCTUM_TOKEN_EXPIRATION', null),
```
`.env.example` (esta fase):
```
# Expiración de tokens Sanctum en minutos (1440 = 24 horas).
SANCTUM_TOKEN_EXPIRATION=1440
```
También documentado en `.env.example`: `CORS_ALLOWED_ORIGINS` (heredado de la auditoría 6.4.8; no se cambió configuración CORS).

### 4.2 Valor recomendado para la demostración: **1440 minutos (24 h)**
- Suficiente para una jornada de fiscalización + sincronización offline.
- No invalida sesiones a mitad de demostración.
- El token expirado produce **401** en cualquier endpoint protegido; Android debe re-loguear (ver §7).

### 4.3 Comportamiento
- Token dentro de la ventana → funciona normal (200).
- Token con `created_at` más antiguo que `expiration` → Sanctum lo rechaza → **401** (mismo formato que token inválido).
- `SANCTUM_TOKEN_EXPIRATION` vacío/ausente → comportamiento anterior: los tokens no expiran (solo se revocan con logout).
- La expiración **no afecta la creación** del token ni el logout; no se registran tokens en logs.

## 5. Cambiar la duración
Solo editar `.env` (sin tocar código):
```
SANCTUM_TOKEN_EXPIRATION=60      # 1 hora
SANCTUM_TOKEN_EXPIRATION=10080   # 7 días
SANCTUM_TOKEN_EXPIRATION=        # sin expiración (default del código)
```
En producción recuerda `php artisan config:cache` tras cambiarlo.

<!-- PARTE 2 -->
