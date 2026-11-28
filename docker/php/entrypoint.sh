#!/bin/sh
# Se ejecuta cada vez que arranca el contenedor de PHP, antes de php-fpm.
set -e

cd /var/www/html

# 1. Librerías PHP: si falta vendor/ (p. ej. recién clonado), se instalan solas.
#    Así no hay que acordarse de "composer install" la primera vez.
if [ ! -f vendor/autoload.php ]; then
    echo "[trivial] Falta vendor/: ejecutando composer install..."
    composer install --no-interaction --no-progress --prefer-dist --optimize-autoloader
fi

# 2. Carpetas donde PHP necesita escribir (base de datos y caché de plantillas).
mkdir -p data var/cache/smarty
chown -R www-data:www-data data var/cache/smarty

# 3. Arranca php-fpm (o el comando que se haya pedido).
exec docker-php-entrypoint "$@"
