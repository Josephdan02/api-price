FROM php:8.2-fpm

# nginx: servidor HTTP (gestionado por start.sh). curl/unzip: utilidades
# (Composer usa unzip para --prefer-dist). libzip-dev: ext zip. libonig-dev:
# ext mbstring. libxml2-dev: ext dom/xml.
#
# Retirados por no tener ningún uso (auditoría de producción):
#  - supervisor: start.sh gestiona php-fpm + nginx directamente, nunca lo usó.
#  - git: composer instala desde lockfile + dist (zips), no necesita VCS.
#  - default-mysql-client: PDO/pdo_mysql no requiere el binario mysql.
#  - libpng-dev: GD no está instalado; el PDF es MinimalPdf propio, sin Dompdf.
RUN apt-get update && apt-get install -y \
    nginx \
    curl \
    unzip \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        bcmath \
        exif \
        pcntl \
        zip \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Instalar Composer desde la imagen oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copiar primero los archivos de Composer para aprovechar la caché de Docker
COPY composer.json composer.lock ./

# Instalar dependencias de producción
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# Copiar el proyecto
COPY . .

# Ejecutar scripts de Composer después de copiar todo el proyecto
RUN composer dump-autoload --optimize

# Crear directorios necesarios y asignar permisos
RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data \
        storage \
        bootstrap/cache \
    && chmod -R 775 \
        storage \
        bootstrap/cache

# Configuración de Nginx
COPY docker/nginx/default.conf /etc/nginx/sites-available/default

# Script de inicio
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Puerto por defecto del contenedor (solo metadata EXPOSE). En la nube la
# plataforma puede inyectar $PORT y docker/start.sh reconfigura Nginx
# antes de arrancarlo (nginx no lee variables de entorno en su config).
EXPOSE 8080

CMD ["/usr/local/bin/start.sh"]