#!/bin/sh
# Runs every time the PHP container starts, before php-fpm.
set -e

cd /var/www/html

# 1. PHP libraries: if vendor/ is missing (e.g. fresh clone), install them.
#    So nobody has to remember "composer install" the first time.
if [ ! -f vendor/autoload.php ]; then
    echo "[trivial] vendor/ missing: running composer install..."
    composer install --no-interaction --no-progress --prefer-dist --optimize-autoloader
fi

# 2. Folders PHP needs to write to (database and template cache).
mkdir -p data var/cache/smarty
# Compiled templates from the previous version: delete, they are rebuilt automatically.
find var/cache/smarty -mindepth 1 ! -name .gitkeep -delete

# 3. Load new questions from questions/ (existing ones are skipped).
#    If this fails, the website still starts.
php bin/import-questions.php || echo "[trivial] Could not import questions (the website keeps working)."
chown -R www-data:www-data data var/cache/smarty

# 4. Start php-fpm (or whatever command was requested).
exec docker-php-entrypoint "$@"
