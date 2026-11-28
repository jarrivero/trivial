# PHP 8.3 con FPM (el "motor" que ejecuta PHP detrás de Nginx)
FROM php:8.3-fpm-alpine

# La imagen oficial ya trae pdo_sqlite, OPcache y unzip: no hay que instalar nada más.
# (Compruébalo con: docker compose exec php php -m)

# Composer dentro del contenedor (no hace falta instalarlo en tu ordenador)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-trivial.ini

WORKDIR /var/www/html

# Copiamos el código y las dependencias para que la imagen funcione sola
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p data var/cache/smarty \
    && chown -R www-data:www-data data var
