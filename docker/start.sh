#!/bin/sh

set -e

echo "=========================================="
echo " PRICE API - Iniciando aplicacion Laravel"
echo "=========================================="

# Verificar configuración básica
echo "PHP version:"
php -v

echo "Laravel version:"
php artisan --version

# TLS hacia MySQL gestionado (Aiven): el CA llega en Base64 via la variable
# MYSQL_SSL_CA_B64 y se materializa en /tmp/aiven-ca.pem.
# Debe ejecutarse ANTES de config:cache: config/database.php lee
# MYSQL_ATTR_SSL_CA y el valor queda horneado en bootstrap/cache/config.php.
#  - Definida y no vacia: decodifica, valida que el archivo exista y no este
#    vacio, y exporta MYSQL_ATTR_SSL_CA. Un fallo de decodificacion corta el
#    arranque: si se pidio CA, la BD exigira TLS y continuar solo produciria
#    un crash-loop confuso en migrate.
#  - Ausente o vacia: no cambia nada (desarrollo local sin TLS explicito).
#  - Los mensajes de diagnostico solo muestran el tamano del archivo, nunca
#    su contenido ni el valor de la variable.
if [ -n "${MYSQL_SSL_CA_B64:-}" ]; then
    echo "Configurando CA TLS de la base de datos (MYSQL_SSL_CA_B64 presente)..."
    printf '%s' "$MYSQL_SSL_CA_B64" | tr -d ' \t\r\n' | base64 -d > /tmp/aiven-ca.pem 2>/dev/null \
        || { echo "FATAL: MYSQL_SSL_CA_B64 no es Base64 valido (copiaste el PEM en vez de su Base64?)." >&2; rm -f /tmp/aiven-ca.pem; exit 1; }
    if [ ! -s /tmp/aiven-ca.pem ]; then
        echo "FATAL: el CA TLS decodificado esta vacio." >&2
        rm -f /tmp/aiven-ca.pem
        exit 1
    fi
    export MYSQL_ATTR_SSL_CA="${MYSQL_ATTR_SSL_CA:-/tmp/aiven-ca.pem}"
    echo "CA TLS de la base de datos lista en /tmp/aiven-ca.pem ($(wc -c < /tmp/aiven-ca.pem) bytes)."
else
    echo "MYSQL_SSL_CA_B64 no definida: sin TLS explicito (modo local)."
fi

# Limpiar cachés generadas durante la construcción.
# NOTA: se usa config/route/view clear en vez de `optimize:clear` porque este
# último incluye `cache:clear`; con CACHE_STORE=database y una BD nueva/vacía
# (primer despliegue), `cache:clear` ejecuta `delete from cache` sobre una
# tabla que aún no existe (se crea con migrate) y set -e mata el contenedor.
echo "Limpiando cachés de Laravel..."
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Crear caché de configuración y rutas para producción.
# En la nube el env real solo existe en runtime, por eso el caché se genera
# aquí (y no en la fase de build de la imagen).
echo "Optimizando Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migraciones contra la BD externa con espera/reintento.
# Una BD gestionada (Aiven/TiDB/Render externo) puede tardar en resolver DNS
# o aceptar conexiones cuando el contenedor arranca: sin este bucle, un
# fallo transitorio de migración (set -e) mata el contenedor en crash-loop.
echo "Ejecutando migraciones..."
i=0
until php artisan migrate --force; do
    i=$((i+1))
    if [ "$i" -ge 12 ]; then
        echo "FATAL: la base de datos no respondio tras $i reintentos (~60s)." >&2
        exit 1
    fi
    echo "Base de datos no disponible (intento $i/12). Reintentando en 5s..."
    sleep 5
done

# Crear enlace de storage si es necesario (idempotente; seguro en FS efímero)
if [ ! -L public/storage ]; then
    php artisan storage:link || true
fi

# Puerto dinámico de la plataforma.
# Render (y similares) inyectan $PORT (default 10000). Nginx no soporta
# variables de entorno en su configuración, por lo que el puerto se sustituye
# aquí, ANTES de arrancar nginx. Localmente, sin PORT, queda el 8080 original.
if [ -n "$PORT" ]; then
    case "$PORT" in
        *[!0-9]*)
            echo "PORT='$PORT' no es numérico; se mantiene el puerto 8080." >&2
            ;;
        *)
            echo "Configurando Nginx en el puerto $PORT..."
            sed -i \
                -e "s/listen 8080/listen ${PORT}/" \
                -e "s/listen \[::\]:8080/listen [::]:${PORT}/" \
                /etc/nginx/sites-available/default
            ;;
    esac
fi

# PHP-FPM en primer plano: sus logs (master) van a stdout/stderr del
# contenedor, visibles en `docker logs` y en el stream de la plataforma.
echo "Iniciando PHP-FPM..."
php-fpm --nodaemonize &

# Nginx en primer plano (proceso principal del contenedor)
echo "Iniciando Nginx en el puerto ${PORT:-8080}..."
exec nginx -g "daemon off;"