# PHP 8.3 with FPM (the "engine" that runs PHP behind Nginx)
FROM php:8.3-fpm-alpine

# The official image already ships pdo_sqlite, OPcache and unzip: nothing else to install.
# (Check with: docker compose exec php php -m)

# Composer inside the container (no need to install it on your computer)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-trivial.ini
COPY docker/php/entrypoint.sh /usr/local/bin/trivial-entrypoint
RUN chmod +x /usr/local/bin/trivial-entrypoint

WORKDIR /var/www/html

# Copy code and dependencies so the image works on its own
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p data var/cache/smarty \
    && chown -R www-data:www-data data var

# On start: installs vendor/ if missing and fixes permissions (see entrypoint.sh)
ENTRYPOINT ["trivial-entrypoint"]
CMD ["php-fpm"]
